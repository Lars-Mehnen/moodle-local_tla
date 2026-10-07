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
 * Tests for the parameterization advisor.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla;

use local_tla\indicator\parameterization_advisor;


/**
 * Tests for parameterization_advisor_test.
 * @covers \local_tla\indicator\parameterization_advisor
 */
final class parameterization_advisor_test extends \advanced_testcase {
    /** @var parameterization_advisor Advisor under test. */
    private parameterization_advisor $advisor;

    protected function setUp(): void {
        parent::setUp();
        $this->advisor = new parameterization_advisor();
    }

    public function test_meets_target_variant_space(): void {
        foreach ([25, 60, 120, 200, 500] as $n) {
            $r = $this->advisor->recommend($n);
            // Provided variants must reach the target (ratio * n).
            $this->assertGreaterThanOrEqual($r['targetvariants'], $r['variants'], "N={$n}");
            $this->assertSame($n * parameterization_advisor::DEFAULT_RATIO, $r['targetvariants']);
            // Valuesperparam ^ params == variants.
            $this->assertSame(
                (int) ($r['valuesperparam'] ** $r['params']),
                $r['variants'],
                "N={$n}"
            );
        }
    }

    public function test_respects_caps(): void {
        $r = $this->advisor->recommend(5000);
        $this->assertLessThanOrEqual(parameterization_advisor::MAX_PARAMS, $r['params']);
        $this->assertLessThanOrEqual(
            parameterization_advisor::MAX_VALUES_PER_PARAM,
            $r['valuesperparam']
        );
        $this->assertCount($r['params'], $r['ranges']);
    }

    public function test_ranges_have_the_right_width(): void {
        $r = $this->advisor->recommend(200);
        foreach ($r['ranges'] as $range) {
            $this->assertSame($r['valuesperparam'], $range['hi'] - $range['lo'] + 1);
            $this->assertIsString($range['name']);
        }
    }

    public function test_snippet_is_pasteable(): void {
        $r = $this->advisor->recommend(200);
        $snippet = $r['snippet'];
        $this->assertStringContainsString('Twig all', $snippet);
        // One random() line per parameter.
        $this->assertSame($r['params'], substr_count($snippet, 'random('));
        foreach ($r['ranges'] as $range) {
            $this->assertStringContainsString(
                "random({$range['lo']}, {$range['hi']})",
                $snippet
            );
        }
    }

    public function test_invalid_cohort_is_rejected(): void {
        $this->expectException(\invalid_parameter_exception::class);
        $this->advisor->recommend(0);
    }

    public function test_invalid_ratio_is_rejected(): void {
        $this->expectException(\invalid_parameter_exception::class);
        $this->advisor->recommend(100, 0);
    }
}
