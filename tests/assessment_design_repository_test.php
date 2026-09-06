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
 * Moodle database integration tests for the read-only configuration check.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla;

use local_tla\repository\assessment_design_repository;
use local_tla\service\assessment_design_service;
use local_tla\service\course_dashboard_service;
use local_tla\service\course_quality_report;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

#[CoversClass(assessment_design_repository::class)]
#[CoversClass(assessment_design_service::class)]
final class assessment_design_repository_test extends \advanced_testcase {
    public function test_empty_course_has_configuration_snapshot_without_observations(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $data = (new assessment_design_service())->get_course_design((int) $course->id);
        $this->assertSame([], $data['activities']);
        $this->assertSame('current_configuration', $data['source']);
        $this->assertSame('not_verifiable', $data['limitations']['independentcompetence']);
    }

    public function test_live_modules_without_grades_hidden_and_other_courses(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id, 'attempts' => 3]);
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $deleted = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $this->getDataGenerator()->create_module('quiz', ['course' => $other->id]);
        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $deleted->cmid]);
        $DB->set_field('course_modules', 'visible', 0, ['id' => $assign->cmid]);
        $result = (new assessment_design_repository())->get_course_settings((int) $course->id);
        $this->assertCount(2, $result);
        $this->assertEqualsCanonicalizing([(int) $quiz->cmid, (int) $assign->cmid], array_column($result, 'cmid'));
        $bycm = array_column($result, null, 'cmid');
        $this->assertFalse($bycm[$assign->cmid]['visible']);
        $this->assertSame(3.0, $bycm[$quiz->cmid]['settings']['attempts']);
    }

    public function test_overrides_return_only_presence_no_id_reason_or_settings(): void {
        global $DB;
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $quiz = $this->getDataGenerator()->create_module('quiz', ['course' => $course->id]);
        $assign = $this->getDataGenerator()->create_module('assign', ['course' => $course->id]);
        $DB->insert_record('quiz_overrides', (object) [
            'quiz' => $quiz->id, 'userid' => $user->id, 'attempts' => 7, 'password' => 'PRIVATE-PASSWORD',
        ]);
        $DB->insert_record('assign_user_flags', (object) [
            'assignment' => $assign->id, 'userid' => $user->id, 'extensionduedate' => time() + 1000,
        ]);
        $rows = (new assessment_design_service())->get_course_design((int) $course->id);
        $bycm = array_column($rows['activities'], null, 'cmid');
        $this->assertTrue($bycm[$quiz->cmid]['hasoverrides']);
        $this->assertTrue($bycm[$assign->cmid]['hasexceptions']);
        $json = json_encode($rows);
        foreach (['userid', 'groupid', 'PRIVATE-PASSWORD', $user->email, 'extensionduedate'] as $private) {
            $this->assertStringNotContainsString($private, $json);
        }
        $this->assertContains('overrides', array_column($bycm[$quiz->cmid]['findings'], 'code'));
    }

    public function test_query_count_is_two_and_does_not_grow_with_activities(): void {
        global $DB;
        $this->resetAfterTest();
        $small = $this->getDataGenerator()->create_course();
        $large = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('quiz', ['course' => $small->id]);
        for ($i = 0; $i < 12; $i++) {
            $this->getDataGenerator()->create_module('quiz', ['course' => $large->id]);
            $this->getDataGenerator()->create_module('assign', ['course' => $large->id]);
        }
        $repository = new assessment_design_repository();
        $before = $DB->perf_get_reads();
        $repository->get_course_settings((int) $small->id);
        $smallreads = $DB->perf_get_reads() - $before;
        $before = $DB->perf_get_reads();
        $repository->get_course_settings((int) $large->id);
        $largereads = $DB->perf_get_reads() - $before;
        $this->assertSame(2, $smallreads);
        $this->assertSame($smallreads, $largereads);
    }

    public function test_design_findings_exist_without_student_grades(): void {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create_course();
        $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id, 'attempts' => 3, 'grademethod' => 2, 'grade' => 100,
        ]);
        $data = (new course_dashboard_service())->get_course_dashboard_data((int) $course->id, 1, 2, 800);
        $this->assertCount(1, $data['assessmentdesign']['activities']);
        $codes = array_column($data['assessmentdesign']['activities'][0]['findings'], 'code');
        $this->assertContains('quiz_average', $codes);
        $report = (new course_quality_report())->generate((int) $course->id, 800);
        $designfindings = array_filter($report['findings'], static fn(array $f): bool => $f['area'] === 'assessment_design');
        $this->assertNotEmpty($designfindings);
        foreach ($designfindings as $finding) {
            $this->assertSame('info', $finding['severity']);
            $this->assertSame('current_configuration', $finding['evidence']['source']);
        }
    }

    public function test_invalid_course_id_is_rejected(): void {
        $this->expectException(\invalid_parameter_exception::class);
        (new assessment_design_repository())->get_course_settings(0);
    }
}
