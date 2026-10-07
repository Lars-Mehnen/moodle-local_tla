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
 * Tests for the course gradebook repository.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla;

use local_tla\repository\course_grade_repository;


/**
 * Tests for course_grade_repository_test.
 * @covers \local_tla\repository\course_grade_repository
 */
final class course_grade_repository_test extends \advanced_testcase {
    /**
     * Fetch the main mod grade item id for an activity instance.
     *
     * @param string $module Module name.
     * @param int $instanceid Module instance id.
     * @param int $courseid Course id.
     * @return int Grade item id.
     */
    private function grade_item_id(string $module, int $instanceid, int $courseid): int {
        global $DB;

        return (int) $DB->get_field('grade_items', 'id', [
            'itemtype' => 'mod',
            'itemmodule' => $module,
            'iteminstance' => $instanceid,
            'itemnumber' => 0,
            'courseid' => $courseid,
        ], MUST_EXIST);
    }

    /**
     * Force a specific numeric range on a grade item.
     *
     * @param int $itemid Grade item id.
     * @param float $grademin Minimum.
     * @param float $grademax Maximum.
     */
    private function set_item_range(int $itemid, float $grademin, float $grademax): void {
        global $DB;

        $DB->set_field('grade_items', 'grademin', $grademin, ['id' => $itemid]);
        $DB->set_field('grade_items', 'grademax', $grademax, ['id' => $itemid]);
    }

    /**
     * Insert a grade_grades row.
     *
     * @param int $itemid Grade item id.
     * @param int $userid User id.
     * @param float|null $finalgrade Final grade or null.
     */
    private function insert_grade(int $itemid, int $userid, ?float $finalgrade): void {
        global $DB;

        $DB->insert_record('grade_grades', (object) [
            'itemid' => $itemid,
            'userid' => $userid,
            'rawgrademin' => 0,
            'rawgrademax' => 100,
            'finalgrade' => $finalgrade,
            'hidden' => 0,
            'locked' => 0,
            'overridden' => 0,
            'excluded' => 0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    /**
     * Add $count grades to an item, one per fresh user.
     *
     * @param int $itemid Grade item id.
     * @param array $grades Grade values.
     */
    private function add_grades(int $itemid, array $grades): void {
        foreach ($grades as $grade) {
            $user = $this->getDataGenerator()->create_user();
            $this->insert_grade($itemid, (int) $user->id, $grade);
        }
    }

    /**
     * Find one activity result by module + instance.
     *
     * @param array $result Repository result.
     * @param string $module Module name.
     * @param int $instanceid Instance id.
     * @return array|null
     */
    private function find_activity(array $result, string $module, int $instanceid): ?array {
        foreach ($result as $activity) {
            if ($activity['module'] === $module && $activity['instanceid'] === $instanceid) {
                return $activity;
            }
        }
        return null;
    }

    public function test_quiz_item_with_valid_grades(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'grade' => 100,
        ]);
        $itemid = $this->grade_item_id('quiz', (int) $quiz->id, (int) $course->id);
        $this->add_grades($itemid, [50.0, 50.0, 50.0, 50.0, 50.0, 50.0, 50.0, 50.0]);

        $result = (new course_grade_repository())
            ->get_activity_score_distributions((int) $course->id, 8);

        $activity = $this->find_activity($result, 'quiz', (int) $quiz->id);
        $this->assertNotNull($activity);
        $this->assertSame(8, $activity['validgrades']);
        $this->assertSame(0, $activity['invalidgrades']);
        $this->assertSame('middle', $activity['analysis']['status']);
    }

    public function test_assign_item_with_valid_grades(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'grade' => 100,
        ]);
        $itemid = $this->grade_item_id('assign', (int) $assign->id, (int) $course->id);
        $this->add_grades($itemid, [95.0, 95.0, 95.0, 95.0, 95.0, 95.0, 95.0, 95.0]);

        $result = (new course_grade_repository())
            ->get_activity_score_distributions((int) $course->id, 8);

        $activity = $this->find_activity($result, 'assign', (int) $assign->id);
        $this->assertNotNull($activity);
        $this->assertSame(8, $activity['validgrades']);
        $this->assertSame('ceiling', $activity['analysis']['status']);
    }

    public function test_null_finalgrade_excluded(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'grade' => 100,
        ]);
        $itemid = $this->grade_item_id('assign', (int) $assign->id, (int) $course->id);
        $this->add_grades($itemid, [50.0, null, 60.0]);

        $result = (new course_grade_repository())
            ->get_activity_score_distributions((int) $course->id, 1);

        $activity = $this->find_activity($result, 'assign', (int) $assign->id);
        $this->assertNotNull($activity);
        $this->assertSame(2, $activity['validgrades']);
        $this->assertSame(0, $activity['invalidgrades']);
    }

    public function test_invalid_range_excluded(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'grade' => 100,
        ]);
        $itemid = $this->grade_item_id('assign', (int) $assign->id, (int) $course->id);
        $this->set_item_range($itemid, 50.0, 50.0);
        $this->add_grades($itemid, [50.0, 50.0]);

        $result = (new course_grade_repository())
            ->get_activity_score_distributions((int) $course->id, 1);

        $this->assertNull($this->find_activity($result, 'assign', (int) $assign->id));
    }

    public function test_normalization_on_shifted_scale(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'grade' => 100,
        ]);
        $itemid = $this->grade_item_id('assign', (int) $assign->id, (int) $course->id);
        // Range 20..70: 20 -> 0%, 45 -> 50%, 70 -> 100%.
        $this->set_item_range($itemid, 20.0, 70.0);
        $this->add_grades($itemid, [20.0, 45.0, 70.0]);

        $result = (new course_grade_repository())
            ->get_activity_score_distributions((int) $course->id, 1);

        $activity = $this->find_activity($result, 'assign', (int) $assign->id);
        $this->assertNotNull($activity);
        $this->assertSame(3, $activity['validgrades']);
        $this->assertEqualsWithDelta(50.0, $activity['analysis']['mean'], 1e-9);
    }

    public function test_value_at_grademin_is_zero_percent(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'grade' => 100,
        ]);
        $itemid = $this->grade_item_id('assign', (int) $assign->id, (int) $course->id);
        $this->set_item_range($itemid, 10.0, 110.0);
        $this->add_grades($itemid, [10.0]);

        $result = (new course_grade_repository())
            ->get_activity_score_distributions((int) $course->id, 1);

        $activity = $this->find_activity($result, 'assign', (int) $assign->id);
        $this->assertNotNull($activity);
        $this->assertEqualsWithDelta(0.0, $activity['analysis']['mean'], 1e-9);
    }

    public function test_value_at_grademax_is_hundred_percent(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'grade' => 100,
        ]);
        $itemid = $this->grade_item_id('assign', (int) $assign->id, (int) $course->id);
        $this->set_item_range($itemid, 10.0, 110.0);
        $this->add_grades($itemid, [110.0]);

        $result = (new course_grade_repository())
            ->get_activity_score_distributions((int) $course->id, 1);

        $activity = $this->find_activity($result, 'assign', (int) $assign->id);
        $this->assertNotNull($activity);
        $this->assertEqualsWithDelta(100.0, $activity['analysis']['mean'], 1e-9);
    }

    public function test_out_of_range_grade_counted_invalid(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'grade' => 100,
        ]);
        $itemid = $this->grade_item_id('assign', (int) $assign->id, (int) $course->id);
        // Grademax 100, a finalgrade of 150 is far out of range -> invalid.
        $this->add_grades($itemid, [50.0, 150.0]);

        $result = (new course_grade_repository())
            ->get_activity_score_distributions((int) $course->id, 1);

        $activity = $this->find_activity($result, 'assign', (int) $assign->id);
        $this->assertNotNull($activity);
        $this->assertSame(1, $activity['validgrades']);
        $this->assertSame(1, $activity['invalidgrades']);
    }

    public function test_course_isolation(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'grade' => 100,
        ]);
        $otherassign = $this->getDataGenerator()->create_module('assign', [
            'course' => $othercourse->id,
            'grade' => 100,
        ]);
        $this->add_grades(
            $this->grade_item_id('assign', (int) $assign->id, (int) $course->id),
            [50.0]
        );
        $this->add_grades(
            $this->grade_item_id('assign', (int) $otherassign->id, (int) $othercourse->id),
            [50.0]
        );

        $result = (new course_grade_repository())
            ->get_activity_score_distributions((int) $course->id, 1);

        $this->assertCount(1, $result);
        $this->assertSame((int) $assign->id, $result[0]['instanceid']);
    }

    public function test_deleted_item_excluded(): void {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'grade' => 100,
        ]);
        $itemid = $this->grade_item_id('assign', (int) $assign->id, (int) $course->id);
        $this->add_grades($itemid, [50.0]);

        // Mark the course module as being deleted.
        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $assign->cmid]);

        $result = (new course_grade_repository())
            ->get_activity_score_distributions((int) $course->id, 1);

        $this->assertNull($this->find_activity($result, 'assign', (int) $assign->id));
    }

    public function test_unsupported_module_excluded(): void {
        $this->resetAfterTest(true);
        // The lesson generator creates content that needs a real (non-guest) user.
        $this->setAdminUser();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'grade' => 100,
        ]);
        $lesson = $this->getDataGenerator()->create_module('lesson', [
            'course' => $course->id,
            'grade' => 100,
        ]);
        $this->add_grades(
            $this->grade_item_id('assign', (int) $assign->id, (int) $course->id),
            [50.0]
        );

        $result = (new course_grade_repository())
            ->get_activity_score_distributions((int) $course->id, 1);

        $this->assertNull($this->find_activity($result, 'lesson', (int) $lesson->id));
        $this->assertNotNull($this->find_activity($result, 'assign', (int) $assign->id));
    }

    public function test_minobservations_respected(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'grade' => 100,
        ]);
        $itemid = $this->grade_item_id('assign', (int) $assign->id, (int) $course->id);
        $this->add_grades($itemid, [50.0, 50.0, 50.0]);

        $result = (new course_grade_repository())
            ->get_activity_score_distributions((int) $course->id, 8);

        $activity = $this->find_activity($result, 'assign', (int) $assign->id);
        $this->assertNotNull($activity);
        $this->assertSame(3, $activity['validgrades']);
        $this->assertSame('unknown', $activity['analysis']['status']);
    }

    public function test_no_n_plus_one_queries(): void {
        global $DB;

        $this->resetAfterTest(true);

        // Small course: 2 graded activities.
        $small = $this->getDataGenerator()->create_course();
        for ($i = 0; $i < 2; $i++) {
            $this->getDataGenerator()->create_module('assign', [
                'course' => $small->id,
                'grade' => 100,
            ]);
        }

        // Larger course: 8 graded activities (mix of assign and quiz).
        $large = $this->getDataGenerator()->create_course();
        for ($i = 0; $i < 5; $i++) {
            $this->getDataGenerator()->create_module('assign', [
                'course' => $large->id,
                'grade' => 100,
            ]);
        }
        for ($i = 0; $i < 3; $i++) {
            $this->getDataGenerator()->create_module('quiz', [
                'course' => $large->id,
                'grade' => 100,
            ]);
        }

        $repository = new course_grade_repository();

        // Warm the modinfo cache so its (constant) build cost is excluded.
        get_fast_modinfo((int) $small->id);
        get_fast_modinfo((int) $large->id);

        $before = $DB->perf_get_reads();
        $repository->get_activity_score_distributions((int) $small->id, 1);
        $smallreads = $DB->perf_get_reads() - $before;

        $before = $DB->perf_get_reads();
        $repository->get_activity_score_distributions((int) $large->id, 1);
        $largereads = $DB->perf_get_reads() - $before;

        // The read count must not grow with the number of activities: the data.
        // Is loaded in a fixed, bundled set of queries (no per-activity lookup).
        $this->assertSame($smallreads, $largereads);
        $this->assertLessThanOrEqual(4, $largereads);
    }

    public function test_no_personal_data_returned(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'grade' => 100,
        ]);
        $itemid = $this->grade_item_id('assign', (int) $assign->id, (int) $course->id);
        $this->add_grades($itemid, [50.0, 60.0]);

        $result = (new course_grade_repository())
            ->get_activity_score_distributions((int) $course->id, 1);

        $activity = $this->find_activity($result, 'assign', (int) $assign->id);
        $this->assertNotNull($activity);
        $this->assertEqualsCanonicalizing(
            ['cmid', 'itemid', 'module', 'instanceid', 'name', 'grademin', 'grademax',
             'validgrades', 'invalidgrades', 'analysis'],
            array_keys($activity)
        );
        $this->assertArrayNotHasKey('userid', $activity);
        $this->assertArrayNotHasKey('userid', $activity['analysis']);
    }

    // Analysis B: course progress.

    /**
     * Create a graded assignment with a due date and return [module, itemid].
     *
     * @param int $courseid Course id.
     * @param int $duedate Due date timestamp (for ordering).
     * @return array
     */
    private function make_graded_assign(int $courseid, int $duedate): array {
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $courseid,
            'grade' => 100,
            'duedate' => $duedate,
        ]);
        $itemid = $this->grade_item_id('assign', (int) $assign->id, $courseid);
        return [$assign, $itemid];
    }

    public function test_course_progress_two_activities(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        [, $item1] = $this->make_graded_assign((int) $course->id, 1000);
        [, $item2] = $this->make_graded_assign((int) $course->id, 2000);
        $user = $this->getDataGenerator()->create_user();
        $this->insert_grade($item1, (int) $user->id, 40.0);
        $this->insert_grade($item2, (int) $user->id, 70.0);

        $result = (new course_grade_repository())
            ->get_course_progress((int) $course->id, 1);

        $this->assertSame(2, $result['activities']);
        $this->assertSame(1, $result['participants']);
        $this->assertSame('improving', $result['status']);
    }

    public function test_course_progress_multiple_activities(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        [, $item1] = $this->make_graded_assign((int) $course->id, 1000);
        [, $item2] = $this->make_graded_assign((int) $course->id, 2000);
        [, $item3] = $this->make_graded_assign((int) $course->id, 3000);
        $user = $this->getDataGenerator()->create_user();
        $this->insert_grade($item1, (int) $user->id, 40.0);
        $this->insert_grade($item2, (int) $user->id, 60.0);
        $this->insert_grade($item3, (int) $user->id, 80.0);

        $result = (new course_grade_repository())
            ->get_course_progress((int) $course->id, 1);

        $this->assertSame(3, $result['activities']);
        $this->assertSame(1, $result['participants']);
    }

    public function test_course_progress_single_grade_excluded(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        [, $item1] = $this->make_graded_assign((int) $course->id, 1000);
        $this->make_graded_assign((int) $course->id, 2000);
        $user = $this->getDataGenerator()->create_user();
        $this->insert_grade($item1, (int) $user->id, 40.0);

        $result = (new course_grade_repository())
            ->get_course_progress((int) $course->id, 1);

        $this->assertSame(0, $result['participants']);
        $this->assertSame('unknown', $result['status']);
    }

    public function test_course_progress_first_last_ordered_by_deadline(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        // Later-deadline activity is created first to prove ordering uses the.
        // Deadline, not creation order.
        [, $late] = $this->make_graded_assign((int) $course->id, 2000);
        [, $early] = $this->make_graded_assign((int) $course->id, 1000);
        $user = $this->getDataGenerator()->create_user();
        $this->insert_grade($early, (int) $user->id, 40.0);
        $this->insert_grade($late, (int) $user->id, 70.0);

        $result = (new course_grade_repository())
            ->get_course_progress((int) $course->id, 1);

        $this->assertSame(1, $result['participants']);
        $this->assertEqualsWithDelta(30.0, $result['medianchange'], 1e-9);
    }

    public function test_course_progress_null_grade_excluded(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        [, $item1] = $this->make_graded_assign((int) $course->id, 1000);
        [, $item2] = $this->make_graded_assign((int) $course->id, 2000);
        $user = $this->getDataGenerator()->create_user();
        $this->insert_grade($item1, (int) $user->id, 40.0);
        $this->insert_grade($item2, (int) $user->id, null);

        $result = (new course_grade_repository())
            ->get_course_progress((int) $course->id, 1);

        $this->assertSame(0, $result['participants']);
    }

    public function test_course_progress_normalized_percentages(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        [, $item1] = $this->make_graded_assign((int) $course->id, 1000);
        [, $item2] = $this->make_graded_assign((int) $course->id, 2000);
        // Shift item1 to range 20..70: a raw 45 becomes 50%.
        $this->set_item_range($item1, 20.0, 70.0);
        $user = $this->getDataGenerator()->create_user();
        $this->insert_grade($item1, (int) $user->id, 45.0);
        $this->insert_grade($item2, (int) $user->id, 60.0);

        $result = (new course_grade_repository())
            ->get_course_progress((int) $course->id, 1);

        $this->assertSame(1, $result['participants']);
        // First 50%, last 60% -> +10.
        $this->assertEqualsWithDelta(10.0, $result['medianchange'], 1e-9);
    }

    public function test_course_progress_course_isolation(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        [, $item1] = $this->make_graded_assign((int) $course->id, 1000);
        [, $item2] = $this->make_graded_assign((int) $course->id, 2000);
        [, $otheritem1] = $this->make_graded_assign((int) $othercourse->id, 1000);
        [, $otheritem2] = $this->make_graded_assign((int) $othercourse->id, 2000);
        $user = $this->getDataGenerator()->create_user();
        $this->insert_grade($item1, (int) $user->id, 40.0);
        $this->insert_grade($item2, (int) $user->id, 70.0);
        $this->insert_grade($otheritem1, (int) $user->id, 10.0);
        $this->insert_grade($otheritem2, (int) $user->id, 90.0);

        $result = (new course_grade_repository())
            ->get_course_progress((int) $course->id, 1);

        $this->assertSame(2, $result['activities']);
        $this->assertSame(1, $result['participants']);
        $this->assertEqualsWithDelta(30.0, $result['medianchange'], 1e-9);
    }

    public function test_course_progress_different_participant_sets(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        [, $item1] = $this->make_graded_assign((int) $course->id, 1000);
        [, $item2] = $this->make_graded_assign((int) $course->id, 2000);
        $both = $this->getDataGenerator()->create_user();
        $onlyone = $this->getDataGenerator()->create_user();
        $this->insert_grade($item1, (int) $both->id, 40.0);
        $this->insert_grade($item2, (int) $both->id, 70.0);
        $this->insert_grade($item1, (int) $onlyone->id, 50.0);

        $result = (new course_grade_repository())
            ->get_course_progress((int) $course->id, 1);

        // Only the student graded in both activities is counted.
        $this->assertSame(1, $result['participants']);
    }

    public function test_course_progress_too_few_is_unknown(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        [, $item1] = $this->make_graded_assign((int) $course->id, 1000);
        [, $item2] = $this->make_graded_assign((int) $course->id, 2000);
        $user = $this->getDataGenerator()->create_user();
        $this->insert_grade($item1, (int) $user->id, 40.0);
        $this->insert_grade($item2, (int) $user->id, 70.0);

        $result = (new course_grade_repository())
            ->get_course_progress((int) $course->id, 8);

        $this->assertSame(1, $result['participants']);
        $this->assertSame('unknown', $result['status']);
    }

    public function test_course_progress_improvement_is_cautious_yellow(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        [, $item1] = $this->make_graded_assign((int) $course->id, 1000);
        [, $item2] = $this->make_graded_assign((int) $course->id, 2000);
        $user = $this->getDataGenerator()->create_user();
        $this->insert_grade($item1, (int) $user->id, 40.0);
        $this->insert_grade($item2, (int) $user->id, 70.0);

        $result = (new course_grade_repository())
            ->get_course_progress((int) $course->id, 1);

        // A computed course-level improvement is only a context hint: the status.
        // Is "improving" but the severity is capped at yellow, never green.
        $this->assertSame('improving', $result['status']);
        $this->assertSame('yellow', $result['severity']);
    }

    public function test_course_progress_decline_is_cautious_yellow(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        [, $item1] = $this->make_graded_assign((int) $course->id, 1000);
        [, $item2] = $this->make_graded_assign((int) $course->id, 2000);
        $user = $this->getDataGenerator()->create_user();
        $this->insert_grade($item1, (int) $user->id, 70.0);
        $this->insert_grade($item2, (int) $user->id, 40.0);

        $result = (new course_grade_repository())
            ->get_course_progress((int) $course->id, 1);

        // A computed decline across different activities is capped at yellow,.
        // Because differing difficulty can also cause an apparent drop.
        $this->assertSame('declining', $result['status']);
        $this->assertSame('yellow', $result['severity']);
    }

    public function test_course_progress_no_personal_data(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        [, $item1] = $this->make_graded_assign((int) $course->id, 1000);
        [, $item2] = $this->make_graded_assign((int) $course->id, 2000);
        $user = $this->getDataGenerator()->create_user();
        $this->insert_grade($item1, (int) $user->id, 40.0);
        $this->insert_grade($item2, (int) $user->id, 70.0);

        $result = (new course_grade_repository())
            ->get_course_progress((int) $course->id, 1);

        $this->assertEqualsCanonicalizing(
            ['activities', 'participants', 'status', 'severity', 'medianchange',
             'improvedshare', 'stableshare', 'declinedshare', 'warning'],
            array_keys($result)
        );
        $this->assertArrayNotHasKey('userid', $result);
    }
}
