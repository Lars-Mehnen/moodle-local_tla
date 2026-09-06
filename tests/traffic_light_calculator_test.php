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
 * Traffic-light calculator tests.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla;

use local_tla\indicator\traffic_light_calculator;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

#[CoversClass(traffic_light_calculator::class)]
final class traffic_light_calculator_test extends \advanced_testcase {
    public function test_green_below_yellow_threshold(): void {
        $result = (new traffic_light_calculator())->calculate(1, 10, 0.25, 0.50, 8);

        $this->assertSame('green', $result['status']);
        $this->assertSame(0.1, $result['rate']);
    }

    public function test_yellow_threshold_is_inclusive(): void {
        $result = (new traffic_light_calculator())->calculate(2, 8, 0.25, 0.50, 8);

        $this->assertSame('yellow', $result['status']);
        $this->assertSame(0.25, $result['rate']);
    }

    public function test_red_threshold_is_inclusive(): void {
        $result = (new traffic_light_calculator())->calculate(4, 8, 0.25, 0.50, 8);

        $this->assertSame('red', $result['status']);
        $this->assertSame(0.5, $result['rate']);
    }

    public function test_insufficient_observations_return_unknown(): void {
        $result = (new traffic_light_calculator())->calculate(2, 7, 0.25, 0.50, 8);

        $this->assertSame('unknown', $result['status']);
        $this->assertEqualsWithDelta(2 / 7, $result['rate'], 0.000001);
    }

    public function test_zero_observations_return_unknown_without_rate(): void {
        $result = (new traffic_light_calculator())->calculate(0, 0, 0.25, 0.50, 8);

        $this->assertSame('unknown', $result['status']);
        $this->assertNull($result['rate']);
    }

    public function test_numerator_must_not_exceed_denominator(): void {
        $this->expectException(\invalid_parameter_exception::class);

        (new traffic_light_calculator())->calculate(9, 8, 0.25, 0.50, 8);
    }

    public function test_threshold_order_is_validated(): void {
        $this->expectException(\invalid_parameter_exception::class);

        (new traffic_light_calculator())->calculate(1, 8, 0.60, 0.50, 8);
    }

    public function test_minimum_observations_must_be_positive(): void {
        $this->expectException(\invalid_parameter_exception::class);

        (new traffic_light_calculator())->calculate(0, 0, 0.25, 0.50, 0);
    }
}
