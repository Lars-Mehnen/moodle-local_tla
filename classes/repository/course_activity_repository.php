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
 * Course activity data access.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\repository;

use local_tla\assignment\effective_deadline_resolver;


/**
 * Read-only repository for course activity data.
 */
final class course_activity_repository {
    /**
     * Return event counts grouped by UTC calendar day.
     *
     * The interval is start-inclusive and end-exclusive:
     * timestart <= timecreated < timeend.
     *
     * Days without events are omitted.
     *
     * @param int $courseid Course ID.
     * @param int $timestart Inclusive Unix timestamp.
     * @param int $timeend Exclusive Unix timestamp.
     * @return array
     * @throws \invalid_parameter_exception If a parameter is invalid.
     */
    public function get_events_per_day(
        int $courseid,
        int $timestart,
        int $timeend
    ): array {
        global $DB;

        $this->validate_parameters($courseid, $timestart, $timeend);

        $sql = "SELECT id, timecreated
                  FROM {logstore_standard_log}
                 WHERE courseid = :courseid
                   AND timecreated >= :timestart
                   AND timecreated < :timeend
              ORDER BY timecreated ASC, id ASC";

        $params = [
            'courseid' => $courseid,
            'timestart' => $timestart,
            'timeend' => $timeend,
        ];

        $counts = [];
        $records = $DB->get_recordset_sql($sql, $params);

        try {
            foreach ($records as $record) {
                $daystart = self::utc_day_start((int) $record->timecreated);

                if (!isset($counts[$daystart])) {
                    $counts[$daystart] = 0;
                }

                $counts[$daystart]++;
            }
        } finally {
            $records->close();
        }

        $result = [];
        foreach ($counts as $daystart => $eventcount) {
            $result[] = [
                'daystart' => (int) $daystart,
                'eventcount' => $eventcount,
            ];
        }

        return $result;
    }

    /**
     * Return distinct active-user counts grouped by UTC calendar day.
     *
     * A user is considered active when a matching log record has userid > 0.
     * Multiple events by the same user on the same day count once.
     *
     * The interval is start-inclusive and end-exclusive:
     * timestart <= timecreated < timeend.
     *
     * Days without active users are omitted.
     *
     * @param int $courseid Course ID.
     * @param int $timestart Inclusive Unix timestamp.
     * @param int $timeend Exclusive Unix timestamp.
     * @return array
     * @throws \invalid_parameter_exception If a parameter is invalid.
     */
    public function get_active_users_per_day(
        int $courseid,
        int $timestart,
        int $timeend
    ): array {
        global $DB;

        $this->validate_parameters($courseid, $timestart, $timeend);

        $sql = "SELECT id, userid, timecreated
                  FROM {logstore_standard_log}
                 WHERE courseid = :courseid
                   AND userid > 0
                   AND timecreated >= :timestart
                   AND timecreated < :timeend
              ORDER BY timecreated ASC, id ASC";

        $params = [
            'courseid' => $courseid,
            'timestart' => $timestart,
            'timeend' => $timeend,
        ];

        $usersbyday = [];
        $records = $DB->get_recordset_sql($sql, $params);

        try {
            foreach ($records as $record) {
                $daystart = self::utc_day_start((int) $record->timecreated);
                $userid = (int) $record->userid;

                if (!isset($usersbyday[$daystart])) {
                    $usersbyday[$daystart] = [];
                }

                $usersbyday[$daystart][$userid] = true;
            }
        } finally {
            $records->close();
        }

        $result = [];
        foreach ($usersbyday as $daystart => $userids) {
            $result[] = [
                'daystart' => (int) $daystart,
                'activeusers' => count($userids),
            ];
        }

        return $result;
    }


    /**
     * Return event counts grouped by Moodle component.
     *
     * Only activity-module components beginning with "mod_" are included.
     * The interval is start-inclusive and end-exclusive.
     *
     * @param int $courseid Course ID.
     * @param int $timestart Inclusive Unix timestamp.
     * @param int $timeend Exclusive Unix timestamp.
     * @return array
     * @throws \invalid_parameter_exception If a parameter is invalid.
     */
    public function get_module_events(
        int $courseid,
        int $timestart,
        int $timeend
    ): array {
        global $DB;

        $this->validate_parameters($courseid, $timestart, $timeend);

        $componentcondition = $DB->sql_like(
            'component',
            ':componentprefix',
            false
        );

        $sql = "SELECT id, component
                  FROM {logstore_standard_log}
                 WHERE courseid = :courseid
                   AND {$componentcondition}
                   AND timecreated >= :timestart
                   AND timecreated < :timeend
              ORDER BY component ASC, id ASC";

        $params = [
            'courseid' => $courseid,
            'componentprefix' => $DB->sql_like_escape('mod_') . '%',
            'timestart' => $timestart,
            'timeend' => $timeend,
        ];

        $counts = [];
        $records = $DB->get_recordset_sql($sql, $params);

        try {
            foreach ($records as $record) {
                $component = (string) $record->component;

                if (!isset($counts[$component])) {
                    $counts[$component] = 0;
                }

                $counts[$component]++;
            }
        } finally {
            $records->close();
        }

        $result = [];
        foreach ($counts as $component => $eventcount) {
            $result[] = [
                'component' => $component,
                'eventcount' => $eventcount,
            ];
        }

        return $result;
    }


    /**
     * Return a summary of individual assignment submissions.
     *
     * This intentionally covers only individual submissions:
     * - latest attempt only
     * - status "submitted"
     * - userid greater than zero
     * - groupid equal to zero
     *
     * Assignment overrides, extensions, and group submissions are not
     * considered by this baseline method.
     *
     * The interval applies to assign_submission.timemodified and is
     * start-inclusive and end-exclusive.
     *
     * @param int $courseid Course ID.
     * @param int $timestart Inclusive Unix timestamp.
     * @param int $timeend Exclusive Unix timestamp.
     * @return array
     * @throws \invalid_parameter_exception If a parameter is invalid.
     */
    public function get_individual_assignment_submission_summary(
        int $courseid,
        int $timestart,
        int $timeend
    ): array {
        global $DB;

        $this->validate_parameters($courseid, $timestart, $timeend);

        $sql = "SELECT s.id, s.timemodified, a.duedate
                  FROM {assign_submission} s
                  JOIN {assign} a
                    ON a.id = s.assignment
                 WHERE a.course = :courseid
                   AND s.latest = :latest
                   AND s.status = :status
                   AND s.userid > 0
                   AND s.groupid = 0
                   AND s.timemodified >= :timestart
                   AND s.timemodified < :timeend
              ORDER BY s.id ASC";

        $params = [
            'courseid' => $courseid,
            'latest' => 1,
            'status' => 'submitted',
            'timestart' => $timestart,
            'timeend' => $timeend,
        ];

        $summary = [
            'submitted' => 0,
            'ontime' => 0,
            'late' => 0,
            'noduedate' => 0,
        ];

        $records = $DB->get_recordset_sql($sql, $params);

        try {
            foreach ($records as $record) {
                $summary['submitted']++;

                $duedate = (int) $record->duedate;
                $timemodified = (int) $record->timemodified;

                if ($duedate === 0) {
                    $summary['noduedate']++;
                } else if ($timemodified <= $duedate) {
                    $summary['ontime']++;
                } else {
                    $summary['late']++;
                }
            }
        } finally {
            $records->close();
        }

        return $summary;
    }

    /**
     * Return a summary of individual assignment submissions using the effective
     * per-user deadline.
     *
     * Like get_individual_assignment_submission_summary() this covers only
     * individual submissions (latest attempt, status "submitted", userid > 0,
     * groupid = 0) within the start-inclusive, end-exclusive interval applied to
     * assign_submission.timemodified. Group (team) submissions remain excluded.
     *
     * In contrast to the baseline method, the due date each submission is judged
     * against is the effective per-user deadline: user overrides, group
     * overrides (lowest sortorder wins) and individual extensions are all taken
     * into account, reproducing Moodle core semantics via
     * {@see effective_deadline_resolver}. Overrides may move a deadline both
     * earlier and later.
     *
     * The invariant submitted = ontime + late + noduedate + unresolved always
     * holds. The mechanism counters (extended, useroverride, groupoverride) are
     * independent attributes describing which mechanisms influenced the
     * effective deadline; they may overlap with ontime/late and with each other
     * (e.g. a submission whose base deadline came from a group override and was
     * then pushed later by an extension counts under both groupoverride and
     * extended).
     *
     * All required override, group-membership and extension data is loaded in a
     * small fixed number of bulk queries to avoid per-submission lookups.
     *
     * @param int $courseid Course ID.
     * @param int $timestart Inclusive Unix timestamp.
     * @param int $timeend Exclusive Unix timestamp.
     * @return array{
     *     submitted: int,
     *     ontime: int,
     *     late: int,
     *     noduedate: int,
     *     extended: int,
     *     useroverride: int,
     *     groupoverride: int,
     *     unresolved: int
     * }
     * @throws \invalid_parameter_exception If a parameter is invalid.
     */
    public function get_effective_individual_assignment_submission_summary(
        int $courseid,
        int $timestart,
        int $timeend
    ): array {
        global $DB;

        $this->validate_parameters($courseid, $timestart, $timeend);

        $summary = [
            'submitted' => 0,
            'ontime' => 0,
            'late' => 0,
            'noduedate' => 0,
            'extended' => 0,
            'useroverride' => 0,
            'groupoverride' => 0,
            'unresolved' => 0,
        ];

        // Bulk-load user overrides for the course: keyed by "assignid:userid".
        // The existence of a row matters even when its duedate column is null.
        $useroverrideexists = [];
        $useroverrideduedate = [];
        $useroverriderecords = $DB->get_recordset_sql(
            "SELECT o.id, o.assignid, o.userid, o.duedate
               FROM {assign_overrides} o
               JOIN {assign} a ON a.id = o.assignid
              WHERE a.course = :courseid
                AND o.userid IS NOT NULL",
            ['courseid' => $courseid]
        );
        try {
            foreach ($useroverriderecords as $record) {
                $key = $record->assignid . ':' . $record->userid;
                $useroverrideexists[$key] = true;
                $useroverrideduedate[$key] =
                    $record->duedate === null ? null : (int) $record->duedate;
            }
        } finally {
            $useroverriderecords->close();
        }

        // Bulk-load group overrides for the course: keyed by assignid.
        $groupoverridesbyassign = [];
        $groupoverriderecords = $DB->get_recordset_sql(
            "SELECT o.id, o.assignid, o.groupid, o.sortorder, o.duedate
               FROM {assign_overrides} o
               JOIN {assign} a ON a.id = o.assignid
              WHERE a.course = :courseid
                AND o.groupid IS NOT NULL",
            ['courseid' => $courseid]
        );
        try {
            foreach ($groupoverriderecords as $record) {
                $assignid = (int) $record->assignid;
                $groupoverridesbyassign[$assignid][] = [
                    'groupid' => (int) $record->groupid,
                    'sortorder' => $record->sortorder === null ? null : (int) $record->sortorder,
                    'duedate' => $record->duedate === null ? null : (int) $record->duedate,
                ];
            }
        } finally {
            $groupoverriderecords->close();
        }

        // Bulk-load current group memberships for the course: userid => set of groupids.
        $usergroups = [];
        $memberrecords = $DB->get_recordset_sql(
            "SELECT gm.id, gm.userid, gm.groupid
               FROM {groups_members} gm
               JOIN {groups} g ON g.id = gm.groupid
              WHERE g.courseid = :courseid",
            ['courseid' => $courseid]
        );
        try {
            foreach ($memberrecords as $record) {
                $usergroups[(int) $record->userid][(int) $record->groupid] = true;
            }
        } finally {
            $memberrecords->close();
        }

        // Bulk-load active individual extensions: keyed by "assignid:userid".
        $extensions = [];
        $extensionrecords = $DB->get_recordset_sql(
            "SELECT uf.id, uf.assignment, uf.userid, uf.extensionduedate
               FROM {assign_user_flags} uf
               JOIN {assign} a ON a.id = uf.assignment
              WHERE a.course = :courseid
                AND uf.extensionduedate > 0",
            ['courseid' => $courseid]
        );
        try {
            foreach ($extensionrecords as $record) {
                $key = $record->assignment . ':' . $record->userid;
                $extensions[$key] = (int) $record->extensionduedate;
            }
        } finally {
            $extensionrecords->close();
        }

        // Stream the in-scope individual submissions and resolve each one.
        $resolver = new effective_deadline_resolver();

        $sql = "SELECT s.id, s.assignment, s.userid, s.timemodified, a.duedate
                  FROM {assign_submission} s
                  JOIN {assign} a
                    ON a.id = s.assignment
                 WHERE a.course = :courseid
                   AND s.latest = :latest
                   AND s.status = :status
                   AND s.userid > 0
                   AND s.groupid = 0
                   AND s.timemodified >= :timestart
                   AND s.timemodified < :timeend
              ORDER BY s.id ASC";

        $params = [
            'courseid' => $courseid,
            'latest' => 1,
            'status' => 'submitted',
            'timestart' => $timestart,
            'timeend' => $timeend,
        ];

        $records = $DB->get_recordset_sql($sql, $params);

        try {
            foreach ($records as $record) {
                $assignid = (int) $record->assignment;
                $userid = (int) $record->userid;
                $key = $assignid . ':' . $userid;

                // Group overrides that actually apply to this user's groups.
                $applicablegroupoverrides = [];
                if (!empty($groupoverridesbyassign[$assignid]) && !empty($usergroups[$userid])) {
                    foreach ($groupoverridesbyassign[$assignid] as $override) {
                        if (isset($usergroups[$userid][$override['groupid']])) {
                            $applicablegroupoverrides[] = [
                                'sortorder' => $override['sortorder'],
                                'duedate' => $override['duedate'],
                            ];
                        }
                    }
                }

                $result = $resolver->resolve(
                    (int) $record->timemodified,
                    (int) $record->duedate,
                    !empty($useroverrideexists[$key]),
                    $useroverrideduedate[$key] ?? null,
                    $applicablegroupoverrides,
                    $extensions[$key] ?? 0
                );

                $summary['submitted']++;
                $summary[$result['classification']]++;

                // Mechanism counters are independent attributes and may overlap:.
                // A submission whose base deadline came from an override and was.
                // Then extended counts under both the override and extended.
                if ($result['useduseroverride']) {
                    $summary['useroverride']++;
                }
                if ($result['usedgroupoverride']) {
                    $summary['groupoverride']++;
                }
                if ($result['usedextension']) {
                    $summary['extended']++;
                }
            }
        } finally {
            $records->close();
        }

        return $summary;
    }

    /**
     * Validate common repository parameters.
     *
     * @param int $courseid Course ID.
     * @param int $timestart Inclusive Unix timestamp.
     * @param int $timeend Exclusive Unix timestamp.
     * @throws \invalid_parameter_exception If a parameter is invalid.
     */
    private function validate_parameters(
        int $courseid,
        int $timestart,
        int $timeend
    ): void {
        if ($courseid <= 0) {
            throw new \invalid_parameter_exception('Course ID must be greater than zero.');
        }

        if ($timestart < 0) {
            throw new \invalid_parameter_exception('Start time must not be negative.');
        }

        if ($timeend <= $timestart) {
            throw new \invalid_parameter_exception('End time must be greater than start time.');
        }
    }

    /**
     * Return the UTC midnight containing a Unix timestamp.
     *
     * @param int $timestamp Unix timestamp.
     * @return int UTC midnight timestamp.
     */
    private static function utc_day_start(int $timestamp): int {
        return intdiv($timestamp, DAYSECS) * DAYSECS;
    }
}
