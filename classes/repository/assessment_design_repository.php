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
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * Read current assessment settings without loading student records.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\repository;

defined('MOODLE_INTERNAL') || die();

/**
 * A read-only snapshot of live quiz and assignment configuration.
 *
 * Two bundled SELECT statements, regardless of cohort size. Correlated EXISTS
 * checks use activity keys and return presence flags only: no user/group ids,
 * override reasons, passwords, answers, or availability JSON leave the database.
 * Hidden modules are included for authorised teachers, deleted modules are not.
 * These are base settings, NOT reconstructed effective settings per student.
 */
final class assessment_design_repository {
    /**
     * Fetch the current configuration of supported activities.
     *
     * @param int $courseid Course id.
     * @return array Configuration rows; no student-level data.
     */
    public function get_course_settings(int $courseid): array {
        global $DB;
        if ($courseid <= 0) {
            throw new \invalid_parameter_exception('Course ID must be greater than zero.');
        }

        $quizzes = $DB->get_records_sql(
            "SELECT q.id AS instanceid, cm.id AS cmid, q.name, cm.visible,
                    q.attempts, q.grademethod, q.grade, q.preferredbehaviour,
                    q.timeopen, q.timeclose, q.delay1, q.delay2, q.attemptonlast,
                    q.reviewattempt, q.reviewcorrectness, q.reviewmaxmarks, q.reviewmarks,
                    q.reviewspecificfeedback, q.reviewgeneralfeedback,
                    q.reviewrightanswer, q.reviewoverallfeedback,
                    CASE WHEN cm.availability IS NULL THEN 0 ELSE 1 END AS hasrestrictions,
                    CASE WHEN EXISTS (SELECT 1 FROM {quiz_overrides} qo WHERE qo.quiz = q.id)
                         THEN 1 ELSE 0 END AS hasoverrides
               FROM {quiz} q
               JOIN {modules} m ON m.name = 'quiz'
               JOIN {course_modules} cm ON cm.instance = q.id AND cm.module = m.id AND cm.course = q.course
              WHERE q.course = :courseid AND cm.deletioninprogress = 0
           ORDER BY q.id",
            ['courseid' => $courseid]
        );
        $assignments = $DB->get_records_sql(
            "SELECT a.id AS instanceid, cm.id AS cmid, a.name, cm.visible,
                    a.grade, a.maxattempts, a.attemptreopenmethod, a.nosubmissions,
                    a.submissiondrafts, a.markingworkflow, a.teamsubmission, a.gradepenalty,
                    a.allowsubmissionsfromdate, a.duedate, a.cutoffdate, a.gradingduedate,
                    gi.gradepass,
                    CASE WHEN cm.availability IS NULL THEN 0 ELSE 1 END AS hasrestrictions,
                    CASE WHEN EXISTS (SELECT 1 FROM {assign_overrides} ao WHERE ao.assignid = a.id)
                         THEN 1 ELSE 0 END AS hasoverrides,
                    CASE WHEN EXISTS (SELECT 1 FROM {assign_user_flags} af
                                       WHERE af.assignment = a.id
                                         AND (af.extensionduedate > 0 OR af.locked <> 0))
                         THEN 1 ELSE 0 END AS hasexceptions
               FROM {assign} a
               JOIN {modules} m ON m.name = 'assign'
               JOIN {course_modules} cm ON cm.instance = a.id AND cm.module = m.id AND cm.course = a.course
          LEFT JOIN {grade_items} gi ON gi.courseid = a.course AND gi.itemtype = 'mod'
                    AND gi.itemmodule = 'assign' AND gi.iteminstance = a.id AND gi.itemnumber = 0
              WHERE a.course = :courseid AND cm.deletioninprogress = 0
           ORDER BY a.id",
            ['courseid' => $courseid]
        );
        $result = [];
        foreach (['quiz' => $quizzes, 'assign' => $assignments] as $module => $records) {
            foreach ($records as $record) {
                $settings = [];
                // A strict whitelist avoids inadvertently exporting future DB fields.
                $numeric = $module === 'quiz' ? [
                    'attempts', 'grademethod', 'grade', 'timeopen', 'timeclose', 'delay1', 'delay2',
                    'attemptonlast', 'reviewattempt', 'reviewcorrectness', 'reviewmaxmarks', 'reviewmarks',
                    'reviewspecificfeedback', 'reviewgeneralfeedback', 'reviewrightanswer', 'reviewoverallfeedback',
                ] : [
                    'grade', 'maxattempts', 'nosubmissions', 'submissiondrafts', 'markingworkflow',
                    'teamsubmission', 'gradepenalty', 'allowsubmissionsfromdate', 'duedate', 'cutoffdate',
                    'gradingduedate', 'gradepass',
                ];
                foreach ($numeric as $key) {
                    $settings[$key] = $record->$key === null ? null : (float) $record->$key;
                }
                $textkey = $module === 'quiz' ? 'preferredbehaviour' : 'attemptreopenmethod';
                $settings[$textkey] = (string) $record->$textkey;
                $result[] = [
                    'cmid' => (int) $record->cmid,
                    'instanceid' => (int) $record->instanceid,
                    'module' => $module,
                    'name' => (string) $record->name,
                    'visible' => (bool) $record->visible,
                    'hasrestrictions' => (bool) $record->hasrestrictions,
                    'hasoverrides' => (bool) $record->hasoverrides,
                    'hasexceptions' => !empty($record->hasexceptions),
                    'settings' => $settings,
                ];
            }
        }
        return $result;
    }
}
