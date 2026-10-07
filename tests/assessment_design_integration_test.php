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
 * Moodle integration checks for consistent reports, API protection and paging.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla;

use core_external\external_api;
use local_tla\external\get_course_quality_report;
use local_tla\indicator\assessment_design_analyzer;
use local_tla\output\assessment_design_presenter;
use local_tla\service\course_dashboard_service;
use local_tla\tests\assessment_design_cases;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/fixtures/assessment_design_cases.php');

/**
 * Tests for assessment_design_integration_test.
 * @covers \local_tla\external\get_course_quality_report
 * @covers \local_tla\output\assessment_design_presenter
 */
final class assessment_design_integration_test extends \advanced_testcase {
    public function test_api_and_dashboard_share_design_findings_and_return_schema(): void {
        $this->resetAfterTest();
        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id, 'attempts' => 0, 'grademethod' => 2, 'grade' => 100,
        ]);
        $dashboard = (new course_dashboard_service())->get_course_dashboard_data((int) $course->id, 1, 2, 8);
        $result = get_course_quality_report::execute((int) $course->id);
        $clean = external_api::clean_returnvalue(get_course_quality_report::execute_returns(), $result);
        $api = array_filter($clean['findings'], static fn(array $f): bool => $f['area'] === 'assessment_design');
        $apicodes = [];
        foreach ($api as $finding) {
            $evidence = json_decode($finding['evidence'], true, 512, JSON_THROW_ON_ERROR);
            $apicodes[] = $evidence['rule'];
            $this->assertSame('info', $finding['severity']);
            $this->assertSame('base_settings', $evidence['scope']);
        }
        $expected = array_column($dashboard['assessmentdesign']['activities'][0]['findings'], 'code');
        $this->assertEqualsCanonicalizing($expected, $apicodes);
        $this->assertGreaterThan(0, $clean['summary']['info']);
    }

    public function test_api_cannot_lower_the_site_minimum(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();
        set_config('min_observations', 8, 'local_tla');
        $course = $this->getDataGenerator()->create_course();
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id, 'grade' => 100]);
        $itemid = $DB->get_field('grade_items', 'id', [
            'courseid' => $course->id, 'itemtype' => 'mod', 'itemmodule' => 'assign',
            'iteminstance' => $assign->id, 'itemnumber' => 0,
        ], MUST_EXIST);
        for ($i = 0; $i < 2; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $DB->insert_record('grade_grades', (object) [
                'itemid' => $itemid, 'userid' => $user->id, 'rawgrademin' => 0,
                'rawgrademax' => 100, 'finalgrade' => 100, 'timecreated' => time(), 'timemodified' => time(),
            ]);
        }
        $result = get_course_quality_report::execute((int) $course->id, 1);
        foreach ($result['findings'] as $finding) {
            $this->assertNotSame('score_distribution', $finding['area']);
        }
        $this->assertGreaterThan(0, $result['summary']['info']);
    }

    public function test_api_rejects_student_without_capability(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id, 'student');
        $this->setUser($user);
        $this->expectException(\required_capability_exception::class);
        get_course_quality_report::execute((int) $course->id);
    }

    public function test_activity_name_is_plain_text_for_mustache_escaping(): void {
        $snapshot = ['checkedat' => 100, 'activities' => [
            (new assessment_design_analyzer())->analyze(assessment_design_cases::quiz([], [
                'name' => 'SQL &amp; Joins',
            ])),
        ]];
        $view = (new assessment_design_presenter())->export(
            $snapshot,
            new \moodle_url('/local/tla/assessment_design.php', ['id' => 2])
        );
        $this->assertSame('SQL & Joins', $view['activities'][0]['name']);
    }

    public function test_all_configuration_activities_are_accessible_through_paging(): void {
        $analyzer = new assessment_design_analyzer();
        $activities = [];
        for ($i = 1; $i <= 45; $i++) {
            $activities[] = $analyzer->analyze(assessment_design_cases::quiz([], [
                'cmid' => $i, 'name' => sprintf('Activity %02d', $i),
            ]));
        }
        $snapshot = ['checkedat' => 100, 'activities' => $activities];
        $presenter = new assessment_design_presenter();
        $url = new \moodle_url('/local/tla/assessment_design.php', ['id' => 2]);
        $first = $presenter->export($snapshot, $url, 0);
        $second = $presenter->export($snapshot, $url, 1);
        $last = $presenter->export($snapshot, $url, 999);
        $this->assertCount(20, $first['activities']);
        $this->assertCount(20, $second['activities']);
        $this->assertCount(5, $last['activities']);
        $this->assertFalse($first['hasprevious']);
        $this->assertTrue($first['hasnext']);
        $this->assertFalse($last['hasnext']);
        $ids = array_merge(
            array_column($first['activities'], 'cmid'),
            array_column($second['activities'], 'cmid'),
            array_column($last['activities'], 'cmid')
        );
        $this->assertSame(range(1, 45), $ids);
        $empty = $presenter->export(['checkedat' => 100, 'activities' => []], $url, 999);
        $this->assertFalse($empty['hasactivities']);
        $this->assertFalse($empty['hasnext']);
        $this->assertFalse($empty['hasprevious']);
    }
}
