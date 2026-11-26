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
 * Tests for the U-shape origin analyser.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla;

use local_tla\indicator\ushape_origin_analyzer;
use local_tla\tests\ushape_origin_cases;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/local/tla/tests/fixtures/ushape_origin_cases.php');

/**
 * Tests for ushape_origin_analyzer.
 */
#[CoversClass(ushape_origin_analyzer::class)]
final class ushape_origin_analyzer_test extends \basic_testcase {
    /**
     * Shared synthetic cases.
     *
     * @return array
     */
    public static function cases_provider(): array {
        $result = [];
        foreach (ushape_origin_cases::all() as $name => $case) {
            $result[$name] = [$case];
        }
        return $result;
    }

    /**
     * Each synthetic course yields the expected patterns.
     *
     * @param array $case Case definition.
     */
    #[DataProvider('cases_provider')]
    public function test_cases(array $case): void {
        $result = (new ushape_origin_analyzer())->analyze(
            $case['items'],
            $case['grades'],
            $case['activity'],
            $case['passshare'],
            $case['now'],
            8
        );
        foreach ($case['expect'] as $path => $expected) {
            $value = $result;
            foreach (explode('.', $path) as $key) {
                $value = $value[$key] ?? null;
            }
            $this->assertSame($expected, $value, $path);
        }
    }

    public function test_auc_and_threshold_helpers(): void {
        $this->assertEqualsWithDelta(1.0, ushape_origin_analyzer::auc([3, 4], [1, 2]), 1e-9);
        $this->assertEqualsWithDelta(0.5, ushape_origin_analyzer::auc([1, 2], [1, 2]), 1e-9);
        $this->assertNull(ushape_origin_analyzer::otsu_threshold([5, 5, 5]));
        $threshold = ushape_origin_analyzer::otsu_threshold([0, 5, 10, 90, 95, 100]);
        $this->assertGreaterThan(10, $threshold);
        $this->assertLessThan(90, $threshold);
        $this->assertGreaterThan(0.95, ushape_origin_analyzer::eta_squared([0, 5, 10, 90, 95, 100], $threshold));
    }

    public function test_invalid_pass_share_is_rejected(): void {
        $this->expectException(\invalid_parameter_exception::class);
        (new ushape_origin_analyzer())->analyze([], [], [], 0.0, 0, 8);
    }

    public function test_invalid_grade_is_rejected(): void {
        $this->expectException(\invalid_parameter_exception::class);
        (new ushape_origin_analyzer())->analyze([], [1 => [1 => 120.0]], [], 0.5, 0, 8);
    }
}
