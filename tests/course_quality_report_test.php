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
 * Tests for the course quality report aggregator.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla;

use local_tla\service\course_quality_report;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for course_quality_report_test.
 */
#[CoversClass(course_quality_report::class)]
final class course_quality_report_test extends \advanced_testcase {
    /**
     * Insert a final grade for a user on an assignment's grade item.
     *
     * @param int $itemid Grade item id.
     * @param int $userid User id.
     * @param float $grade Final grade.
     */
    private function insert_grade(int $itemid, int $userid, float $grade): void {
        global $DB;
        $DB->insert_record('grade_grades', (object) [
            'itemid' => $itemid,
            'userid' => $userid,
            'rawgrademin' => 0,
            'rawgrademax' => 100,
            'finalgrade' => $grade,
            'timecreated' => time(),
            'timemodified' => time(),
        ]);
    }

    public function test_empty_course_has_no_findings(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();

        $report = (new course_quality_report())->generate((int) $course->id, 8);

        $this->assertSame((int) $course->id, $report['courseid']);
        $this->assertSame(0, $report['generated']);
        $this->assertSame([], $report['findings']);
    }

    public function test_ceiling_activity_yields_contextual_review_not_randomisation(): void {
        global $DB;
        $this->resetAfterTest(true);

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

        // Ten students clustered near the maximum -> ceiling effect.
        for ($i = 0; $i < 10; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->insert_grade($itemid, (int) $user->id, 96.0 + ($i % 5));
        }

        $report = (new course_quality_report())->generate((int) $course->id, 8);

        $finding = null;
        foreach ($report['findings'] as $f) {
            if ($f['area'] === 'score_distribution') {
                $finding = $f;
                break;
            }
        }
        $this->assertNotNull($finding, 'Expected a score-distribution finding.');
        $this->assertSame('ceiling', $finding['status']);
        $this->assertNotNull($finding['recommendation']);
        $this->assertSame('review_assessment_design', $finding['recommendation']['type']);
        $this->assertSame('info', $finding['severity']);
        $this->assertNull($finding['recommendation']['params']);
        $this->assertFalse($finding['evidence']['assessmentcontext']['historicalmatchverified']);
    }

    public function test_unexplained_quiz_ceiling_recommends_gated_parameterization(): void {
        global $DB;
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        // Single-attempt quiz: no retry mechanism explains a ceiling -> gated.
        // Parameterization recommendation.
        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $course->id,
            'grade' => 100,
            'attempts' => 1,
        ]);
        $itemid = (int) $DB->get_field('grade_items', 'id', [
            'itemtype' => 'mod',
            'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id,
            'itemnumber' => 0,
            'courseid' => $course->id,
        ], MUST_EXIST);
        for ($i = 0; $i < 10; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->insert_grade($itemid, (int) $user->id, 96.0 + ($i % 5));
        }

        $report = (new course_quality_report())->generate((int) $course->id, 8);

        $finding = null;
        foreach ($report['findings'] as $f) {
            if ($f['area'] === 'score_distribution') {
                $finding = $f;
                break;
            }
        }
        $this->assertNotNull($finding);
        $this->assertSame('ceiling', $finding['status']);
        $this->assertSame('yellow', $finding['severity']);
        $this->assertSame('parameterize_quiz', $finding['recommendation']['type']);
        // The concrete parameterization plan is attached, sized to the cohort.
        $params = $finding['recommendation']['params'];
        $this->assertGreaterThanOrEqual($params['targetvariants'], $params['variants']);
        $this->assertStringContainsString('random(', $params['snippet']);
    }

    public function test_report_returns_only_aggregate_keys(): void {
        $this->resetAfterTest(true);
        $course = $this->getDataGenerator()->create_course();

        $report = (new course_quality_report())->generate((int) $course->id, 8);

        $this->assertEqualsCanonicalizing(
            ['courseid', 'generated', 'findings', 'summary'],
            array_keys($report)
        );
        $this->assertArrayNotHasKey('userid', $report);
    }
}
