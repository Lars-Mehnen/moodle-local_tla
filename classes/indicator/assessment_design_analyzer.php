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
 * Explain assessment configuration without rating people or teaching quality.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\indicator;

defined('MOODLE_INTERNAL') || die();

/**
 * Deterministic, database-free rules. No simulations and no grade changes.
 *
 * Findings distinguish observations, items to review, unknowns, and criteria
 * that do not apply. A review is NOT a verdict of unfairness. The pedagogical
 * purpose, course-grade weight and independent authorship remain unknown.
 */
final class assessment_design_analyzer {
    // Moodle mod/quiz/lib.php. Kept explicit to avoid loading the entire module
    // for this pure calculation; the integration test checks parity with core.
    public const GRADE_HIGHEST = 1;
    public const GRADE_AVERAGE = 2;
    public const GRADE_FIRST = 3;
    public const GRADE_LAST = 4;

    // Moodle mod_quiz\question\display_options review bit fields.
    public const DURING = 0x10000;
    public const IMMEDIATE = 0x01000;
    public const OPEN = 0x00100;
    public const CLOSED = 0x00010;

    /**
     * Analyse one whitelisted repository row.
     *
     * @param array $activity Configuration row.
     * @return array Configuration with criterion findings and interpretation context.
     */
    public function analyze(array $activity): array {
        $findings = [];
        $retrycontext = 'unknown';
        $s = $activity['settings'];
        if ($activity['module'] === 'quiz') {
            [$findings, $retrycontext] = $this->quiz_findings($s);
        } else if ($activity['module'] === 'assign') {
            [$findings, $retrycontext] = $this->assignment_findings($s);
        } else {
            $findings[] = self::finding('unsupported', 'unknown', 'scope');
        }
        if (!empty($activity['hasoverrides']) || !empty($activity['hasexceptions'])) {
            $findings[] = self::finding('overrides', 'unknown', 'scope', 'effective');
        }
        if (!empty($activity['hasrestrictions']) || empty($activity['visible'])) {
            $findings[] = self::finding('access', 'unknown', 'scope', 'effective');
        }
        $activity['source'] = 'current_configuration';
        $activity['scope'] = 'base_settings';
        $activity['purpose'] = 'unknown';
        $activity['courseweight'] = 'not_evaluated';
        $activity['retrycontext'] = $retrycontext;
        $activity['findings'] = $findings;
        return $activity;
    }

    /**
     * Rules for completed quiz attempts, distinct from question-level retries.
     *
     * @param array $s Quiz settings.
     * @return array Pair of findings and retry context.
     */
    private function quiz_findings(array $s): array {
        $f = [];
        $attempts = isset($s['attempts']) ? (int) $s['attempts'] : -1;
        $multiple = $attempts === 0 || $attempts > 1;
        $context = 'unknown';
        if (!isset($s['grade'])) {
            $f[] = self::finding('grading_unknown', 'unknown', 'grading');
        } else if ((float) $s['grade'] <= 0) {
            $f[] = self::finding('ungraded', 'notapplicable', 'grading');
            $context = 'ungraded';
        } else if ($attempts === 1) {
            $f[] = self::finding('quiz_single', 'observed', 'grading', 'purpose');
            $context = 'single';
        } else if (!$multiple) {
            $f[] = self::finding('grading_unknown', 'unknown', 'grading');
        } else {
            $method = (int) ($s['grademethod'] ?? 0);
            [$code, $status, $context] = match ($method) {
                self::GRADE_HIGHEST => ['quiz_best', 'observed', 'bestofmultiple'],
                self::GRADE_AVERAGE => ['quiz_average', 'review', 'averageofmultiple'],
                self::GRADE_FIRST => ['quiz_first', 'review', 'firstofmultiple'],
                self::GRADE_LAST => ['quiz_last', 'observed', 'lastofmultiple'],
                default => ['grading_unknown', 'unknown', 'unknown'],
            };
            $f[] = self::finding($code, $status, 'grading', 'purpose');
        }

        if ($multiple) {
            $f[] = $this->quiz_feedback_finding($s);
            // This is a necessary-bound check, NOT a prediction of working speed.
            $open = (int) ($s['timeopen'] ?? 0);
            $close = (int) ($s['timeclose'] ?? 0);
            if ($open > 0 && $close > 0 &&
                    ($close <= $open || (int) ($s['delay1'] ?? 0) >= $close - $open)) {
                $f[] = self::finding('quiz_retry_window', 'review', 'timing', 'timing');
            }
        } else {
            $f[] = self::finding('feedback_no_retry', $attempts === 1 ? 'notapplicable' : 'unknown', 'feedback');
        }
        if (!empty($s['attemptonlast'])) {
            $f[] = self::finding('quiz_continuation', 'observed', 'grading');
        }
        // A quiz's preferred behaviour is not a reliable inventory of the actual
        // question types or their penalties. CodeRunner may override behaviour.
        $f[] = self::finding('question_rules', 'unknown', 'questionrules', 'questionrules');
        return [$f, $context];
    }

    /**
     * Inspect standard post-attempt feedback display permissions, not its content.
     *
     * @param array $s Settings.
     * @return array Finding.
     */
    private function quiz_feedback_finding(array $s): array {
        $required = ['reviewattempt', 'reviewcorrectness', 'reviewmaxmarks', 'reviewmarks',
            'reviewspecificfeedback', 'reviewgeneralfeedback', 'reviewrightanswer', 'reviewoverallfeedback', 'timeclose'];
        foreach ($required as $field) {
            if (!isset($s[$field])) {
                return self::finding('feedback_unknown', 'unknown', 'feedback');
            }
        }
        if (self::has_review_signal($s, self::OPEN)) {
            return self::finding('feedback_open', 'observed', 'feedback', 'feedbackcontent');
        }
        if (self::has_review_signal($s, self::IMMEDIATE)) {
            return self::finding('feedback_immediate', 'review', 'feedback', 'feedbackwindow');
        }
        if (self::has_review_signal($s, self::CLOSED)) {
            return self::finding((int) $s['timeclose'] === 0 ? 'feedback_no_close' : 'feedback_after_close',
                'review', 'feedback', 'feedbackwindow');
        }
        return self::finding('feedback_no_postattempt', 'review', 'feedback', 'feedbackwindow');
    }

    /**
     * Is any standard result/feedback category enabled in a single review phase?
     *
     * Bits from different phases must never be combined to infer permission.
     * Maximum marks alone are not feedback about the student's response.
     *
     * @param array $s Settings.
     * @param int $phase One Moodle review phase.
     * @return bool Configured permission; not proof of available feedback text.
     */
    public static function has_review_signal(array $s, int $phase): bool {
        if (((int) ($s['reviewoverallfeedback'] ?? 0) & $phase) !== 0) {
            return true;
        }
        if (((int) ($s['reviewmaxmarks'] ?? 0) & $phase) !== 0 &&
                ((int) ($s['reviewmarks'] ?? 0) & $phase) !== 0) {
            return true;
        }
        if (((int) ($s['reviewattempt'] ?? 0) & $phase) === 0) {
            return false;
        }
        foreach (['reviewcorrectness', 'reviewspecificfeedback', 'reviewgeneralfeedback', 'reviewrightanswer'] as $field) {
            if (((int) ($s[$field] ?? 0) & $phase) !== 0) {
                return true;
            }
        }
        return false;
    }

    /**
     * Rules for assignment reopening and the limits of configuration evidence.
     *
     * @param array $s Assignment settings.
     * @return array Pair of findings and retry context.
     */
    private function assignment_findings(array $s): array {
        $f = [];
        $context = 'unknown';
        $attempts = isset($s['maxattempts']) ? (int) $s['maxattempts'] : 0;
        $multiple = $attempts === -1 || $attempts > 1;
        $method = $s['attemptreopenmethod'] ?? '';
        if (!empty($s['nosubmissions'])) {
            $f[] = self::finding('assign_offline', 'unknown', 'grading', 'purpose');
            $context = 'offline';
        } else if ($attempts === 1 || $method === 'none') {
            $f[] = self::finding('assign_single', 'observed', 'grading', 'purpose');
            $context = 'single';
        } else if (!$multiple) {
            $f[] = self::finding('grading_unknown', 'unknown', 'grading');
        } else {
            [$code, $status, $context] = match ($method) {
                'manual' => ['assign_manual', 'review', 'manual'],
                'automatic' => ['assign_automatic', 'observed', 'automatic'],
                'untilpass' => ['assign_untilpass', 'review', 'untilpass'],
                default => ['grading_unknown', 'unknown', 'unknown'],
            };
            $f[] = self::finding($code, $status, 'grading', 'purpose');
            if ($method === 'untilpass' && (!isset($s['gradepass']) || (float) $s['gradepass'] <= 0)) {
                $f[] = self::finding('assign_pass_missing', 'review', 'grading', 'passgrade');
            }
        }
        if (isset($s['grade']) && (float) $s['grade'] === 0.0) {
            $f[] = self::finding('ungraded', 'notapplicable', 'grading');
        }
        $f[] = self::finding(!empty($s['markingworkflow']) ? 'assign_feedback_workflow' : 'assign_feedback_unknown',
            'unknown', 'feedback', 'assignmentfeedback');
        if (!empty($s['teamsubmission'])) {
            $f[] = self::finding('assign_team', 'observed', 'scope', 'team');
        }
        if (!empty($s['gradepenalty'])) {
            $f[] = self::finding('assign_gradepenalty', 'unknown', 'grading', 'gradepenalty');
        }
        if ($multiple && empty($s['nosubmissions']) && (int) ($s['cutoffdate'] ?? 0) > 0 &&
                (int) ($s['gradingduedate'] ?? 0) >= (int) $s['cutoffdate']) {
            $f[] = self::finding('assign_feedback_schedule', 'review', 'timing', 'assignmentfeedback');
        }
        return [$f, $context];
    }

    /**
     * Build a translatable, stable criterion result (no language dependency).
     *
     * @param string $code Machine-readable rule code.
     * @param string $status observed|review|unknown|notapplicable.
     * @param string $criterion Criterion group.
     * @param string|null $action Optional contextual review action.
     * @return array Finding.
     */
    private static function finding(string $code, string $status, string $criterion, ?string $action = null): array {
        return [
            'code' => $code,
            'status' => $status,
            'criterion' => $criterion,
            'messagekey' => 'ad_rule_' . $code,
            'actionkey' => $action === null ? null : 'ad_action_' . $action,
        ];
    }
}
