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
 * Read-only data access for the U-shape origin analysis.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\repository;

use local_tla\indicator\ushape_origin_analyzer;

/**
 * Load the chronological grade timeline, the pass threshold and daily activity
 * of one course in three bundled queries (no per-student queries), and run the
 * pure ushape_origin_analyzer on it.
 */
final class course_ushape_origin_repository {
    /** @var string[] Activity modules with comparable numeric grades. */
    private const SUPPORTED_MODULES = ['assign', 'quiz'];

    /** @var float Tolerance when normalising grades to percentages. */
    private const NORMALIZE_TOLERANCE = 0.0001;

    /** @var float Fallback pass share when neither gradebook nor setting define one. */
    public const DEFAULT_PASS_SHARE = 0.5;

    /**
     * Return the U-shape origin analysis of one course.
     *
     * Ordering of activities: assignment due date / quiz close date; activities
     * without such a date fall back to their creation time (counted in
     * nodeadlineitems). Pass threshold: course-total "grade to pass" relative to
     * its range; when it is not set, the configured fallback share is used.
     *
     * @param int $courseid Course ID.
     * @param int $minobservations Minimum participants.
     * @param int|null $now Reference time (default: current time).
     * @return array Analyzer result plus passsource ('gradebook' | 'setting').
     * @throws \invalid_parameter_exception If a parameter is invalid.
     */
    public function get_ushape_origin(int $courseid, int $minobservations, ?int $now = null): array {
        global $DB, $CFG;

        if ($courseid <= 0) {
            throw new \invalid_parameter_exception('Course ID must be greater than zero.');
        }
        if ($minobservations < 1) {
            throw new \invalid_parameter_exception('Minimum observations must be positive.');
        }
        $now = $now ?? time();

        require_once($CFG->libdir . '/grade/constants.php');

        [$moduleinsql, $moduleparams] = $DB->get_in_or_equal(
            self::SUPPORTED_MODULES,
            SQL_PARAMS_NAMED,
            'mod'
        );

        $sql = "SELECT gi.id AS itemid,
                       gi.itemmodule AS module,
                       gi.grademin AS grademin,
                       gi.grademax AS grademax,
                       asg.duedate AS assignduedate,
                       qz.timeclose AS quiztimeclose,
                       cm.added AS cmadded,
                       cs.section AS sectionnum,
                       cs.name AS sectionname,
                       COALESCE(asg.name, qz.name) AS name
                  FROM {grade_items} gi
                  JOIN {modules} m ON m.name = gi.itemmodule
                  JOIN {course_modules} cm
                    ON cm.course = gi.courseid
                   AND cm.module = m.id
                   AND cm.instance = gi.iteminstance
             LEFT JOIN {course_sections} cs ON cs.id = cm.section
             LEFT JOIN {assign} asg
                    ON gi.itemmodule = 'assign' AND asg.id = gi.iteminstance
             LEFT JOIN {quiz} qz
                    ON gi.itemmodule = 'quiz' AND qz.id = gi.iteminstance
                 WHERE gi.courseid = :courseid
                   AND gi.itemtype = :itemtype
                   AND gi.itemnumber = 0
                   AND gi.gradetype = :gradetype
                   AND gi.grademax > gi.grademin
                   AND cm.deletioninprogress = 0
                   AND gi.itemmodule {$moduleinsql}
              ORDER BY gi.id ASC";

        $records = $DB->get_records_sql($sql, array_merge($moduleparams, [
            'courseid' => $courseid,
            'itemtype' => 'mod',
            'gradetype' => GRADE_TYPE_VALUE,
        ]));

        $items = [];
        $ranges = [];
        foreach ($records as $record) {
            $deadline = $record->module === 'assign'
                ? (int) $record->assignduedate
                : (int) $record->quiztimeclose;
            $itemid = (int) $record->itemid;
            $section = trim((string) ($record->sectionname ?? ''));
            if ($section === '' && $record->sectionnum !== null) {
                $section = '#' . (int) $record->sectionnum;
            }
            $items[] = [
                'itemid' => $itemid,
                'ordertime' => $deadline > 0 ? $deadline : (int) $record->cmadded,
                'maxpoints' => (float) $record->grademax - (float) $record->grademin,
                'name' => (string) $record->name,
                'section' => $section,
                'hasdeadline' => $deadline > 0,
            ];
            $ranges[$itemid] = [(float) $record->grademin, (float) $record->grademax];
        }

        [$passshare, $passsource] = $this->pass_share($courseid);

        $grades = [];
        if ($ranges) {
            [$insql, $params] = $DB->get_in_or_equal(array_keys($ranges), SQL_PARAMS_NAMED, 'item');
            $rs = $DB->get_recordset_sql(
                "SELECT id, itemid, userid, finalgrade
                   FROM {grade_grades}
                  WHERE itemid {$insql}
                    AND finalgrade IS NOT NULL
               ORDER BY itemid ASC, id ASC",
                $params
            );
            try {
                foreach ($rs as $record) {
                    [$min, $max] = $ranges[(int) $record->itemid];
                    $pct = self::normalize_percentage((float) $record->finalgrade, $min, $max);
                    if ($pct !== null) {
                        $grades[(int) $record->userid][(int) $record->itemid] = $pct;
                    }
                }
            } finally {
                $rs->close();
            }
        }

        $activity = $this->daily_activity($courseid, $items, $grades, $now);

        $result = (new ushape_origin_analyzer())->analyze(
            $items,
            $grades,
            $activity,
            $passshare,
            $now,
            $minobservations
        );
        $result['passsource'] = $passsource;
        return $result;
    }

    /**
     * Pass share from the course total, otherwise from the plugin setting.
     *
     * @param int $courseid Course ID.
     * @return array{0: float, 1: string} Share in (0, 1] and its source.
     */
    private function pass_share(int $courseid): array {
        global $DB;

        $course = $DB->get_record(
            'grade_items',
            ['courseid' => $courseid, 'itemtype' => 'course'],
            'id, grademin, grademax, gradepass',
            IGNORE_MULTIPLE
        );
        if ($course) {
            $range = (float) $course->grademax - (float) $course->grademin;
            $pass = (float) $course->gradepass - (float) $course->grademin;
            if ($range > 0.0 && (float) $course->gradepass > 0.0 && $pass > 0.0 && $pass <= $range) {
                return [$pass / $range, 'gradebook'];
            }
        }
        $config = get_config('local_tla', 'ushape_pass_share');
        $share = $config === false || $config === '' ? self::DEFAULT_PASS_SHARE : (float) $config;
        if (!is_finite($share) || $share <= 0.0 || $share > 1.0) {
            $share = self::DEFAULT_PASS_SHARE;
        }
        return [$share, 'setting'];
    }

    /**
     * Daily event counts of graded participants, aggregated in the database.
     *
     * @param int $courseid Course ID.
     * @param array $items Activities.
     * @param array $grades Grades keyed by user.
     * @param int $now Reference time.
     * @return array [userid => [daystart => count]]
     */
    private function daily_activity(int $courseid, array $items, array $grades, int $now): array {
        global $DB;

        $due = array_filter($items, static fn(array $i): bool => $i['ordertime'] <= $now);
        if (!$due || !$grades) {
            return [];
        }
        $times = array_column($due, 'ordertime');
        $start = min($times) - ushape_origin_analyzer::ACTIVITY_LEAD_DAYS * DAYSECS;
        $end = max($times);
        if ($end <= $start) {
            return [];
        }

        $rs = $DB->get_recordset_sql(
            "SELECT userid, FLOOR(timecreated / 86400) AS daynum, COUNT(1) AS eventcount
               FROM {logstore_standard_log}
              WHERE courseid = :courseid
                AND userid > 0
                AND anonymous = 0
                AND timecreated >= :timestart
                AND timecreated < :timeend
           GROUP BY userid, FLOOR(timecreated / 86400)",
            ['courseid' => $courseid, 'timestart' => $start, 'timeend' => $end]
        );
        $activity = [];
        try {
            foreach ($rs as $record) {
                $userid = (int) $record->userid;
                if (!isset($grades[$userid])) {
                    continue;
                }
                $activity[$userid][(int) $record->daynum * 86400] = (int) $record->eventcount;
            }
        } finally {
            $rs->close();
        }
        // Graded participants without any logged event count as inactive.
        foreach (array_keys($grades) as $userid) {
            $activity[$userid] = $activity[$userid] ?? [];
        }
        return $activity;
    }

    /**
     * Normalise a final grade to [0, 100].
     *
     * @param float $finalgrade Final grade.
     * @param float $grademin Item minimum.
     * @param float $grademax Item maximum.
     * @return float|null Percentage, or null if invalid.
     */
    private static function normalize_percentage(float $finalgrade, float $grademin, float $grademax): ?float {
        $range = $grademax - $grademin;
        if (!is_finite($finalgrade) || $range <= 0.0) {
            return null;
        }
        $pct = 100.0 * ($finalgrade - $grademin) / $range;
        if (!is_finite($pct) || $pct < -self::NORMALIZE_TOLERANCE || $pct > 100.0 + self::NORMALIZE_TOLERANCE) {
            return null;
        }
        return max(0.0, min(100.0, $pct));
    }
}
