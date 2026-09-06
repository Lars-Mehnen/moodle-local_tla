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
 * Present current assessment configuration without quality colours.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\output;

defined('MOODLE_INTERNAL') || die();

/** Aggregate/configuration-only template data with bounded, accessible paging. */
final class assessment_design_presenter {
    /** @var int Activity cards per page. All activities remain accessible. */
    public const PAGE_SIZE = 20;

    /**
     * Build a language-aware view of one settings snapshot.
     *
     * @param array $design Snapshot from assessment_design_service.
     * @param \moodle_url $baseurl Current page with course/time-filter parameters.
     * @param int $page Requested zero-based page.
     * @return array Escaped by Mustache; no HTML fragments or student records.
     */
    public function export(array $design, \moodle_url $baseurl, int $page = 0): array {
        $activities = $design['activities'];
        usort($activities, static fn(array $a, array $b): int =>
            [strtolower($a['name']), $a['cmid']] <=> [strtolower($b['name']), $b['cmid']]);
        $total = count($activities);
        $lastpage = max(0, (int) ceil($total / self::PAGE_SIZE) - 1);
        $page = max(0, min($lastpage, $page));
        $shown = array_slice($activities, $page * self::PAGE_SIZE, self::PAGE_SIZE);
        $cards = [];
        foreach ($shown as $activity) {
            $findings = [];
            foreach ($activity['findings'] as $finding) {
                $findings[] = [
                    'code' => $finding['code'],
                    'criterion' => get_string('ad_criterion_' . $finding['criterion'], 'local_tla'),
                    'statuslabel' => get_string('ad_status_' . $finding['status'], 'local_tla'),
                    'message' => get_string($finding['messagekey'], 'local_tla'),
                    'action' => $finding['actionkey'] === null ? '' : get_string($finding['actionkey'], 'local_tla'),
                ];
            }
            $cards[] = [
                'cmid' => $activity['cmid'],
                // Mustache escapes this plain text; do not encode ampersands twice.
                'name' => html_entity_decode(strip_tags(format_string($activity['name'])),
                    ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                'moduletype' => get_string('pluginname', 'mod_' . $activity['module']),
                'hidden' => !$activity['visible'],
                'rulecount' => get_string('ad_rulecount', 'local_tla', count($findings)),
                'viewurl' => (new \moodle_url('/mod/' . $activity['module'] . '/view.php',
                    ['id' => $activity['cmid']]))->out(false),
                'settings' => $this->settings_rows($activity),
                'findings' => $findings,
            ];
        }
        $previous = new \moodle_url($baseurl, ['designpage' => $page - 1]);
        $next = new \moodle_url($baseurl, ['designpage' => $page + 1]);
        return [
            'activities' => $cards,
            'hasactivities' => !empty($cards),
            'snapshotlabel' => get_string('ad_snapshot', 'local_tla',
                userdate($design['checkedat'], get_string('strftimedatetime', 'langconfig'))),
            'pageinfo' => get_string('ad_pagination', 'local_tla', (object) [
                'first' => $total === 0 ? 0 : $page * self::PAGE_SIZE + 1,
                'last' => min($total, ($page + 1) * self::PAGE_SIZE),
                'total' => $total,
            ]),
            'hasprevious' => $page > 0,
            'hasnext' => $page < $lastpage,
            'previousurl' => $previous->out(false) . '#tla-assessment-design',
            'nexturl' => $next->out(false) . '#tla-assessment-design',
        ];
    }

    /**
     * Convert the useful base settings to labelled values, not raw bit masks.
     *
     * @param array $activity Configuration row.
     * @return array Label/value pairs.
     */
    private function settings_rows(array $activity): array {
        $keys = $activity['module'] === 'quiz' ? [
            'attempts', 'grademethod', 'timeopen', 'timeclose', 'delay1', 'delay2',
            'attemptonlast', 'preferredbehaviour',
        ] : [
            'maxattempts', 'attemptreopenmethod', 'gradepass', 'allowsubmissionsfromdate',
            'duedate', 'cutoffdate', 'gradingduedate', 'submissiondrafts', 'markingworkflow', 'gradepenalty',
        ];
        $dates = ['timeopen', 'timeclose', 'allowsubmissionsfromdate', 'duedate', 'cutoffdate', 'gradingduedate'];
        $booleans = ['attemptonlast', 'submissiondrafts', 'markingworkflow', 'gradepenalty'];
        $rows = [];
        foreach ($keys as $key) {
            $value = $activity['settings'][$key] ?? null;
            $text = get_string('ad_status_unknown', 'local_tla');
            if ($value !== null) {
                if (in_array($key, $dates, true)) {
                    $text = (int) $value === 0 ? get_string('ad_none', 'local_tla')
                        : userdate((int) $value, get_string('strftimedatetime', 'langconfig'));
                } else if (in_array($key, $booleans, true)) {
                    $text = get_string($value ? 'yes' : 'no');
                } else if (($key === 'attempts' && (int) $value === 0) ||
                        ($key === 'maxattempts' && (int) $value === -1)) {
                    $text = get_string('ad_unlimited', 'local_tla');
                } else if ($key === 'grademethod') {
                    $text = in_array((int) $value, [1, 2, 3, 4], true)
                        ? get_string('ad_method_' . (int) $value, 'local_tla') : $text;
                } else if ($key === 'attemptreopenmethod') {
                    $text = in_array($value, ['none', 'manual', 'automatic', 'untilpass'], true)
                        ? get_string('ad_reopen_' . $value, 'local_tla') : $text;
                } else {
                    $text = is_numeric($value) ? format_float((float) $value, $key === 'gradepass' ? 2 : 0)
                        : (string) $value;
                }
            }
            $rows[] = ['label' => get_string('ad_setting_' . $key, 'local_tla'), 'value' => $text];
        }
        return $rows;
    }
}
