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
 * Read-only quiz attempt progress repository.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\repository;

use local_tla\indicator\attempt_progress_analyzer;


/**
 * Aggregate per-quiz progress between completed attempts.
 *
 * Completed attempt = {quiz_attempts}.state = 'finished' (Moodle core semantics;
 * 'inprogress', 'overdue' and 'abandoned' are excluded), preview = 0 and a
 * non-null sumgrades. For each student, the first attempt is the one with the
 * lowest attempt number, the last the highest, and the best the one with the
 * highest sumgrades. Scores are normalised to a percentage of the quiz maximum
 * (quiz.sumgrades). Only students with at least two completed attempts count.
 *
 * A quiz is included when it allows more than one attempt (quiz.attempts <> 1)
 * or when at least one student actually has two or more completed attempts;
 * single-attempt quizzes without repetition are excluded so they are not shown
 * misleadingly.
 *
 * Everything is loaded in two bundled queries (quizzes incl. name; attempts),
 * so the query count does not grow with the number of quizzes. Only aggregated,
 * anonymous values are returned.
 */
final class quiz_progress_repository {
    /**
     * Return per-quiz attempt-progress analysis for a course.
     *
     * @param int $courseid Course ID.
     * @param int $minobservations Minimum students (with >= 2 attempts) for a verdict.
     * @return array<int, array{
     *     cmid: int,
     *     quizid: int,
     *     name: string,
     *     participants: int,
     *     totalcompletedattempts: int,
     *     analysis: array
     * }>
     * @throws \invalid_parameter_exception If a parameter is invalid.
     */
    public function get_quiz_attempt_progress(int $courseid, int $minobservations): array {
        global $DB;

        if ($courseid <= 0) {
            throw new \invalid_parameter_exception('Course ID must be greater than zero.');
        }
        if ($minobservations < 1) {
            throw new \invalid_parameter_exception('Minimum observations must be positive.');
        }

        // Quizzes with a live course module and a usable maximum grade.
        $quizzes = $DB->get_records_sql(
            "SELECT q.id AS quizid, q.name AS name, q.sumgrades AS sumgrades,
                    q.attempts AS maxattempts, cm.id AS cmid
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
            return [];
        }

        $quizids = array_keys($quizzes);
        [$insql, $params] = $DB->get_in_or_equal($quizids, SQL_PARAMS_NAMED, 'quiz');

        // All completed, non-preview attempts for these quizzes, in one query.
        $attemptsbyquiz = [];
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
                $attemptsbyquiz[(int) $record->quiz][(int) $record->userid][] = [
                    'attempt' => (int) $record->attempt,
                    'sumgrades' => (float) $record->sumgrades,
                ];
            }
        } finally {
            $records->close();
        }

        $analyzer = new attempt_progress_analyzer();
        $result = [];

        foreach ($quizzes as $quiz) {
            $quizid = (int) $quiz->quizid;
            $max = (float) $quiz->sumgrades;
            $byuser = $attemptsbyquiz[$quizid] ?? [];

            $participants = [];
            $totalcompleted = 0;
            $hasmulti = false;

            foreach ($byuser as $attempts) {
                $totalcompleted += count($attempts);
                if (count($attempts) < 2) {
                    continue;
                }
                $hasmulti = true;

                // Attempts are ordered by attempt number (deterministic).
                $firstsum = $attempts[0]['sumgrades'];
                $lastsum = $attempts[count($attempts) - 1]['sumgrades'];
                $bestsum = $firstsum;
                foreach ($attempts as $a) {
                    if ($a['sumgrades'] > $bestsum) {
                        $bestsum = $a['sumgrades'];
                    }
                }

                $participants[] = [
                    'first' => self::to_percent($firstsum, $max),
                    'last' => self::to_percent($lastsum, $max),
                    'best' => self::to_percent($bestsum, $max),
                    'attempts' => count($attempts),
                ];
            }

            // Exclude single-attempt quizzes with no repetition to avoid a.
            // Misleading verdict.
            if ((int) $quiz->maxattempts === 1 && !$hasmulti) {
                continue;
            }

            $result[] = [
                'cmid' => (int) $quiz->cmid,
                'quizid' => $quizid,
                'name' => (string) $quiz->name,
                'participants' => count($participants),
                'totalcompletedattempts' => $totalcompleted,
                'analysis' => $analyzer->analyze($participants, $minobservations),
            ];
        }

        return $result;
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
