<?php
// phpcs:ignoreFile
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
 * Shared, database-free assessment-design test cases.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\tests;


/** Test fixtures only. Never loaded by dashboard or external functions. */
final class assessment_design_cases {
    /**
     * A quiz configuration row; overrides are setting changes.
     *
     * @param array $changes Setting overrides merged into the default settings.
     * @param array $flags Top-level flag overrides merged into the row.
     * @return array The quiz configuration row.
     */
    public static function quiz(array $changes = [], array $flags = []): array {
        return array_replace([
            'cmid' => 101, 'instanceid' => 1, 'module' => 'quiz', 'name' => 'SQL practice',
            'visible' => true, 'hasoverrides' => false, 'hasexceptions' => false, 'hasrestrictions' => false,
            'settings' => array_replace([
                'attempts' => 3, 'grademethod' => 1, 'grade' => 100, 'timeopen' => 0, 'timeclose' => 0,
                'delay1' => 0, 'delay2' => 0, 'attemptonlast' => 0, 'preferredbehaviour' => 'deferredfeedback',
                'reviewattempt' => 0, 'reviewcorrectness' => 0, 'reviewmaxmarks' => 0, 'reviewmarks' => 0,
                'reviewspecificfeedback' => 0, 'reviewgeneralfeedback' => 0,
                'reviewrightanswer' => 0, 'reviewoverallfeedback' => 0,
            ], $changes),
        ], $flags);
    }

    /**
     * An assignment configuration row.
     *
     * @param array $changes Setting overrides merged into the default settings.
     * @param array $flags Top-level flag overrides merged into the row.
     * @return array The assignment configuration row.
     */
    public static function assignment(array $changes = [], array $flags = []): array {
        return array_replace([
            'cmid' => 102, 'instanceid' => 1, 'module' => 'assign', 'name' => 'Assignment',
            'visible' => true, 'hasoverrides' => false, 'hasexceptions' => false, 'hasrestrictions' => false,
            'settings' => array_replace([
                'grade' => 100, 'maxattempts' => 3, 'attemptreopenmethod' => 'manual', 'gradepass' => 50,
                'nosubmissions' => 0, 'submissiondrafts' => 1, 'markingworkflow' => 0, 'teamsubmission' => 0,
                'gradepenalty' => 0, 'allowsubmissionsfromdate' => 0, 'duedate' => 0, 'cutoffdate' => 0,
                'gradingduedate' => 0,
            ], $changes),
        ], $flags);
    }

    /**
     * Cases each assert a particular finding and its status.
     * @return array Named data-provider cases.
     */
    public static function cases(): array {
        return [
            'best of attempts' => [self::quiz(), 'quiz_best', 'observed'],
            'average retains mistakes' => [self::quiz(['grademethod' => 2]), 'quiz_average', 'review'],
            'first retains mistakes' => [self::quiz(['grademethod' => 3]), 'quiz_first', 'review'],
            'last replaces earlier' => [self::quiz(['grademethod' => 4]), 'quiz_last', 'observed'],
            'unlimited quiz is zero' => [self::quiz(['attempts' => 0]), 'quiz_best', 'observed'],
            'single ignores average setting' => [self::quiz(['attempts' => 1, 'grademethod' => 2]), 'quiz_single', 'observed'],
            'negative quiz attempt unknown' => [self::quiz(['attempts' => -1]), 'grading_unknown', 'unknown'],
            'missing attempts unknown' => [self::quiz(['attempts' => null]), 'grading_unknown', 'unknown'],
            'unknown grading method' => [self::quiz(['grademethod' => 9]), 'grading_unknown', 'unknown'],
            'ungraded quiz' => [self::quiz(['grade' => 0]), 'ungraded', 'notapplicable'],
            'missing grade' => [self::quiz(['grade' => null]), 'grading_unknown', 'unknown'],
            'feedback while open' => [self::quiz(['reviewattempt' => 256, 'reviewgeneralfeedback' => 256]), 'feedback_open', 'observed'],
            'correctness while open' => [self::quiz(['reviewattempt' => 256, 'reviewcorrectness' => 256]), 'feedback_open', 'observed'],
            'specific feedback while open' => [self::quiz(['reviewattempt' => 256, 'reviewspecificfeedback' => 256]), 'feedback_open', 'observed'],
            'right answer while open' => [self::quiz(['reviewattempt' => 256, 'reviewrightanswer' => 256]), 'feedback_open', 'observed'],
            'marks require maxmarks' => [self::quiz(['reviewmarks' => 256]), 'feedback_no_postattempt', 'review'],
            'maximum only is not feedback' => [self::quiz(['reviewmaxmarks' => 256]), 'feedback_no_postattempt', 'review'],
            'marks independent of attempt gate' => [self::quiz(['reviewmarks' => 256, 'reviewmaxmarks' => 256]), 'feedback_open', 'observed'],
            'overall independent of attempt gate' => [self::quiz(['reviewoverallfeedback' => 256]), 'feedback_open', 'observed'],
            'attempt alone not guidance' => [self::quiz(['reviewattempt' => 256]), 'feedback_no_postattempt', 'review'],
            'immediate only' => [self::quiz(['reviewoverallfeedback' => 4096]), 'feedback_immediate', 'review'],
            'open takes precedence' => [self::quiz(['reviewoverallfeedback' => 4352]), 'feedback_open', 'observed'],
            'after close with date' => [self::quiz(['reviewoverallfeedback' => 16, 'timeclose' => 500]), 'feedback_after_close', 'review'],
            'after close without date' => [self::quiz(['reviewoverallfeedback' => 16]), 'feedback_no_close', 'review'],
            'during is not post attempt' => [self::quiz(['reviewattempt' => 65536, 'reviewgeneralfeedback' => 65536]), 'feedback_no_postattempt', 'review'],
            'bits cannot cross phases' => [self::quiz(['reviewattempt' => 256, 'reviewgeneralfeedback' => 4096]), 'feedback_no_postattempt', 'review'],
            'missing review setting' => [self::quiz(['reviewmarks' => null]), 'feedback_unknown', 'unknown'],
            'no whole quiz repeat' => [self::quiz(['attempts' => 1]), 'feedback_no_retry', 'notapplicable'],
            'question rules never guessed' => [self::quiz(['preferredbehaviour' => 'adaptive_adapted_for_coderunner']), 'question_rules', 'unknown'],
            'continuation noted' => [self::quiz(['attemptonlast' => 1]), 'quiz_continuation', 'observed'],
            'retry delay spans window' => [self::quiz(['timeopen' => 100, 'timeclose' => 300, 'delay1' => 200]), 'quiz_retry_window', 'review'],
            'invalid base interval' => [self::quiz(['timeopen' => 300, 'timeclose' => 100]), 'quiz_retry_window', 'review'],
            'quiz overrides explicit' => [self::quiz([], ['hasoverrides' => true]), 'overrides', 'unknown'],
            'hidden quiz explicit' => [self::quiz([], ['visible' => false]), 'access', 'unknown'],
            'availability explicit' => [self::quiz([], ['hasrestrictions' => true]), 'access', 'unknown'],
            'assignment manual' => [self::assignment(), 'assign_manual', 'review'],
            'assignment automatic' => [self::assignment(['attemptreopenmethod' => 'automatic']), 'assign_automatic', 'observed'],
            'assignment until passing' => [self::assignment(['attemptreopenmethod' => 'untilpass']), 'assign_untilpass', 'review'],
            'assignment unlimited is minus one' => [self::assignment(['maxattempts' => -1]), 'assign_manual', 'review'],
            'assignment single ignores untilpass' => [self::assignment(['maxattempts' => 1, 'attemptreopenmethod' => 'untilpass']), 'assign_single', 'observed'],
            'legacy none is no reopening' => [self::assignment(['attemptreopenmethod' => 'none']), 'assign_single', 'observed'],
            'assignment zero not unlimited' => [self::assignment(['maxattempts' => 0]), 'grading_unknown', 'unknown'],
            'assignment unknown method' => [self::assignment(['attemptreopenmethod' => 'custom']), 'grading_unknown', 'unknown'],
            'untilpass missing threshold' => [self::assignment(['attemptreopenmethod' => 'untilpass', 'gradepass' => 0]), 'assign_pass_missing', 'review'],
            'untilpass missing gradeitem' => [self::assignment(['attemptreopenmethod' => 'untilpass', 'gradepass' => null]), 'assign_pass_missing', 'review'],
            'assignment offline' => [self::assignment(['nosubmissions' => 1]), 'assign_offline', 'unknown'],
            'assignment ungraded' => [self::assignment(['grade' => 0]), 'ungraded', 'notapplicable'],
            'feedback content not inferred' => [self::assignment(), 'assign_feedback_unknown', 'unknown'],
            'marking workflow noted' => [self::assignment(['markingworkflow' => 1]), 'assign_feedback_workflow', 'unknown'],
            'teams not individual proof' => [self::assignment(['teamsubmission' => 1]), 'assign_team', 'observed'],
            'real Moodle penalty noted' => [self::assignment(['gradepenalty' => 1]), 'assign_gradepenalty', 'unknown'],
            'planned feedback at cutoff' => [self::assignment(['cutoffdate' => 300, 'gradingduedate' => 300]), 'assign_feedback_schedule', 'review'],
            'individual extension or lock' => [self::assignment([], ['hasexceptions' => true]), 'overrides', 'unknown'],
            'assignment overrides' => [self::assignment([], ['hasoverrides' => true]), 'overrides', 'unknown'],
            'unsupported not guessed' => [self::assignment([], ['module' => 'workshop']), 'unsupported', 'unknown'],
        ];
    }
}
