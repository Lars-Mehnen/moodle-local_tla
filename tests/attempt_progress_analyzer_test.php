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
 * Tests for the attempt progress analyzer.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla;

use local_tla\indicator\attempt_progress_analyzer;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

#[CoversClass(attempt_progress_analyzer::class)]
final class attempt_progress_analyzer_test extends \advanced_testcase {
    /** @var attempt_progress_analyzer Analyzer under test. */
    private attempt_progress_analyzer $analyzer;

    protected function setUp(): void {
        parent::setUp();
        $this->analyzer = new attempt_progress_analyzer();
    }

    /**
     * Build a participant record.
     *
     * @param float $first First-attempt percentage.
     * @param float $last Last-attempt percentage.
     * @param float|null $best Best percentage (defaults to max of first/last).
     * @param int $attempts Attempt count.
     * @return array{first: float, last: float, best: float, attempts: int}
     */
    private static function p(float $first, float $last, ?float $best = null, int $attempts = 2): array {
        return [
            'first' => $first,
            'last' => $last,
            'best' => $best ?? max($first, $last),
            'attempts' => $attempts,
        ];
    }

    /**
     * Build $count identical participants with the given first/last.
     *
     * @param float $first First percentage.
     * @param float $last Last percentage.
     * @param int $count Number of participants.
     * @return array<int, array>
     */
    private static function cohort(float $first, float $last, int $count): array {
        return array_fill(0, $count, self::p($first, $last));
    }

    public function test_too_few_observations_is_unknown(): void {
        $result = $this->analyzer->analyze(self::cohort(40.0, 70.0, 5), 8);

        $this->assertSame('unknown', $result['status']);
        $this->assertSame('unknown', $result['severity']);
        $this->assertSame(5, $result['observations']);
        $this->assertNull($result['medianchange']);
    }

    public function test_exactly_minimum_is_analyzed(): void {
        $result = $this->analyzer->analyze(self::cohort(40.0, 70.0, 8), 8);

        $this->assertNotSame('unknown', $result['status']);
        $this->assertSame(8, $result['observations']);
    }

    public function test_clear_improvement(): void {
        $result = $this->analyzer->analyze(self::cohort(40.0, 70.0, 8), 8);

        $this->assertSame('improving', $result['status']);
        $this->assertSame('green', $result['severity']);
    }

    public function test_clear_decline(): void {
        $result = $this->analyzer->analyze(self::cohort(70.0, 40.0, 8), 8);

        $this->assertSame('declining', $result['status']);
        $this->assertSame('red', $result['severity']);
    }

    public function test_stable(): void {
        $result = $this->analyzer->analyze(self::cohort(50.0, 51.0, 8), 8);

        $this->assertSame('stable', $result['status']);
        $this->assertSame('yellow', $result['severity']);
    }

    public function test_mixed(): void {
        $participants = array_merge(
            self::cohort(40.0, 70.0, 3),  // improved (+30)
            self::cohort(70.0, 40.0, 3),  // declined (-30)
            self::cohort(50.0, 50.0, 2)   // stable
        );
        $result = $this->analyzer->analyze($participants, 8);

        $this->assertSame('mixed', $result['status']);
        $this->assertSame('yellow', $result['severity']);
    }

    public function test_plus_two_is_stable(): void {
        $result = $this->analyzer->analyze(self::cohort(50.0, 52.0, 8), 8);

        $this->assertEqualsWithDelta(1.0, $result['stableshare'], 1e-9);
        $this->assertEqualsWithDelta(0.0, $result['improvedshare'], 1e-9);
    }

    public function test_minus_two_is_stable(): void {
        $result = $this->analyzer->analyze(self::cohort(52.0, 50.0, 8), 8);

        $this->assertEqualsWithDelta(1.0, $result['stableshare'], 1e-9);
        $this->assertEqualsWithDelta(0.0, $result['declinedshare'], 1e-9);
    }

    public function test_more_than_plus_two_is_improved(): void {
        $result = $this->analyzer->analyze(self::cohort(50.0, 53.0, 8), 8);

        $this->assertEqualsWithDelta(1.0, $result['improvedshare'], 1e-9);
    }

    public function test_less_than_minus_two_is_declined(): void {
        $result = $this->analyzer->analyze(self::cohort(53.0, 50.0, 8), 8);

        $this->assertEqualsWithDelta(1.0, $result['declinedshare'], 1e-9);
    }

    public function test_substantial_improvement_threshold(): void {
        // Change of exactly +10 counts as substantial.
        $result = $this->analyzer->analyze(self::cohort(40.0, 50.0, 8), 8);

        $this->assertEqualsWithDelta(1.0, $result['substantialimprovementshare'], 1e-9);

        // Change of +9 does not.
        $result = $this->analyzer->analyze(self::cohort(40.0, 49.0, 8), 8);
        $this->assertEqualsWithDelta(0.0, $result['substantialimprovementshare'], 1e-9);
    }

    public function test_means_are_correct(): void {
        $participants = [
            self::p(40.0, 60.0, 60.0, 1),
            self::p(60.0, 80.0, 80.0, 2),
        ];
        $result = $this->analyzer->analyze($participants, 2);

        $this->assertEqualsWithDelta(50.0, $result['meanfirst'], 1e-9);
        $this->assertEqualsWithDelta(70.0, $result['meanlast'], 1e-9);
        $this->assertEqualsWithDelta(70.0, $result['meanbest'], 1e-9);
        $this->assertEqualsWithDelta(1.5, $result['meanattempts'], 1e-9);
    }

    public function test_median_change_is_correct(): void {
        $participants = [
            self::p(40.0, 50.0),  // +10
            self::p(40.0, 60.0),  // +20
            self::p(40.0, 70.0),  // +30
        ];
        $result = $this->analyzer->analyze($participants, 3);

        $this->assertEqualsWithDelta(20.0, $result['medianchange'], 1e-9);
        $this->assertEqualsWithDelta(20.0, $result['meanchange'], 1e-9);
    }

    public function test_input_is_not_modified(): void {
        $participants = [self::p(40.0, 70.0), self::p(60.0, 50.0)];
        $copy = $participants;

        $this->analyzer->analyze($participants, 2);

        $this->assertSame($copy, $participants);
    }

    public function test_invalid_percentages_are_rejected(): void {
        $sets = [
            'nan' => [self::p(40.0, 70.0), ['first' => NAN, 'last' => 70.0, 'best' => 70.0, 'attempts' => 2]],
            'over' => [['first' => 40.0, 'last' => 101.0, 'best' => 101.0, 'attempts' => 2]],
            'negative' => [['first' => -1.0, 'last' => 70.0, 'best' => 70.0, 'attempts' => 2]],
        ];
        foreach ($sets as $label => $set) {
            $threw = false;
            try {
                $this->analyzer->analyze($set, 1);
            } catch (\invalid_parameter_exception $e) {
                $threw = true;
            }
            $this->assertTrue($threw, "Expected exception for {$label}.");
        }
    }

    public function test_valid_best_at_least_first_and_last(): void {
        // Best equal to max(first, last) is accepted.
        $result = $this->analyzer->analyze([
            self::p(40.0, 70.0, 70.0),
            self::p(80.0, 60.0, 80.0),
        ], 2);
        $this->assertSame(2, $result['observations']);
    }

    public function test_best_below_first_or_last_is_rejected(): void {
        $threw = false;
        try {
            $this->analyzer->analyze([
                ['first' => 40.0, 'last' => 70.0, 'best' => 50.0, 'attempts' => 2],
            ], 1);
        } catch (\invalid_parameter_exception $e) {
            $threw = true;
        }
        $this->assertTrue($threw, 'Best below last must be rejected.');
    }
}
