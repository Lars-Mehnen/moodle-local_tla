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
 * Tests for the course activity repository.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla;

use local_tla\repository\course_activity_repository;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for course_activity_repository_test.
 */
#[CoversClass(course_activity_repository::class)]
final class course_activity_repository_test extends \advanced_testcase {
    public function test_invalid_parameters(): void {
        $repository = new course_activity_repository();

        $this->expectException(\invalid_parameter_exception::class);
        $repository->get_events_per_day(0, 0, DAYSECS);
    }

    public function test_empty_result(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $repository = new course_activity_repository();

        $result = $repository->get_events_per_day(
            (int) $course->id,
            self::day_start(),
            self::day_start() + DAYSECS
        );

        $this->assertSame([], $result);
    }

    public function test_multiple_events_on_one_day(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $daystart = self::day_start();

        $this->insert_log_record((int) $course->id, (int) $user->id, $daystart + 60);
        $this->insert_log_record((int) $course->id, (int) $user->id, $daystart + 3600);
        $this->insert_log_record((int) $course->id, (int) $user->id, $daystart + 7200);

        $repository = new course_activity_repository();
        $result = $repository->get_events_per_day(
            (int) $course->id,
            $daystart,
            $daystart + DAYSECS
        );

        $this->assertSame([
            ['daystart' => $daystart, 'eventcount' => 3],
        ], $result);
    }

    public function test_events_on_two_days(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $daystart = self::day_start();

        $this->insert_log_record((int) $course->id, (int) $user->id, $daystart + DAYSECS + 120);
        $this->insert_log_record((int) $course->id, (int) $user->id, $daystart + 120);
        $this->insert_log_record((int) $course->id, (int) $user->id, $daystart + DAYSECS + 240);

        $repository = new course_activity_repository();
        $result = $repository->get_events_per_day(
            (int) $course->id,
            $daystart,
            $daystart + (2 * DAYSECS)
        );

        $this->assertSame([
            ['daystart' => $daystart, 'eventcount' => 1],
            ['daystart' => $daystart + DAYSECS, 'eventcount' => 2],
        ], $result);
    }

    public function test_other_courses_are_excluded(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $daystart = self::day_start();

        $this->insert_log_record((int) $course->id, (int) $user->id, $daystart + 60);
        $this->insert_log_record((int) $othercourse->id, (int) $user->id, $daystart + 120);

        $repository = new course_activity_repository();
        $result = $repository->get_events_per_day(
            (int) $course->id,
            $daystart,
            $daystart + DAYSECS
        );

        $this->assertSame([
            ['daystart' => $daystart, 'eventcount' => 1],
        ], $result);
    }

    public function test_time_boundaries(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $daystart = self::day_start();
        $timeend = $daystart + DAYSECS;

        $this->insert_log_record((int) $course->id, (int) $user->id, $daystart);
        $this->insert_log_record((int) $course->id, (int) $user->id, $timeend - 1);
        $this->insert_log_record((int) $course->id, (int) $user->id, $timeend);

        $repository = new course_activity_repository();
        $result = $repository->get_events_per_day(
            (int) $course->id,
            $daystart,
            $timeend
        );

        $this->assertSame([
            ['daystart' => $daystart, 'eventcount' => 2],
        ], $result);
    }

    public function test_active_users_count_distinct_users_per_day(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $daystart = self::day_start();

        $this->insert_log_record((int) $course->id, (int) $user1->id, $daystart + 60);
        $this->insert_log_record((int) $course->id, (int) $user1->id, $daystart + 120);
        $this->insert_log_record((int) $course->id, (int) $user2->id, $daystart + 180);

        $repository = new course_activity_repository();
        $result = $repository->get_active_users_per_day(
            (int) $course->id,
            $daystart,
            $daystart + DAYSECS
        );

        $this->assertSame([
            ['daystart' => $daystart, 'activeusers' => 2],
        ], $result);
    }

    public function test_active_users_are_grouped_by_day(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $daystart = self::day_start();

        $this->insert_log_record((int) $course->id, (int) $user1->id, $daystart + 60);
        $this->insert_log_record((int) $course->id, (int) $user1->id, $daystart + DAYSECS + 60);
        $this->insert_log_record((int) $course->id, (int) $user2->id, $daystart + DAYSECS + 120);

        $repository = new course_activity_repository();
        $result = $repository->get_active_users_per_day(
            (int) $course->id,
            $daystart,
            $daystart + (2 * DAYSECS)
        );

        $this->assertSame([
            ['daystart' => $daystart, 'activeusers' => 1],
            ['daystart' => $daystart + DAYSECS, 'activeusers' => 2],
        ], $result);
    }

    public function test_active_users_exclude_anonymous_and_other_courses(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $daystart = self::day_start();

        $this->insert_log_record((int) $course->id, (int) $user->id, $daystart + 60);
        $this->insert_log_record((int) $course->id, 0, $daystart + 120);
        $this->insert_log_record((int) $othercourse->id, (int) $user->id, $daystart + 180);

        $repository = new course_activity_repository();
        $result = $repository->get_active_users_per_day(
            (int) $course->id,
            $daystart,
            $daystart + DAYSECS
        );

        $this->assertSame([
            ['daystart' => $daystart, 'activeusers' => 1],
        ], $result);
    }

    public function test_active_users_time_boundaries(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user1 = $this->getDataGenerator()->create_user();
        $user2 = $this->getDataGenerator()->create_user();
        $daystart = self::day_start();
        $timeend = $daystart + DAYSECS;

        $this->insert_log_record((int) $course->id, (int) $user1->id, $daystart);
        $this->insert_log_record((int) $course->id, (int) $user2->id, $timeend - 1);
        $this->insert_log_record((int) $course->id, (int) $user2->id, $timeend);

        $repository = new course_activity_repository();
        $result = $repository->get_active_users_per_day(
            (int) $course->id,
            $daystart,
            $timeend
        );

        $this->assertSame([
            ['daystart' => $daystart, 'activeusers' => 2],
        ], $result);
    }


    public function test_module_events_group_by_component(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $daystart = self::day_start();

        $this->insert_log_record((int) $course->id, (int) $user->id, $daystart + 60, 'mod_forum');
        $this->insert_log_record((int) $course->id, (int) $user->id, $daystart + 120, 'mod_assign');
        $this->insert_log_record((int) $course->id, (int) $user->id, $daystart + 180, 'mod_forum');

        $repository = new course_activity_repository();
        $result = $repository->get_module_events(
            (int) $course->id,
            $daystart,
            $daystart + DAYSECS
        );

        $this->assertSame([
            ['component' => 'mod_assign', 'eventcount' => 1],
            ['component' => 'mod_forum', 'eventcount' => 2],
        ], $result);
    }

    public function test_module_events_exclude_core_and_other_courses(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $daystart = self::day_start();

        $this->insert_log_record((int) $course->id, (int) $user->id, $daystart + 60, 'mod_quiz');
        $this->insert_log_record((int) $course->id, (int) $user->id, $daystart + 120, 'core');
        $this->insert_log_record((int) $othercourse->id, (int) $user->id, $daystart + 180, 'mod_quiz');

        $repository = new course_activity_repository();
        $result = $repository->get_module_events(
            (int) $course->id,
            $daystart,
            $daystart + DAYSECS
        );

        $this->assertSame([
            ['component' => 'mod_quiz', 'eventcount' => 1],
        ], $result);
    }

    public function test_module_events_time_boundaries(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $daystart = self::day_start();
        $timeend = $daystart + DAYSECS;

        $this->insert_log_record((int) $course->id, (int) $user->id, $daystart, 'mod_assign');
        $this->insert_log_record((int) $course->id, (int) $user->id, $timeend - 1, 'mod_assign');
        $this->insert_log_record((int) $course->id, (int) $user->id, $timeend, 'mod_assign');

        $repository = new course_activity_repository();
        $result = $repository->get_module_events(
            (int) $course->id,
            $daystart,
            $timeend
        );

        $this->assertSame([
            ['component' => 'mod_assign', 'eventcount' => 2],
        ], $result);
    }


    public function test_individual_assignment_submission_summary_counts_categories(): void {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $daystart = self::day_start();

        $assignmentid = $this->insert_assignment(
            (int) $course->id,
            $daystart + 3600
        );

        $this->insert_assignment_submission(
            $assignmentid,
            101,
            $daystart + 1800
        );
        $this->insert_assignment_submission(
            $assignmentid,
            102,
            $daystart + 3600
        );
        $this->insert_assignment_submission(
            $assignmentid,
            103,
            $daystart + 5400
        );

        $noduedateassignmentid = $this->insert_assignment(
            (int) $course->id,
            0
        );
        $this->insert_assignment_submission(
            $noduedateassignmentid,
            104,
            $daystart + 2400
        );

        $repository = new course_activity_repository();
        $result = $repository->get_individual_assignment_submission_summary(
            (int) $course->id,
            $daystart,
            $daystart + DAYSECS
        );

        $this->assertSame([
            'submitted' => 4,
            'ontime' => 2,
            'late' => 1,
            'noduedate' => 1,
        ], $result);
    }

    public function test_individual_assignment_submission_summary_filters_records(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $daystart = self::day_start();

        $assignmentid = $this->insert_assignment(
            (int) $course->id,
            $daystart + 3600
        );
        $otherassignmentid = $this->insert_assignment(
            (int) $othercourse->id,
            $daystart + 3600
        );

        $this->insert_assignment_submission(
            $assignmentid,
            101,
            $daystart + 1800
        );
        $this->insert_assignment_submission(
            $assignmentid,
            102,
            $daystart + 1800,
            'draft'
        );
        $this->insert_assignment_submission(
            $assignmentid,
            103,
            $daystart + 1800,
            'submitted',
            0
        );
        $this->insert_assignment_submission(
            $assignmentid,
            0,
            $daystart + 1800,
            'submitted',
            1,
            5
        );
        $this->insert_assignment_submission(
            $otherassignmentid,
            104,
            $daystart + 1800
        );

        $repository = new course_activity_repository();
        $result = $repository->get_individual_assignment_submission_summary(
            (int) $course->id,
            $daystart,
            $daystart + DAYSECS
        );

        $this->assertSame([
            'submitted' => 1,
            'ontime' => 1,
            'late' => 0,
            'noduedate' => 0,
        ], $result);
    }

    public function test_individual_assignment_submission_summary_time_boundaries(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $daystart = self::day_start();
        $timeend = $daystart + DAYSECS;

        $assignmentid = $this->insert_assignment(
            (int) $course->id,
            $timeend + DAYSECS
        );

        $this->insert_assignment_submission(
            $assignmentid,
            101,
            $daystart
        );
        $this->insert_assignment_submission(
            $assignmentid,
            102,
            $timeend - 1
        );
        $this->insert_assignment_submission(
            $assignmentid,
            103,
            $timeend
        );

        $repository = new course_activity_repository();
        $result = $repository->get_individual_assignment_submission_summary(
            (int) $course->id,
            $daystart,
            $timeend
        );

        $this->assertSame([
            'submitted' => 2,
            'ontime' => 2,
            'late' => 0,
            'noduedate' => 0,
        ], $result);
    }


    public function test_effective_excludes_group_submissions(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $daystart = self::day_start();
        $assignmentid = $this->insert_assignment((int) $course->id, $daystart + 3600);

        // One individual submission plus one group submission (must be excluded).
        $this->insert_assignment_submission($assignmentid, 101, $daystart + 1800);
        $this->insert_assignment_submission($assignmentid, 0, $daystart + 1800, 'submitted', 1, 5);

        $result = (new course_activity_repository())
            ->get_effective_individual_assignment_submission_summary(
                (int) $course->id,
                $daystart,
                $daystart + DAYSECS
            );

        $this->assertSame(1, $result['submitted']);
        $this->assertSame(1, $result['ontime']);
    }

    public function test_effective_excludes_non_latest_attempts(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $daystart = self::day_start();
        $assignmentid = $this->insert_assignment((int) $course->id, $daystart + 3600);

        $this->insert_assignment_submission($assignmentid, 101, $daystart + 1800);
        $this->insert_assignment_submission($assignmentid, 102, $daystart + 1800, 'submitted', 0);

        $result = (new course_activity_repository())
            ->get_effective_individual_assignment_submission_summary(
                (int) $course->id,
                $daystart,
                $daystart + DAYSECS
            );

        $this->assertSame(1, $result['submitted']);
    }

    public function test_effective_excludes_non_submitted_status(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $daystart = self::day_start();
        $assignmentid = $this->insert_assignment((int) $course->id, $daystart + 3600);

        $this->insert_assignment_submission($assignmentid, 101, $daystart + 1800);
        $this->insert_assignment_submission($assignmentid, 102, $daystart + 1800, 'draft');

        $result = (new course_activity_repository())
            ->get_effective_individual_assignment_submission_summary(
                (int) $course->id,
                $daystart,
                $daystart + DAYSECS
            );

        $this->assertSame(1, $result['submitted']);
    }

    public function test_effective_time_boundaries(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $daystart = self::day_start();
        $timeend = $daystart + DAYSECS;
        $assignmentid = $this->insert_assignment((int) $course->id, $timeend + DAYSECS);

        $this->insert_assignment_submission($assignmentid, 101, $daystart);
        $this->insert_assignment_submission($assignmentid, 102, $timeend - 1);
        $this->insert_assignment_submission($assignmentid, 103, $timeend);

        $result = (new course_activity_repository())
            ->get_effective_individual_assignment_submission_summary(
                (int) $course->id,
                $daystart,
                $timeend
            );

        $this->assertSame(2, $result['submitted']);
        $this->assertSame(2, $result['ontime']);
    }

    public function test_effective_user_override_extends_deadline(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $daystart = self::day_start();

        // Normal due date is early; the submission is after it (simple = late).
        $assignmentid = $this->insert_assignment((int) $course->id, $daystart + 1800);
        $this->insert_assignment_submission($assignmentid, (int) $user->id, $daystart + 3600);

        // A user override extends the deadline past the submission time.
        $this->insert_user_override($assignmentid, (int) $user->id, $daystart + 7200);

        $result = (new course_activity_repository())
            ->get_effective_individual_assignment_submission_summary(
                (int) $course->id,
                $daystart,
                $daystart + DAYSECS
            );

        $this->assertSame(1, $result['submitted']);
        $this->assertSame(1, $result['ontime']);
        $this->assertSame(0, $result['late']);
        $this->assertSame(1, $result['useroverride']);
    }

    public function test_effective_group_override_earlier_makes_late(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_group_member([
            'groupid' => $group->id,
            'userid' => $user->id,
        ]);
        $daystart = self::day_start();

        // Normal due date is late; a group override pulls it earlier.
        $assignmentid = $this->insert_assignment((int) $course->id, $daystart + 7200);
        $this->insert_group_override($assignmentid, (int) $group->id, 1, $daystart + 1800);

        // Submitted after the group deadline but before the normal due date.
        $this->insert_assignment_submission($assignmentid, (int) $user->id, $daystart + 3600);

        $result = (new course_activity_repository())
            ->get_effective_individual_assignment_submission_summary(
                (int) $course->id,
                $daystart,
                $daystart + DAYSECS
            );

        $this->assertSame(1, $result['submitted']);
        $this->assertSame(0, $result['ontime']);
        $this->assertSame(1, $result['late']);
        $this->assertSame(1, $result['groupoverride']);
    }

    public function test_effective_extension_extends_deadline(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $daystart = self::day_start();

        $assignmentid = $this->insert_assignment((int) $course->id, $daystart + 1800);
        $this->insert_assignment_submission($assignmentid, (int) $user->id, $daystart + 3600);

        // An individual extension pushes the deadline past the submission time.
        $this->insert_extension($assignmentid, (int) $user->id, $daystart + 7200);

        $result = (new course_activity_repository())
            ->get_effective_individual_assignment_submission_summary(
                (int) $course->id,
                $daystart,
                $daystart + DAYSECS
            );

        $this->assertSame(1, $result['submitted']);
        $this->assertSame(1, $result['ontime']);
        $this->assertSame(0, $result['late']);
        $this->assertSame(1, $result['extended']);
    }

    public function test_effective_group_override_and_extension_overlap(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id);
        $group = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_group_member([
            'groupid' => $group->id,
            'userid' => $user->id,
        ]);
        $daystart = self::day_start();

        // Group override sets an earlier base deadline, an extension then pushes.
        // It back past the submission time. The submission must count under BOTH.
        // Groupoverride and extended (overlapping attributes), and be on time.
        $assignmentid = $this->insert_assignment((int) $course->id, $daystart + 1800);
        $this->insert_group_override($assignmentid, (int) $group->id, 1, $daystart + 900);
        $this->insert_extension($assignmentid, (int) $user->id, $daystart + 7200);
        $this->insert_assignment_submission($assignmentid, (int) $user->id, $daystart + 3600);

        $result = (new course_activity_repository())
            ->get_effective_individual_assignment_submission_summary(
                (int) $course->id,
                $daystart,
                $daystart + DAYSECS
            );

        $this->assertSame(1, $result['submitted']);
        $this->assertSame(1, $result['ontime']);
        $this->assertSame(0, $result['late']);
        $this->assertSame(1, $result['groupoverride']);
        $this->assertSame(1, $result['extended']);
        $this->assertSame(0, $result['useroverride']);
    }

    public function test_effective_sum_invariant_across_mixed_cases(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $daystart = self::day_start();

        // On time and late against a normal due date.
        $withdue = $this->insert_assignment((int) $course->id, $daystart + 3600);
        $this->insert_assignment_submission($withdue, 101, $daystart + 1800);
        $this->insert_assignment_submission($withdue, 102, $daystart + 5400);

        // No due date.
        $noduedate = $this->insert_assignment((int) $course->id, 0);
        $this->insert_assignment_submission($noduedate, 103, $daystart + 1800);

        // Unresolved: user in two groups, each with a group override sharing the.
        // Lowest sortorder on the same assignment.
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id);
        $groupa = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $groupb = $this->getDataGenerator()->create_group(['courseid' => $course->id]);
        $this->getDataGenerator()->create_group_member([
            'groupid' => $groupa->id,
            'userid' => $user->id,
        ]);
        $this->getDataGenerator()->create_group_member([
            'groupid' => $groupb->id,
            'userid' => $user->id,
        ]);
        $tieassignment = $this->insert_assignment((int) $course->id, $daystart + 3600);
        $this->insert_group_override($tieassignment, (int) $groupa->id, 1, $daystart + 1800);
        $this->insert_group_override($tieassignment, (int) $groupb->id, 1, $daystart + 7200);
        $this->insert_assignment_submission($tieassignment, (int) $user->id, $daystart + 3600);

        $result = (new course_activity_repository())
            ->get_effective_individual_assignment_submission_summary(
                (int) $course->id,
                $daystart,
                $daystart + DAYSECS
            );

        $this->assertSame(4, $result['submitted']);
        $this->assertSame(1, $result['ontime']);
        $this->assertSame(1, $result['late']);
        $this->assertSame(1, $result['noduedate']);
        $this->assertSame(1, $result['unresolved']);

        // The core invariant must always hold.
        $this->assertSame(
            $result['submitted'],
            $result['ontime'] + $result['late'] + $result['noduedate'] + $result['unresolved']
        );
    }

    /**
     * Insert a minimal assignment record.
     *
     * @param int $courseid Course ID.
     * @param int $duedate Due date timestamp, or zero.
     * @return int Assignment ID.
     */
    private function insert_assignment(int $courseid, int $duedate): int {
        global $DB;

        return (int) $DB->insert_record('assign', (object) [
            'course' => $courseid,
            'name' => 'Repository test assignment',
            'intro' => '',
            'introformat' => FORMAT_HTML,
            'alwaysshowdescription' => 0,
            'nosubmissions' => 0,
            'submissiondrafts' => 0,
            'sendnotifications' => 0,
            'sendlatenotifications' => 0,
            'sendstudentnotifications' => 1,
            'duedate' => $duedate,
            'allowsubmissionsfromdate' => 0,
            'grade' => 100,
            'timemodified' => time(),
            'requiresubmissionstatement' => 0,
            'completionsubmit' => 0,
            'cutoffdate' => 0,
            'gradingduedate' => 0,
            'teamsubmission' => 0,
            'requireallteammemberssubmit' => 0,
            'teamsubmissiongroupingid' => 0,
            'blindmarking' => 0,
            'hidegrader' => 0,
            'revealidentities' => 0,
            'attemptreopenmethod' => 'none',
            'maxattempts' => -1,
            'markingworkflow' => 0,
            'markingallocation' => 0,
            'preventsubmissionnotingroup' => 0,
            'activity' => '',
            'activityformat' => FORMAT_HTML,
            'timelimit' => 0,
            'submissionattachments' => 0,
        ]);
    }

    /**
     * Insert a minimal assignment submission record.
     *
     * @param int $assignmentid Assignment ID.
     * @param int $userid User ID.
     * @param int $timemodified Submission timestamp.
     * @param string $status Submission status.
     * @param int $latest Whether this is the latest attempt.
     * @param int $groupid Group ID.
     */
    private function insert_assignment_submission(
        int $assignmentid,
        int $userid,
        int $timemodified,
        string $status = 'submitted',
        int $latest = 1,
        int $groupid = 0
    ): void {
        global $DB;

        $DB->insert_record('assign_submission', (object) [
            'assignment' => $assignmentid,
            'userid' => $userid,
            'timecreated' => $timemodified,
            'timemodified' => $timemodified,
            'status' => $status,
            'groupid' => $groupid,
            'attemptnumber' => 0,
            'latest' => $latest,
        ]);
    }

    /**
     * Insert a user-specific assignment override.
     *
     * @param int $assignmentid Assignment ID.
     * @param int $userid User ID.
     * @param int|null $duedate Override due date, or null to leave unset.
     * @param int $sortorder Override sort order.
     * @return int Override ID.
     */
    private function insert_user_override(
        int $assignmentid,
        int $userid,
        ?int $duedate,
        int $sortorder = 0
    ): int {
        global $DB;

        return (int) $DB->insert_record('assign_overrides', (object) [
            'assignid' => $assignmentid,
            'groupid' => null,
            'userid' => $userid,
            'sortorder' => $sortorder,
            'allowsubmissionsfromdate' => null,
            'duedate' => $duedate,
            'cutoffdate' => null,
            'timelimit' => null,
            'reasonformat' => 0,
        ]);
    }

    /**
     * Insert a group assignment override.
     *
     * @param int $assignmentid Assignment ID.
     * @param int $groupid Group ID.
     * @param int $sortorder Override sort order.
     * @param int|null $duedate Override due date, or null to leave unset.
     * @return int Override ID.
     */
    private function insert_group_override(
        int $assignmentid,
        int $groupid,
        int $sortorder,
        ?int $duedate
    ): int {
        global $DB;

        return (int) $DB->insert_record('assign_overrides', (object) [
            'assignid' => $assignmentid,
            'groupid' => $groupid,
            'userid' => null,
            'sortorder' => $sortorder,
            'allowsubmissionsfromdate' => null,
            'duedate' => $duedate,
            'cutoffdate' => null,
            'timelimit' => null,
            'reasonformat' => 0,
        ]);
    }

    /**
     * Insert an individual extension via assign_user_flags.
     *
     * @param int $assignmentid Assignment ID.
     * @param int $userid User ID.
     * @param int $extensionduedate Extension due date.
     */
    private function insert_extension(
        int $assignmentid,
        int $userid,
        int $extensionduedate
    ): void {
        global $DB;

        $DB->insert_record('assign_user_flags', (object) [
            'userid' => $userid,
            'assignment' => $assignmentid,
            'locked' => 0,
            'mailed' => 0,
            'extensionduedate' => $extensionduedate,
            'workflowstate' => null,
        ]);
    }

    /**
     * Insert a standard log record for the tests.
     */
    private function insert_log_record(
        int $courseid,
        int $userid,
        int $timecreated,
        string $component = 'core'
    ): void {
        global $DB;

        $context = \context_course::instance($courseid);

        $record = (object) [
            'eventname' => '\\core\\event\\course_viewed',
            'component' => $component,
            'action' => 'viewed',
            'target' => 'course',
            'objecttable' => 'course',
            'objectid' => $courseid,
            'crud' => 'r',
            'edulevel' => \core\event\base::LEVEL_PARTICIPATING,
            'contextid' => $context->id,
            'contextlevel' => CONTEXT_COURSE,
            'contextinstanceid' => $courseid,
            'userid' => $userid,
            'courseid' => $courseid,
            'relateduserid' => null,
            'anonymous' => 0,
            'other' => null,
            'timecreated' => $timecreated,
            'origin' => 'web',
            'ip' => '127.0.0.1',
            'realuserid' => null,
        ];

        $DB->insert_record('logstore_standard_log', $record);
    }

    /**
     * Return a fixed day-start timestamp for the tests.
     */
    private static function day_start(): int {
        return gmmktime(0, 0, 0, 1, 15, 2026);
    }
}
