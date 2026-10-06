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
 * Tests for the course dashboard service.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla;

use local_tla\service\course_dashboard_service;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for course_dashboard_service_test.
 */
#[CoversClass(course_dashboard_service::class)]
final class course_dashboard_service_test extends \advanced_testcase {
    public function test_empty_dashboard_data(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $daystart = self::day_start();

        $service = new course_dashboard_service();
        $result = $service->get_course_dashboard_data(
            (int) $course->id,
            $daystart,
            $daystart + DAYSECS
        );

        $this->assertSame([], $result['assessmentdesign']['activities']);
        $this->assertSame('current_configuration', $result['assessmentdesign']['source']);
        $this->assertIsInt($result['assessmentdesign']['checkedat']);
        unset($result['assessmentdesign']);

        $this->assertSame([
            'courseid' => (int) $course->id,
            'timestart' => $daystart,
            'timeend' => $daystart + DAYSECS,
            'eventsperday' => [],
            'activeusersperday' => [],
            'moduleevents' => [],
            'assignmentsubmissions' => [
                'submitted' => 0,
                'ontime' => 0,
                'late' => 0,
                'noduedate' => 0,
                'extended' => 0,
                'useroverride' => 0,
                'groupoverride' => 0,
                'unresolved' => 0,
            ],
            'scoredistributions' => [],
            'quizprogress' => [],
            'courseprogress' => [
                'activities' => 0,
                'participants' => 0,
                'status' => 'unknown',
                'severity' => 'unknown',
                'medianchange' => null,
                'improvedshare' => null,
                'stableshare' => null,
                'declinedshare' => null,
                'warning' => 'Activities may differ in difficulty and learning objectives.',
            ],
            'doseresponse' => [
                'observations' => 0,
                'quizzes' => 0,
                'analysis' => [
                    'status' => 'unknown',
                    'severity' => 'unknown',
                    'fitquality' => null,
                    'observations' => 0,
                    'emax' => ['median' => null, 'lo' => null, 'hi' => null, 'mean' => null],
                    'ec50' => ['median' => null, 'lo' => null, 'hi' => null, 'mean' => null],
                    'sigma' => ['median' => null, 'lo' => null, 'hi' => null, 'mean' => null],
                    'explainedvariance' => null,
                    'curve' => [],
                    'ppc' => [
                        'obssd' => null,
                        'repsdmedian' => null,
                        'repsdlo' => null,
                        'repsdhi' => null,
                        'pvalue' => null,
                    ],
                    'bins' => [],
                    'exposuremax' => null,
                ],
            ],
        ], $result);
    }

    public function test_dashboard_includes_score_distributions(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'grade' => 100,
        ]);
        global $DB;
        $itemid = (int) $DB->get_field('grade_items', 'id', [
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'iteminstance' => $assign->id,
            'itemnumber' => 0,
            'courseid' => $course->id,
        ], MUST_EXIST);
        $user = $this->getDataGenerator()->create_user();
        $DB->insert_record('grade_grades', (object) [
            'itemid' => $itemid,
            'userid' => $user->id,
            'rawgrademin' => 0,
            'rawgrademax' => 100,
            'finalgrade' => 55.0,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);

        $service = new course_dashboard_service();
        $result = $service->get_course_dashboard_data(
            (int) $course->id,
            self::day_start(),
            self::day_start() + DAYSECS,
            1
        );

        $this->assertArrayHasKey('scoredistributions', $result);
        $this->assertCount(1, $result['scoredistributions']);
        $this->assertSame((int) $assign->id, $result['scoredistributions'][0]['instanceid']);
        $this->assertSame(1, $result['scoredistributions'][0]['validgrades']);
    }

    public function test_dashboard_combines_repository_metrics(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $userone = $this->getDataGenerator()->create_user();
        $usertwo = $this->getDataGenerator()->create_user();
        $daystart = self::day_start();

        $this->insert_log_record(
            (int) $course->id,
            (int) $userone->id,
            $daystart + 60,
            'mod_forum'
        );
        $this->insert_log_record(
            (int) $course->id,
            (int) $userone->id,
            $daystart + 120,
            'mod_forum'
        );
        $this->insert_log_record(
            (int) $course->id,
            (int) $usertwo->id,
            $daystart + 180,
            'mod_assign'
        );

        $assignmentid = $this->insert_assignment(
            (int) $course->id,
            $daystart + 3600
        );
        $this->insert_assignment_submission(
            $assignmentid,
            (int) $userone->id,
            $daystart + 1800
        );
        $this->insert_assignment_submission(
            $assignmentid,
            (int) $usertwo->id,
            $daystart + 5400
        );

        $service = new course_dashboard_service();
        $result = $service->get_course_dashboard_data(
            (int) $course->id,
            $daystart,
            $daystart + DAYSECS
        );

        $this->assertSame([
            [
                'daystart' => $daystart,
                'eventcount' => 3,
            ],
        ], $result['eventsperday']);

        $this->assertSame([
            [
                'daystart' => $daystart,
                'activeusers' => 2,
            ],
        ], $result['activeusersperday']);

        $this->assertSame([
            [
                'component' => 'mod_assign',
                'eventcount' => 1,
            ],
            [
                'component' => 'mod_forum',
                'eventcount' => 2,
            ],
        ], $result['moduleevents']);

        $this->assertSame([
            'submitted' => 2,
            'ontime' => 1,
            'late' => 1,
            'noduedate' => 0,
            'extended' => 0,
            'useroverride' => 0,
            'groupoverride' => 0,
            'unresolved' => 0,
        ], $result['assignmentsubmissions']);
    }

    public function test_invalid_parameters_are_rejected(): void {
        $service = new course_dashboard_service();

        $this->expectException(\invalid_parameter_exception::class);
        $service->get_course_dashboard_data(0, 0, DAYSECS);
    }

    /**
     * Return a stable UTC day boundary.
     *
     * @return int
     */
    private static function day_start(): int {
        return intdiv(time(), DAYSECS) * DAYSECS;
    }

    /**
     * Insert a minimal standard log record.
     *
     * @param int $courseid Course ID.
     * @param int $userid User ID.
     * @param int $timecreated Event timestamp.
     * @param string $component Moodle component.
     */
    private function insert_log_record(
        int $courseid,
        int $userid,
        int $timecreated,
        string $component
    ): void {
        global $DB;

        $DB->insert_record('logstore_standard_log', (object) [
            'eventname' => '\core\event\course_viewed',
            'component' => $component,
            'action' => 'viewed',
            'target' => 'course',
            'objecttable' => null,
            'objectid' => null,
            'crud' => 'r',
            'edulevel' => 2,
            'contextid' => 1,
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
        ]);
    }

    /**
     * Insert a minimal assignment.
     *
     * @param int $courseid Course ID.
     * @param int $duedate Due date.
     * @return int Assignment ID.
     */
    private function insert_assignment(int $courseid, int $duedate): int {
        global $DB;

        return (int) $DB->insert_record('assign', (object) [
            'course' => $courseid,
            'name' => 'Dashboard service test assignment',
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
     * Insert a submitted assignment attempt.
     *
     * @param int $assignmentid Assignment ID.
     * @param int $userid User ID.
     * @param int $timemodified Submission timestamp.
     */
    private function insert_assignment_submission(
        int $assignmentid,
        int $userid,
        int $timemodified
    ): void {
        global $DB;

        $DB->insert_record('assign_submission', (object) [
            'assignment' => $assignmentid,
            'userid' => $userid,
            'timecreated' => $timemodified,
            'timemodified' => $timemodified,
            'status' => 'submitted',
            'groupid' => 0,
            'attemptnumber' => 0,
            'latest' => 1,
        ]);
    }
}
