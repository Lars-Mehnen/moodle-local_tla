<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Read-only learning dose-response repository.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\repository;

use local_tla\prediction\emax_bayes_estimator;


/**
 * Build the course-wide learning dose-response and fit a Bayesian Emax model.
 *
 * One observation is produced per (student, quiz): the exposure is the number of
 * completed quiz attempts (state = 'finished', preview = 0, non-null sumgrades)
 * and the effect is the normalised percentage the student reaches on their last
 * completed attempt. Observations are pooled across all quizzes of the course, so
 * a course with several quizzes and a spread of practice volumes yields a curve
 * of achieved performance against amount of practice.
 *
 * Everything is loaded in two bundled queries (quizzes incl. maximum grade;
 * attempts), so the query count does not grow with the number of quizzes. Only
 * aggregated, anonymous values are returned; the raw per-student pairs never leave
 * this method.
 */
final class course_dose_response_repository {
    /**
     * Return the pooled learning dose-response analysis for a course.
     *
     * @param int $courseid Course ID.
     * @param int $minobservations Minimum (student, quiz) pairs for a fit.
     * @return array{
     *     observations: int,
     *     quizzes: int,
     *     analysis: array
     * }
     * @throws \invalid_parameter_exception If a parameter is invalid.
     */
    public function get_learning_dose_response(int $courseid, int $minobservations): array {
        global $DB;

        if ($courseid <= 0) {
            throw new \invalid_parameter_exception('Course ID must be greater than zero.');
        }
        if ($minobservations < 1) {
            throw new \invalid_parameter_exception('Minimum observations must be positive.');
        }

        $estimator = new emax_bayes_estimator();

        // Quizzes with a live course module and a usable maximum grade.
        $quizzes = $DB->get_records_sql(
            "SELECT q.id AS quizid, q.sumgrades AS sumgrades
               FROM {quiz} q
               JOIN {modules} m ON m.name = 'quiz'
               JOIN {course_modules} cm
                 ON cm.course = q.course
                AND cm.module = m.id
                AND cm.instance = q.id
              WHERE q.course = :courseid
                AND cm.deletioninprogress = 0
                AND q.sumgrades > 0
           ORDER BY q.id ASC",
            ['courseid' => $courseid]
        );

        if (empty($quizzes)) {
            return [
                'observations' => 0,
                'quizzes' => 0,
                'analysis' => $estimator->estimate([], $minobservations),
            ];
        }

        $quizids = array_keys($quizzes);
        [$insql, $params] = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED, 'quiz');

        // All completed, non-preview attempts for these quizzes, in one query.
        // Ordered by attempt number so the highest is the student's last attempt.
        $attempts = [];
        $records = $DB->get_recordset_sql(
            "SELECT id, quiz, userid, attempt, sumgrades
               FROM {quiz_attempts}
              WHERE quiz {$insql}
                AND state = :state
                AND preview = 0
                AND sumgrades IS NOT NULL
           ORDER BY quiz ASC, userid ASC, attempt ASC",
            array_merge($params, ['state' => 'finished'])
        );
        try {
            foreach ($records as $record) {
                $attempts[(int) $record->quiz][(int) $record->userid][] = [
                    'attempt' => (int) $record->attempt,
                    'sumgrades' => (float) $record->sumgrades,
                ];
            }
        } finally {
            $records->close();
        }

        $observations = [];
        $contributingquizzes = 0;
        foreach ($quizzes as $quiz) {
            $quizid = (int) $quiz->quizid;
            $max = (float) $quiz->sumgrades;
            $byuser = $attempts[$quizid] ?? [];
            if (empty($byuser)) {
                continue;
            }
            $contributingquizzes++;

            foreach ($byuser as $useratts) {
                // Attempts are ordered by attempt number; the last is the newest.
                $last = $useratts[count($useratts) - 1]['sumgrades'];
                $observations[] = [
                    'c' => (float) count($useratts),
                    'e' => self::to_percent($last, $max),
                ];
            }
        }

        return [
            'observations' => count($observations),
            'quizzes' => $contributingquizzes,
            'analysis' => $estimator->estimate($observations, $minobservations),
        ];
    }

    /**
     * Normalise a raw attempt sum to a percentage of the quiz maximum.
     *
     * @param float $sumgrades Attempt sum of marks.
     * @param float $max Quiz maximum (quiz.sumgrades), guaranteed > 0.
     * @return float Percentage clamped to [0, 100].
     */
    private static function to_percent(float $sumgrades, float $max): float {
        $percentage = 100.0 * $sumgrades / $max;
        if ($percentage < 0.0) {
            return 0.0;
        }
        if ($percentage > 100.0) {
            return 100.0;
        }
        return $percentage;
    }
}
