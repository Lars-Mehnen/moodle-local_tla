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
 * Tests for the Bayesian Emax estimator.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla;

use local_tla\prediction\emax_bayes_estimator;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for emax_bayes_estimator_test.
 */
#[CoversClass(emax_bayes_estimator::class)]
final class emax_bayes_estimator_test extends \advanced_testcase {
    /** @var emax_bayes_estimator Estimator under test. */
    private emax_bayes_estimator $estimator;

    protected function setUp(): void {
        parent::setUp();
        $this->estimator = new emax_bayes_estimator();
    }

    /**
     * Deterministic Emax data with reproducible pseudo-noise (no rand()).
     *
     * Uses a fixed sine-based perturbation so the dataset is identical on every
     * run, which the determinism assertion relies on.
     *
     * @param float $emax True ceiling.
     * @param float $ec50 True half-max exposure.
     * @param float $sigma Noise amplitude.
     * @param int $n Number of observations.
     * @return array<int, array{c: float, e: float}>
     */
    private static function emax_data(float $emax, float $ec50, float $sigma, int $n = 180): array {
        $obs = [];
        for ($k = 0; $k < $n; $k++) {
            $c = 1 + ($k % 12);
            $mu = $emax * $c / ($ec50 + $c);
            // Deterministic zero-mean perturbation in [-1, 1] scaled by sigma.
            $noise = $sigma * sin($k * 2.399963);
            $e = max(0.0, min(100.0, $mu + $noise));
            $obs[] = ['c' => (float) $c, 'e' => $e];
        }
        return $obs;
    }

    public function test_too_few_observations_is_unknown(): void {
        $result = $this->estimator->estimate(self::emax_data(80.0, 3.0, 5.0, 5), 8);

        $this->assertSame('unknown', $result['status']);
        $this->assertSame('unknown', $result['severity']);
        $this->assertNull($result['fitquality']);
        $this->assertSame(5, $result['observations']);
        $this->assertSame([], $result['curve']);
        $this->assertNull($result['emax']['median']);
    }

    public function test_recovers_known_parameters(): void {
        $result = $this->estimator->estimate(self::emax_data(80.0, 3.0, 6.0), 8);

        $this->assertSame('estimated', $result['status']);
        // The credible intervals must bracket the true generating parameters.
        $this->assertGreaterThan($result['emax']['lo'], 80.0);
        $this->assertLessThan($result['emax']['hi'], 80.0);
        $this->assertGreaterThan($result['ec50']['lo'], 3.0);
        $this->assertLessThan($result['ec50']['hi'], 3.0);
        // And the medians must be reasonably close to the truth.
        $this->assertEqualsWithDelta(80.0, $result['emax']['median'], 10.0);
        $this->assertEqualsWithDelta(3.0, $result['ec50']['median'], 1.5);
    }

    public function test_is_deterministic(): void {
        $data = self::emax_data(70.0, 4.0, 5.0);
        $a = $this->estimator->estimate($data, 8);
        $b = $this->estimator->estimate($data, 8);

        $this->assertSame($a, $b);
    }

    public function test_curve_is_monotonic_increasing_and_bounded(): void {
        $result = $this->estimator->estimate(self::emax_data(80.0, 3.0, 5.0), 8);

        $prev = -1.0;
        foreach ($result['curve'] as $point) {
            $this->assertGreaterThanOrEqual(0.0, $point['med']);
            $this->assertLessThanOrEqual(100.0, $point['hi']);
            // The saturating mean curve is non-decreasing with more exposure.
            // Each point's median is a weighted quantile estimated independently.
            // On a discrete grid, so tiny (< 1 point) numerical jitter is allowed.
            // On the flat, saturated part of the curve.
            $this->assertGreaterThanOrEqual($prev - 1.0, $point['med']);
            $prev = $point['med'];
            // The 80% band brackets the median: lo <= med <= hi (small tolerance.
            // For independent per-point quantile jitter on the discrete grid).
            $this->assertGreaterThanOrEqual($point['lo'] - 1.0, $point['med']);
            $this->assertLessThanOrEqual($point['hi'] + 1.0, $point['med']);
        }
    }

    public function test_curve_starts_at_zero_exposure(): void {
        $result = $this->estimator->estimate(self::emax_data(80.0, 3.0, 5.0), 8);

        // Emax * 0 / (EC50 + 0) = 0, so the mean effect at zero practice is zero.
        $this->assertEqualsWithDelta(0.0, $result['curve'][0]['c'], 1e-9);
        $this->assertEqualsWithDelta(0.0, $result['curve'][0]['med'], 1e-9);
    }

    public function test_no_exposure_variation_is_unknown(): void {
        $flat = array_fill(0, 30, ['c' => 4.0, 'e' => 55.0]);
        $result = $this->estimator->estimate($flat, 8);

        // Without variation in exposure the saturation point is not identifiable.
        $this->assertSame('unknown', $result['status']);
    }

    public function test_bins_respect_small_group_threshold(): void {
        // Two students at c=1 (below threshold), five at c=6 (at/above threshold).
        $obs = [];
        for ($i = 0; $i < 2; $i++) {
            $obs[] = ['c' => 1.0, 'e' => 20.0];
        }
        for ($i = 0; $i < 5; $i++) {
            $obs[] = ['c' => 6.0, 'e' => 70.0];
        }
        // Pad with varied exposures so a fit is attempted.
        for ($i = 0; $i < 10; $i++) {
            $obs[] = ['c' => (float) (2 + ($i % 4)), 'e' => 40.0 + $i];
        }

        $result = $this->estimator->estimate($obs, 8);

        $exposures = array_column($result['bins'], 'c');
        $this->assertNotContains(1.0, $exposures, 'A two-student bin must be hidden.');
        foreach ($result['bins'] as $bin) {
            $this->assertGreaterThanOrEqual(
                emax_bayes_estimator::MIN_BIN_COUNT,
                $bin['count']
            );
        }
    }

    public function test_ppc_reports_observed_and_predicted_spread(): void {
        $result = $this->estimator->estimate(self::emax_data(80.0, 3.0, 6.0), 8);

        $this->assertNotNull($result['ppc']['obssd']);
        $this->assertNotNull($result['ppc']['pvalue']);
        $this->assertGreaterThanOrEqual(0.0, $result['ppc']['pvalue']);
        $this->assertLessThanOrEqual(1.0, $result['ppc']['pvalue']);
    }

    public function test_invalid_observations_are_rejected(): void {
        $invalidsets = [
            'missing_c' => [['e' => 50.0]],
            'missing_e' => [['c' => 3.0]],
            'nan_c' => [['c' => NAN, 'e' => 50.0]],
            'negative_c' => [['c' => -1.0, 'e' => 50.0]],
            'inf_e' => [['c' => 3.0, 'e' => INF]],
            'effect_above_hundred' => [['c' => 3.0, 'e' => 101.0]],
            'negative_e' => [['c' => 3.0, 'e' => -0.1]],
        ];

        foreach ($invalidsets as $label => $set) {
            $threw = false;
            try {
                $this->estimator->estimate($set, 1);
            } catch (\invalid_parameter_exception $e) {
                $threw = true;
            }
            $this->assertTrue($threw, "Expected exception for {$label}.");
        }
    }

    public function test_minobservations_must_be_positive(): void {
        $this->expectException(\invalid_parameter_exception::class);
        $this->estimator->estimate(self::emax_data(80.0, 3.0, 5.0), 0);
    }

    public function test_returns_only_aggregate_keys(): void {
        $result = $this->estimator->estimate(self::emax_data(80.0, 3.0, 6.0), 8);

        $this->assertEqualsCanonicalizing(
            [
                'status', 'severity', 'fitquality', 'observations',
                'emax', 'ec50', 'sigma', 'explainedvariance',
                'curve', 'ppc', 'bins', 'exposuremax',
            ],
            array_keys($result)
        );
    }

    public function test_strong_relationship_explains_variance(): void {
        $result = $this->estimator->estimate(self::emax_data(80.0, 3.0, 6.0), 8);

        // Low noise around a real curve -> most variance is explained.
        $this->assertGreaterThan(0.5, $result['explainedvariance']);
        $this->assertSame('good', $result['fitquality']);
    }

    public function test_flat_relationship_is_reported_as_flat(): void {
        // Performance does not depend on exposure: only noise, no dose-response.
        $obs = [];
        for ($k = 0; $k < 200; $k++) {
            $c = 1 + ($k % 20);
            // Deterministic zero-mean noise around a constant 60%.
            $e = 60.0 + 25.0 * sin($k * 2.399963);
            $obs[] = ['c' => (float) $c, 'e' => max(0.0, min(100.0, $e))];
        }
        $result = $this->estimator->estimate($obs, 8);

        $this->assertSame('estimated', $result['status']);
        $this->assertSame('flat', $result['fitquality']);
        $this->assertLessThan(
            emax_bayes_estimator::FLAT_EXPLAINED_MAX,
            $result['explainedvariance']
        );
    }
}
