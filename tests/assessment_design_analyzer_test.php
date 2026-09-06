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
 * Unit tests for conservative assessment-design rules.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla;

use local_tla\indicator\assessment_design_analyzer;
use local_tla\tests\assessment_design_cases;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();
require_once(__DIR__ . '/fixtures/assessment_design_cases.php');

#[CoversClass(assessment_design_analyzer::class)]
final class assessment_design_analyzer_test extends \advanced_testcase {
    /** @return array Named rule cases. */
    public static function rule_cases(): array {
        return assessment_design_cases::cases();
    }

    #[DataProvider('rule_cases')]
    public function test_rule(array $input, string $code, string $status): void {
        $before = $input;
        $result = (new assessment_design_analyzer())->analyze($input);
        $findings = array_column($result['findings'], null, 'code');
        $this->assertArrayHasKey($code, $findings);
        $this->assertSame($status, $findings[$code]['status']);
        $this->assertSame($before, $input);
        $this->assertSame('unknown', $result['purpose']);
        $this->assertSame('not_evaluated', $result['courseweight']);
        foreach ($findings as $finding) {
            $this->assertArrayNotHasKey('severity', $finding);
            $this->assertTrue(get_string_manager()->string_exists($finding['messagekey'], 'local_tla'));
            if ($finding['actionkey'] !== null) {
                $this->assertTrue(get_string_manager()->string_exists($finding['actionkey'], 'local_tla'));
            }
        }
    }

    public function test_core_constant_parity(): void {
        global $CFG;
        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        $this->assertSame((int) QUIZ_GRADEHIGHEST, assessment_design_analyzer::GRADE_HIGHEST);
        $this->assertSame((int) QUIZ_GRADEAVERAGE, assessment_design_analyzer::GRADE_AVERAGE);
        $this->assertSame((int) QUIZ_ATTEMPTFIRST, assessment_design_analyzer::GRADE_FIRST);
        $this->assertSame((int) QUIZ_ATTEMPTLAST, assessment_design_analyzer::GRADE_LAST);
        $this->assertSame(\mod_quiz\question\display_options::DURING, assessment_design_analyzer::DURING);
        $this->assertSame(\mod_quiz\question\display_options::IMMEDIATELY_AFTER, assessment_design_analyzer::IMMEDIATE);
        $this->assertSame(\mod_quiz\question\display_options::LATER_WHILE_OPEN, assessment_design_analyzer::OPEN);
        $this->assertSame(\mod_quiz\question\display_options::AFTER_CLOSE, assessment_design_analyzer::CLOSED);
    }

    public function test_no_impossible_window_claim_without_known_opening(): void {
        $input = assessment_design_cases::quiz(['timeclose' => 100, 'delay1' => 1000]);
        $result = (new assessment_design_analyzer())->analyze($input);
        $this->assertNotContains('quiz_retry_window', array_column($result['findings'], 'code'));
    }

    public function test_single_attempt_does_not_claim_average_penalty(): void {
        $input = assessment_design_cases::quiz(['attempts' => 1, 'grademethod' => 2], ['hasoverrides' => true]);
        $result = (new assessment_design_analyzer())->analyze($input);
        $this->assertNotContains('quiz_average', array_column($result['findings'], 'code'));
        $this->assertSame('single', $result['retrycontext']);
    }
}
