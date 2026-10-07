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
 * Robustness, empty-state and small-group protection tests.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla;

use local_tla\service\course_dashboard_service;
use local_tla\repository\course_grade_repository;


/**
 * Tests for dashboard_hardening_test.
 * @covers \local_tla\service\course_dashboard_service
 */
final class dashboard_hardening_test extends \advanced_testcase {
    /**
     * A completely empty course produces clean, empty aggregates and no error.
     */
    public function test_empty_course_is_clean(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $now = time();

        $data = (new course_dashboard_service())->get_course_dashboard_data(
            (int) $course->id,
            $now - DAYSECS,
            $now + 1,
            8
        );

        $this->assertSame([], $data['scoredistributions']);
        $this->assertSame([], $data['quizprogress']);
        $this->assertSame('unknown', $data['courseprogress']['status']);
        $this->assertSame('unknown', $data['courseprogress']['severity']);
        // No sensitive aggregate metrics are exposed when there is nothing to show.
        $this->assertNull($data['courseprogress']['medianchange']);
        $this->assertNull($data['courseprogress']['improvedshare']);
        $this->assertNull($data['courseprogress']['stableshare']);
        $this->assertNull($data['courseprogress']['declinedshare']);
    }

    /**
     * Below the minimum observation count, no distribution metrics are exposed.
     */
    public function test_small_group_score_distribution_hides_metrics(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'grade' => 100,
        ]);
        $itemid = (int) $DB->get_field('grade_items', 'id', [
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'iteminstance' => $assign->id,
            'itemnumber' => 0,
            'courseid' => $course->id,
        ], MUST_EXIST);

        // Only two grades, but the minimum is eight.
        foreach ([55.0, 60.0] as $grade) {
            $user = $this->getDataGenerator()->create_user();
            $DB->insert_record('grade_grades', (object) [
                'itemid' => $itemid, 'userid' => $user->id, 'rawgrademin' => 0,
                'rawgrademax' => 100, 'finalgrade' => $grade,
                'timecreated' => time(), 'timemodified' => time(),
            ]);
        }

        $distributions = (new course_grade_repository())
            ->get_activity_score_distributions((int) $course->id, 8);

        $this->assertCount(1, $distributions);
        $analysis = $distributions[0]['analysis'];
        $this->assertSame('unknown', $analysis['status']);
        // Individually revealing metrics must stay null for a tiny group.
        $this->assertNull($analysis['median']);
        $this->assertNull($analysis['q1']);
        $this->assertNull($analysis['q3']);
        $this->assertNull($analysis['lowshare']);
        $this->assertNull($analysis['middleshare']);
        $this->assertNull($analysis['highshare']);
        $this->assertSame([], $analysis['histogram']);
    }

    /**
     * Exactly the minimum number of observations is analysed; one fewer is not.
     */
    public function test_minimum_observation_boundary(): void {
        global $DB;

        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', [
            'course' => $course->id,
            'grade' => 100,
        ]);
        $itemid = (int) $DB->get_field('grade_items', 'id', [
            'itemtype' => 'mod',
            'itemmodule' => 'assign',
            'iteminstance' => $assign->id,
            'itemnumber' => 0,
            'courseid' => $course->id,
        ], MUST_EXIST);
        for ($i = 0; $i < 4; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $DB->insert_record('grade_grades', (object) [
                'itemid' => $itemid, 'userid' => $user->id, 'rawgrademin' => 0,
                'rawgrademax' => 100, 'finalgrade' => 50.0,
                'timecreated' => time(), 'timemodified' => time(),
            ]);
        }

        $repo = new course_grade_repository();

        $atmin = $repo->get_activity_score_distributions((int) $course->id, 4);
        $this->assertNotSame('unknown', $atmin[0]['analysis']['status']);

        $belowmin = $repo->get_activity_score_distributions((int) $course->id, 5);
        $this->assertSame('unknown', $belowmin[0]['analysis']['status']);
        $this->assertNull($belowmin[0]['analysis']['median']);
    }
}
