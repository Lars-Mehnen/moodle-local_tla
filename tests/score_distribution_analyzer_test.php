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
 * Tests for the score-distribution analyzer.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla;

use local_tla\indicator\score_distribution_analyzer;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for score_distribution_analyzer_test.
 */
#[CoversClass(score_distribution_analyzer::class)]
final class score_distribution_analyzer_test extends \advanced_testcase {
    /** @var score_distribution_analyzer Analyzer under test. */
    private score_distribution_analyzer $analyzer;

    protected function setUp(): void {
        parent::setUp();
        $this->analyzer = new score_distribution_analyzer();
    }

    /**
     * Build an array with $count copies of $value.
     *
     * @param float $value Value.
     * @param int $count Repeat count.
     * @return array<int, float>
     */
    private static function repeat(float $value, int $count): array {
        return array_fill(0, $count, $value);
    }

    public function test_too_few_observations_is_unknown(): void {
        $result = $this->analyzer->analyze(self::repeat(50.0, 5), 8);

        $this->assertSame('unknown', $result['status']);
        $this->assertSame('unknown', $result['severity']);
        $this->assertSame(5, $result['observations']);
        $this->assertNull($result['median']);
        $this->assertSame([], $result['histogram']);
    }

    public function test_exactly_minimum_observations_is_analyzed(): void {
        $result = $this->analyzer->analyze(self::repeat(50.0, 8), 8);

        $this->assertNotSame('unknown', $result['status']);
        $this->assertSame(8, $result['observations']);
    }

    public function test_strong_u_shape(): void {
        $values = array_merge(self::repeat(5.0, 5), self::repeat(95.0, 5));
        $result = $this->analyzer->analyze($values, 8);

        $this->assertSame('ushape', $result['status']);
        $this->assertSame('red', $result['severity']);
    }

    public function test_only_high_is_ceiling(): void {
        $result = $this->analyzer->analyze(self::repeat(95.0, 10), 8);

        $this->assertSame('ceiling', $result['status']);
        $this->assertSame('info', $result['severity']);
    }

    public function test_only_low_is_floor(): void {
        $result = $this->analyzer->analyze(self::repeat(5.0, 10), 8);

        $this->assertSame('floor', $result['status']);
        $this->assertSame('red', $result['severity']);
    }

    public function test_middle_centred_is_green(): void {
        $result = $this->analyzer->analyze(self::repeat(50.0, 10), 8);

        $this->assertSame('middle', $result['status']);
        $this->assertSame('green', $result['severity']);
    }

    public function test_broad_mixed_is_yellow(): void {
        $values = [10.0, 25.0, 35.0, 45.0, 55.0, 65.0, 75.0, 90.0];
        $result = $this->analyzer->analyze($values, 8);

        $this->assertSame('mixed', $result['status']);
        $this->assertSame('yellow', $result['severity']);
    }

    public function test_u_shape_wins_over_ceiling(): void {
        // 30% low, 70% high: median is 95 (would satisfy ceiling), but the.
        // Balanced extremes make it a genuine U-shape, which must win.
        $values = array_merge(self::repeat(5.0, 3), self::repeat(95.0, 7));
        $result = $this->analyzer->analyze($values, 8);

        $this->assertSame('ushape', $result['status']);
    }

    public function test_zero_is_in_first_bin(): void {
        $values = array_merge(self::repeat(0.0, 8), self::repeat(50.0, 2));
        $result = $this->analyzer->analyze($values, 8);

        $this->assertSame(0, $result['histogram'][0]['from']);
        $this->assertSame(10, $result['histogram'][0]['to']);
        $this->assertSame(8, $result['histogram'][0]['count']);
    }

    public function test_hundred_is_in_last_bin(): void {
        $values = array_merge(self::repeat(100.0, 8), self::repeat(50.0, 2));
        $result = $this->analyzer->analyze($values, 8);

        $last = $result['histogram'][score_distribution_analyzer::BINS - 1];
        $this->assertSame(90, $last['from']);
        $this->assertSame(100, $last['to']);
        $this->assertSame(8, $last['count']);
    }

    public function test_histogram_count_sum_equals_observations(): void {
        $values = [0.0, 5.0, 15.0, 25.0, 45.0, 55.0, 75.0, 85.0, 95.0, 100.0];
        $result = $this->analyzer->analyze($values, 8);

        $sum = array_sum(array_column($result['histogram'], 'count'));
        $this->assertSame(count($values), $sum);
    }

    public function test_histogram_percentages_sum_to_hundred(): void {
        $values = [0.0, 5.0, 15.0, 25.0, 45.0, 55.0, 75.0, 85.0, 95.0, 100.0];
        $result = $this->analyzer->analyze($values, 8);

        $sum = array_sum(array_column($result['histogram'], 'percentage'));
        $this->assertEqualsWithDelta(100.0, $sum, 1e-9);
    }

    public function test_median_and_quartiles(): void {
        $values = [10.0, 20.0, 30.0, 40.0, 50.0, 60.0, 70.0, 80.0];
        $result = $this->analyzer->analyze($values, 8);

        $this->assertEqualsWithDelta(45.0, $result['median'], 1e-9);
        $this->assertEqualsWithDelta(27.5, $result['q1'], 1e-9);
        $this->assertEqualsWithDelta(62.5, $result['q3'], 1e-9);
    }

    public function test_input_array_is_not_modified(): void {
        $values = [80.0, 10.0, 50.0, 30.0, 90.0, 20.0, 60.0, 40.0];
        $copy = $values;

        $this->analyzer->analyze($values, 8);

        $this->assertSame($copy, $values);
    }

    public function test_band_boundary_twenty_is_neither_low_nor_middle(): void {
        // Exactly 20% is below "middle" (starts at 40) and not "low" (< 20).
        $result = $this->analyzer->analyze(self::repeat(20.0, 10), 8);

        $this->assertEqualsWithDelta(0.0, $result['lowshare'], 1e-9);
        $this->assertEqualsWithDelta(0.0, $result['middleshare'], 1e-9);
        $this->assertEqualsWithDelta(0.0, $result['highshare'], 1e-9);
    }

    public function test_band_boundary_forty_is_middle(): void {
        $result = $this->analyzer->analyze(self::repeat(40.0, 10), 8);

        $this->assertEqualsWithDelta(1.0, $result['middleshare'], 1e-9);
    }

    public function test_band_boundary_sixty_is_middle(): void {
        $result = $this->analyzer->analyze(self::repeat(60.0, 10), 8);

        $this->assertEqualsWithDelta(1.0, $result['middleshare'], 1e-9);
    }

    public function test_band_boundary_eighty_is_neither_middle_nor_high(): void {
        // Exactly 80% is not "high" (> 80) and above "middle" (ends at 60).
        $result = $this->analyzer->analyze(self::repeat(80.0, 10), 8);

        $this->assertEqualsWithDelta(0.0, $result['lowshare'], 1e-9);
        $this->assertEqualsWithDelta(0.0, $result['middleshare'], 1e-9);
        $this->assertEqualsWithDelta(0.0, $result['highshare'], 1e-9);
    }

    public function test_band_boundary_zero_is_low(): void {
        $result = $this->analyzer->analyze(self::repeat(0.0, 10), 8);

        $this->assertEqualsWithDelta(1.0, $result['lowshare'], 1e-9);
        $this->assertSame(10, $result['histogram'][0]['count']);
    }

    public function test_band_boundary_hundred_is_high(): void {
        $result = $this->analyzer->analyze(self::repeat(100.0, 10), 8);

        $this->assertEqualsWithDelta(1.0, $result['highshare'], 1e-9);
        $this->assertSame(10, $result['histogram'][score_distribution_analyzer::BINS - 1]['count']);
    }

    public function test_shares_use_the_analyzed_sample_only(): void {
        // Shares are fractions of the observations actually passed in.
        $values = array_merge(self::repeat(10.0, 2), self::repeat(50.0, 6), self::repeat(90.0, 2));
        $result = $this->analyzer->analyze($values, 8);

        $this->assertSame(10, $result['observations']);
        $this->assertEqualsWithDelta(0.2, $result['lowshare'], 1e-9);
        $this->assertEqualsWithDelta(0.6, $result['middleshare'], 1e-9);
        $this->assertEqualsWithDelta(0.2, $result['highshare'], 1e-9);
        $this->assertEqualsWithDelta(0.4, $result['extremeshare'], 1e-9);
    }

    public function test_invalid_values_are_rejected(): void {
        $invalidsets = [
            'nan' => [50.0, NAN, 50.0],
            'inf' => [50.0, INF, 50.0],
            'negative' => [50.0, -1.0, 50.0],
            'above_hundred' => [50.0, 101.0, 50.0],
        ];

        foreach ($invalidsets as $label => $set) {
            $threw = false;
            try {
                $this->analyzer->analyze($set, 1);
            } catch (\invalid_parameter_exception $e) {
                $threw = true;
            }
            $this->assertTrue($threw, "Expected exception for {$label}.");
        }
    }
}
