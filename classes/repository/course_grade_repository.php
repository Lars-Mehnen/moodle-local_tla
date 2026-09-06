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
 * Read-only course gradebook repository.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\repository;

use local_tla\indicator\score_distribution_analyzer;
use local_tla\indicator\attempt_progress_analyzer;

defined('MOODLE_INTERNAL') || die();

/**
 * Read-only access to gradebook-based score distributions.
 *
 * The gradebook ({grade_items} + {grade_grades}) is used as the single source
 * of truth rather than module tables such as {quiz_grades} / {assign_grades}:
 * it holds the final teacher-facing value (including manual overrides), and it
 * lets quiz and assignment be processed uniformly.
 *
 * Only main activity grade items are considered:
 *   itemtype = 'mod', itemmodule IN ('assign','quiz'), itemnumber = 0,
 *   gradetype = GRADE_TYPE_VALUE (numeric; scales and text are excluded),
 *   grademax > grademin, and a live course module (deletioninprogress = 0).
 *
 * grade_items has no "deleted" column in Moodle; a deleted activity is
 * represented by the absence of a matching {course_modules} row, so the inner
 * join naturally excludes it.
 *
 * Hidden activities / grade items are included: this data is only ever exposed
 * behind the local/tla:view capability at the dashboard boundary, never in a
 * public context, and teachers may legitimately want to inspect them.
 *
 * No personal data is returned: only aggregated, anonymous distribution values.
 * Everything is loaded in a fixed, bundled set of queries (one for the grade
 * items incl. activity names, one for the grades) so the query count never
 * grows with the number of activities.
 */
final class course_grade_repository {
    /** @var string[] Supported activity modules. */
    private const SUPPORTED_MODULES = ['assign', 'quiz'];

    /**
     * Rounding tolerance (in percentage points) for normalised grades.
     *
     * A normalised value within [-tolerance, 100 + tolerance] is treated as a
     * valid grade and clamped to the [0, 100] bounds; anything further outside
     * is counted as an invalid data-quality case and excluded from the shape
     * analysis rather than silently clamped.
     */
    private const NORMALIZE_TOLERANCE = 0.0001;

    /**
     * Return per-activity score distributions for a course.
     *
     * The distribution describes existing grades, not automatically every
     * enrolled student: users without a grade are not counted, and no
     * not-submitted-as-zero assumption is made.
     *
     * @param int $courseid Course ID.
     * @param int $minobservations Minimum valid grades for a classification.
     * @return array<int, array{
     *     cmid: int,
     *     itemid: int,
     *     module: string,
     *     instanceid: int,
     *     name: string,
     *     grademin: float,
     *     grademax: float,
     *     validgrades: int,
     *     invalidgrades: int,
     *     analysis: array
     * }>
     * @throws \invalid_parameter_exception If a parameter is invalid.
     */
    public function get_activity_score_distributions(
        int $courseid,
        int $minobservations
    ): array {
        global $DB, $CFG;

        if ($courseid <= 0) {
            throw new \invalid_parameter_exception('Course ID must be greater than zero.');
        }
        if ($minobservations < 1) {
            throw new \invalid_parameter_exception('Minimum observations must be positive.');
        }

        // Ensure the gradebook type constants are available (GRADE_TYPE_VALUE).
        require_once($CFG->libdir . '/grade/constants.php');

        [$moduleinsql, $moduleparams] = $DB->get_in_or_equal(
            self::SUPPORTED_MODULES,
            SQL_PARAMS_NAMED,
            'mod'
        );

        // Grade items joined to their live course module, with the activity
        // name resolved in the same query via module-specific left joins (one
        // per supported module). No per-item queries and no modinfo lookups.
        $sql = "SELECT gi.id AS itemid,
                       gi.itemmodule AS module,
                       gi.iteminstance AS instanceid,
                       gi.grademin AS grademin,
                       gi.grademax AS grademax,
                       cm.id AS cmid,
                       COALESCE(asg.name, qz.name) AS name
                  FROM {grade_items} gi
                  JOIN {modules} m ON m.name = gi.itemmodule
                  JOIN {course_modules} cm
                    ON cm.course = gi.courseid
                   AND cm.module = m.id
                   AND cm.instance = gi.iteminstance
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

        $params = array_merge($moduleparams, [
            'courseid' => $courseid,
            'itemtype' => 'mod',
            'gradetype' => GRADE_TYPE_VALUE,
        ]);

        $items = $DB->get_records_sql($sql, $params);

        if (empty($items)) {
            return [];
        }

        // Bundle-load every relevant grade in a single query.
        $itemids = array_keys($items);
        [$gradeinsql, $gradeparams] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED, 'item');

        $gradesbyitem = [];
        $graderecords = $DB->get_recordset_sql(
            "SELECT id, itemid, finalgrade
               FROM {grade_grades}
              WHERE itemid {$gradeinsql}
                AND finalgrade IS NOT NULL
           ORDER BY itemid ASC, id ASC",
            $gradeparams
        );
        try {
            foreach ($graderecords as $record) {
                $gradesbyitem[(int) $record->itemid][] = (float) $record->finalgrade;
            }
        } finally {
            $graderecords->close();
        }

        $analyzer = new score_distribution_analyzer();
        $result = [];

        foreach ($items as $item) {
            $itemid = (int) $item->itemid;
            $grademin = (float) $item->grademin;
            $grademax = (float) $item->grademax;
            $range = $grademax - $grademin;
            $cmid = (int) $item->cmid;

            $valid = [];
            $invalid = 0;
            foreach ($gradesbyitem[$itemid] ?? [] as $finalgrade) {
                if (!is_finite($finalgrade) || $range <= 0.0) {
                    $invalid++;
                    continue;
                }

                $percentage = 100.0 * ($finalgrade - $grademin) / $range;

                if (
                    !is_finite($percentage) ||
                    $percentage < -self::NORMALIZE_TOLERANCE ||
                    $percentage > 100.0 + self::NORMALIZE_TOLERANCE
                ) {
                    $invalid++;
                    continue;
                }

                // Clamp tiny rounding excursions back onto the [0, 100] bounds.
                if ($percentage < 0.0) {
                    $percentage = 0.0;
                } else if ($percentage > 100.0) {
                    $percentage = 100.0;
                }

                $valid[] = $percentage;
            }

            $result[] = [
                'cmid' => $cmid,
                'itemid' => $itemid,
                'module' => (string) $item->module,
                'instanceid' => (int) $item->instanceid,
                'name' => (string) ($item->name ?? ''),
                'grademin' => $grademin,
                'grademax' => $grademax,
                'validgrades' => count($valid),
                'invalidgrades' => $invalid,
                'analysis' => $analyzer->analyze($valid, $minobservations),
            ];
        }

        return $result;
    }

    /**
     * Return a conservative course-level progress summary.
     *
     * For each student it compares their normalised grade on the earliest and
     * the latest graded activity they have a valid grade for; only students
     * with at least two valid grades count. Activities are ordered by a
     * meaningful deadline (assign due date / quiz close time) and, where that is
     * not set, by the course module creation time (a documented weaker signal).
     *
     * This is only a hint: activities can differ in difficulty and learning
     * objective, so the returned "warning" must always be surfaced.
     *
     * @param int $courseid Course ID.
     * @param int $minobservations Minimum students (with >= 2 grades) for a verdict.
     * @return array{
     *     activities: int,
     *     participants: int,
     *     status: string,
     *     severity: string,
     *     medianchange: float|null,
     *     improvedshare: float|null,
     *     stableshare: float|null,
     *     declinedshare: float|null,
     *     warning: string
     * }
     * @throws \invalid_parameter_exception If a parameter is invalid.
     */
    public function get_course_progress(int $courseid, int $minobservations): array {
        global $DB, $CFG;

        if ($courseid <= 0) {
            throw new \invalid_parameter_exception('Course ID must be greater than zero.');
        }
        if ($minobservations < 1) {
            throw new \invalid_parameter_exception('Minimum observations must be positive.');
        }

        require_once($CFG->libdir . '/grade/constants.php');

        [$moduleinsql, $moduleparams] = $DB->get_in_or_equal(
            self::SUPPORTED_MODULES,
            SQL_PARAMS_NAMED,
            'mod'
        );

        // Comparable graded items with an ordering time, bundled in one query.
        $sql = "SELECT gi.id AS itemid,
                       gi.itemmodule AS module,
                       gi.grademin AS grademin,
                       gi.grademax AS grademax,
                       asg.duedate AS assignduedate,
                       qz.timeclose AS quiztimeclose,
                       cm.added AS cmadded
                  FROM {grade_items} gi
                  JOIN {modules} m ON m.name = gi.itemmodule
                  JOIN {course_modules} cm
                    ON cm.course = gi.courseid
                   AND cm.module = m.id
                   AND cm.instance = gi.iteminstance
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

        $items = $DB->get_records_sql($sql, array_merge($moduleparams, [
            'courseid' => $courseid,
            'itemtype' => 'mod',
            'gradetype' => GRADE_TYPE_VALUE,
        ]));

        $emptyresult = [
            'activities' => count($items),
            'participants' => 0,
            'status' => 'unknown',
            'severity' => 'unknown',
            'medianchange' => null,
            'improvedshare' => null,
            'stableshare' => null,
            'declinedshare' => null,
            'warning' => 'Activities may differ in difficulty and learning objectives.',
        ];

        if (count($items) < 2) {
            return $emptyresult;
        }

        // Per item: ordering time and grade range.
        $itemorder = [];
        $itemrange = [];
        foreach ($items as $item) {
            $deadline = $item->module === 'assign'
                ? (int) $item->assignduedate
                : (int) $item->quiztimeclose;
            $itemorder[(int) $item->itemid] = $deadline > 0
                ? $deadline
                : (int) $item->cmadded;
            $itemrange[(int) $item->itemid] = [
                'min' => (float) $item->grademin,
                'max' => (float) $item->grademax,
            ];
        }

        // Bundle-load all grades for these items.
        $itemids = array_keys($itemrange);
        [$gradeinsql, $gradeparams] = $DB->get_in_or_equal($itemids, SQL_PARAMS_NAMED, 'item');

        $studentgrades = [];
        $graderecords = $DB->get_recordset_sql(
            "SELECT id, itemid, userid, finalgrade
               FROM {grade_grades}
              WHERE itemid {$gradeinsql}
                AND finalgrade IS NOT NULL
           ORDER BY itemid ASC, id ASC",
            $gradeparams
        );
        try {
            foreach ($graderecords as $record) {
                $itemid = (int) $record->itemid;
                $range = $itemrange[$itemid];
                $percentage = self::normalize_percentage(
                    (float) $record->finalgrade,
                    $range['min'],
                    $range['max']
                );
                if ($percentage === null) {
                    continue;
                }
                $studentgrades[(int) $record->userid][] = [
                    'ordertime' => $itemorder[$itemid],
                    'itemid' => $itemid,
                    'percentage' => $percentage,
                ];
            }
        } finally {
            $graderecords->close();
        }

        // Per student: first vs last graded activity by ordering time.
        $participants = [];
        foreach ($studentgrades as $grades) {
            if (count($grades) < 2) {
                continue;
            }
            usort($grades, static function (array $a, array $b): int {
                return [$a['ordertime'], $a['itemid']] <=> [$b['ordertime'], $b['itemid']];
            });
            $first = $grades[0]['percentage'];
            $last = $grades[count($grades) - 1]['percentage'];
            $participants[] = [
                'first' => $first,
                'last' => $last,
                'best' => max($first, $last),
                'attempts' => 0,
            ];
        }

        $analysis = (new attempt_progress_analyzer())->analyze($participants, $minobservations);

        return [
            'activities' => count($items),
            'participants' => count($participants),
            'status' => $analysis['status'],
            'severity' => self::course_severity($analysis['status']),
            'medianchange' => $analysis['medianchange'],
            'improvedshare' => $analysis['improvedshare'],
            'stableshare' => $analysis['stableshare'],
            'declinedshare' => $analysis['declinedshare'],
            'warning' => 'Activities may differ in difficulty and learning objectives.',
        ];
    }

    /**
     * Map a course-progress status to a cautious severity.
     *
     * Course-level changes compare different activities and therefore use a
     * cautious, non-green severity mapping: a computed improvement is only a
     * context hint, not evidence of teaching quality, so it never earns green.
     * A computed decline is capped at yellow rather than red, because differing
     * activity difficulty can equally produce an apparent drop. Only the
     * "unknown" (too little data) state stays unknown.
     *
     * @param string $status Progress status.
     * @return string Severity (unknown or yellow).
     */
    private static function course_severity(string $status): string {
        return $status === 'unknown' ? 'unknown' : 'yellow';
    }

    /**
     * Normalise a raw grade to a percentage of its item range.
     *
     * @param float $finalgrade Raw final grade.
     * @param float $grademin Item minimum.
     * @param float $grademax Item maximum.
     * @return float|null Percentage in [0, 100], or null if invalid.
     */
    private static function normalize_percentage(
        float $finalgrade,
        float $grademin,
        float $grademax
    ): ?float {
        $range = $grademax - $grademin;
        if (!is_finite($finalgrade) || $range <= 0.0) {
            return null;
        }

        $percentage = 100.0 * ($finalgrade - $grademin) / $range;

        if (
            !is_finite($percentage) ||
            $percentage < -self::NORMALIZE_TOLERANCE ||
            $percentage > 100.0 + self::NORMALIZE_TOLERANCE
        ) {
            return null;
        }

        if ($percentage < 0.0) {
            return 0.0;
        }
        if ($percentage > 100.0) {
            return 100.0;
        }
        return $percentage;
    }
}
