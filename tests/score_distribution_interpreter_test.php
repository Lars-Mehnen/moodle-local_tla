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
 * Tests for design-aware score interpretation and privacy preservation.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla;

use local_tla\indicator\assessment_design_analyzer;
use local_tla\indicator\score_distribution_analyzer;
use local_tla\indicator\score_distribution_interpreter;
use local_tla\tests\assessment_design_cases;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/fixtures/assessment_design_cases.php');

/**
 * Tests for score_distribution_interpreter_test.
 */
#[CoversClass(score_distribution_interpreter::class)]
final class score_distribution_interpreter_test extends \advanced_testcase {
    public function test_ceiling_with_best_attempts_is_informational(): void {
        $config = (new assessment_design_analyzer())->analyze(assessment_design_cases::quiz());
        $analysis = (new score_distribution_analyzer())->analyze(array_fill(0, 800, 100), 8);
        $rows = [['cmid' => 101, 'analysis' => $analysis]];
        $result = (new score_distribution_interpreter())->interpret(
            $rows,
            ['activities' => [$config], 'checkedat' => 100]
        );
        $this->assertSame('info', $result[0]['analysis']['severity']);
        $this->assertSame('ceiling_best', $result[0]['interpretation']['code']);
        $this->assertSame('review_first_and_final', $result[0]['interpretation']['recommendationtype']);
        $this->assertFalse($result[0]['interpretation']['historicalmatchverified']);
        $this->assertSame($analysis['histogram'], $result[0]['analysis']['histogram']);
        $this->assertSame($analysis['median'], $result[0]['analysis']['median']);
        $this->assertSame($rows[0]['analysis'], $analysis);
    }

    public function test_missing_context_never_becomes_randomisation_advice(): void {
        $a = (new score_distribution_analyzer())->analyze(array_fill(0, 10, 100), 8);
        $result = (new score_distribution_interpreter())->interpret(
            [['cmid' => 42, 'analysis' => $a]],
            ['activities' => [], 'checkedat' => 100]
        );
        $this->assertSame('ceiling_contextunknown', $result[0]['interpretation']['code']);
        $this->assertSame('info', $result[0]['analysis']['severity']);
        $this->assertSame('review_assessment_design', $result[0]['interpretation']['recommendationtype']);
    }

    public function test_small_group_suppression_is_preserved(): void {
        $a = (new score_distribution_analyzer())->analyze([100, 100], 8);
        $result = (new score_distribution_interpreter())->interpret(
            [['cmid' => 42, 'analysis' => $a]],
            ['activities' => [], 'checkedat' => 100]
        );
        $this->assertSame($a, $result[0]['analysis']);
        $this->assertNull($result[0]['interpretation']['recommendationtype']);
    }

    public function test_matching_uses_course_module_not_overlapping_instance_ids(): void {
        $analyzer = new assessment_design_analyzer();
        $configs = [$analyzer->analyze(assessment_design_cases::quiz()),
            $analyzer->analyze(assessment_design_cases::assignment(['attemptreopenmethod' => 'untilpass']))];
        $a = (new score_distribution_analyzer())->analyze(array_fill(0, 10, 100), 8);
        $result = (new score_distribution_interpreter())->interpret([
            ['cmid' => 101, 'analysis' => $a], ['cmid' => 102, 'analysis' => $a],
        ], ['activities' => $configs, 'checkedat' => 100]);
        $this->assertSame('ceiling_best', $result[0]['interpretation']['code']);
        $this->assertSame('ceiling_untilpass', $result[1]['interpretation']['code']);
    }
}
