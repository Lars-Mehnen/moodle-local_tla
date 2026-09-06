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
 * Score-distribution shape analyzer.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\indicator;

use local_tla\statistics\descriptive;

defined('MOODLE_INTERNAL') || die();

/**
 * Classify the shape of a graded activity's score distribution.
 *
 * The analyzer is intentionally free of any database access or Moodle global
 * state. It receives an array of already normalised and validated percentage
 * values in the closed range [0, 100] and returns descriptive statistics plus
 * a transparent, rule-based shape classification. Invalid input (non-finite,
 * negative or above 100) is rejected with an exception; the repository is
 * responsible for normalising raw grades and counting invalid ones separately.
 *
 * The classification rules are deliberately simple and explainable (no machine
 * learning). "green" only means: no marked floor, ceiling or U-shaped effect
 * under the chosen rules — it is a hint, not a pedagogical guarantee.
 */
final class score_distribution_analyzer {
    /** @var int Number of histogram bins. */
    public const BINS = 10;

    // Score-range boundaries (percent). See analyze() for the exact edge rules.
    /** @var float A grade below this percentage counts as "low". */
    public const LOW_MAX = 20.0;
    /** @var float The inclusive lower edge of the "middle" band. */
    public const MIDDLE_MIN = 40.0;
    /** @var float The inclusive upper edge of the "middle" band. */
    public const MIDDLE_MAX = 60.0;
    /** @var float A grade strictly above this percentage counts as "high". */
    public const HIGH_MIN = 80.0;

    // Shape thresholds (documented in the class doc block and the task spec).
    /** @var float Minimum low share for a U-shape. */
    public const USHAPE_LOW_SHARE = 0.20;
    /** @var float Minimum high share for a U-shape. */
    public const USHAPE_HIGH_SHARE = 0.20;
    /** @var float Minimum combined extreme share for a U-shape. */
    public const USHAPE_EXTREME_SHARE = 0.60;
    /** @var float Maximum middle share for a U-shape. */
    public const USHAPE_MIDDLE_SHARE_MAX = 0.20;
    /** @var float Minimum high share for a ceiling effect. */
    public const CEILING_HIGH_SHARE = 0.60;
    /** @var float Minimum median for a ceiling effect. */
    public const CEILING_MEDIAN = 80.0;
    /** @var float Minimum low share for a floor effect. */
    public const FLOOR_LOW_SHARE = 0.60;
    /** @var float Maximum median for a floor effect. */
    public const FLOOR_MEDIAN = 20.0;
    /** @var float Minimum middle share for a middle-centred distribution. */
    public const MIDDLE_SHARE = 0.40;
    /** @var float Minimum median for a middle-centred distribution. */
    public const MIDDLE_MEDIAN_MIN = 40.0;
    /** @var float Maximum median for a middle-centred distribution. */
    public const MIDDLE_MEDIAN_MAX = 60.0;

    /**
     * Analyze a set of normalised percentage grades.
     *
     * Score bands (documented edge behaviour):
     * - low:    [0, 20)          value < 20
     * - middle: [40, 60]         40 <= value <= 60
     * - high:   (80, 100]        value > 80
     * - extreme = low + high
     * Values of exactly 20 or exactly 80 fall in neither adjacent band by
     * design (they are transition points), which keeps the shares disjoint.
     *
     * Classification order (a genuine U-shape must be detected before the
     * one-sided ceiling/floor effects):
     * unknown -> ushape -> ceiling -> floor -> middle -> mixed.
     *
     * @param array<int, float|int> $percentages Validated grades in [0, 100].
     * @param int $minobservations Minimum count for a classification (>= 1).
     * @return array{
     *     status: string,
     *     severity: string,
     *     observations: int,
     *     mean: float|null,
     *     median: float|null,
     *     q1: float|null,
     *     q3: float|null,
     *     stddev: float|null,
     *     lowshare: float|null,
     *     middleshare: float|null,
     *     highshare: float|null,
     *     extremeshare: float|null,
     *     histogram: array<int, array{from: int, to: int, count: int, percentage: float}>
     * }
     * @throws \invalid_parameter_exception If input is invalid.
     */
    public function analyze(array $percentages, int $minobservations): array {
        if ($minobservations < 1) {
            throw new \invalid_parameter_exception('Minimum observations must be positive.');
        }

        foreach ($percentages as $value) {
            if (!is_int($value) && !is_float($value)) {
                throw new \invalid_parameter_exception('Percentages must be numeric.');
            }
            $value = (float) $value;
            if (!is_finite($value) || $value < 0.0 || $value > 100.0) {
                throw new \invalid_parameter_exception(
                    'Percentages must be finite values within [0, 100].'
                );
            }
        }

        $observations = count($percentages);

        if ($observations < $minobservations) {
            return [
                'status' => 'unknown',
                'severity' => 'unknown',
                'observations' => $observations,
                'mean' => null,
                'median' => null,
                'q1' => null,
                'q3' => null,
                'stddev' => null,
                'lowshare' => null,
                'middleshare' => null,
                'highshare' => null,
                'extremeshare' => null,
                'histogram' => [],
            ];
        }

        // Work on a local copy so the caller's array is never modified.
        $values = array_values($percentages);

        $mean = (float) descriptive::mean($values);
        $median = (float) descriptive::quantile($values, 0.5);
        $q1 = (float) descriptive::quantile($values, 0.25);
        $q3 = (float) descriptive::quantile($values, 0.75);
        $stddevraw = descriptive::stddev($values);
        $stddev = $stddevraw === null ? null : (float) $stddevraw;

        $histogram = $this->build_histogram($values);

        $low = 0;
        $middle = 0;
        $high = 0;
        foreach ($values as $value) {
            $value = (float) $value;
            if ($value < self::LOW_MAX) {
                $low++;
            }
            if ($value >= self::MIDDLE_MIN && $value <= self::MIDDLE_MAX) {
                $middle++;
            }
            if ($value > self::HIGH_MIN) {
                $high++;
            }
        }

        $lowshare = $low / $observations;
        $middleshare = $middle / $observations;
        $highshare = $high / $observations;
        $extremeshare = $lowshare + $highshare;

        [$status, $severity] = $this->classify(
            $lowshare,
            $middleshare,
            $highshare,
            $extremeshare,
            $median
        );

        return [
            'status' => $status,
            'severity' => $severity,
            'observations' => $observations,
            'mean' => $mean,
            'median' => $median,
            'q1' => $q1,
            'q3' => $q3,
            'stddev' => $stddev,
            'lowshare' => $lowshare,
            'middleshare' => $middleshare,
            'highshare' => $highshare,
            'extremeshare' => $extremeshare,
            'histogram' => $histogram,
        ];
    }

    /**
     * Apply the transparent shape-classification rules.
     *
     * @param float $lowshare Share below 20 percent.
     * @param float $middleshare Share within [40, 60].
     * @param float $highshare Share above 80 percent.
     * @param float $extremeshare Combined low and high share.
     * @param float $median Median percentage.
     * @return array{0: string, 1: string} Status and severity.
     */
    private function classify(
        float $lowshare,
        float $middleshare,
        float $highshare,
        float $extremeshare,
        float $median
    ): array {
        // U-shape first, so a genuine two-peaked distribution is not mistaken
        // for a one-sided ceiling or floor effect.
        if (
            $lowshare >= self::USHAPE_LOW_SHARE &&
            $highshare >= self::USHAPE_HIGH_SHARE &&
            $extremeshare >= self::USHAPE_EXTREME_SHARE &&
            $middleshare <= self::USHAPE_MIDDLE_SHARE_MAX
        ) {
            return ['ushape', 'red'];
        }

        if ($highshare >= self::CEILING_HIGH_SHARE && $median >= self::CEILING_MEDIAN) {
            // High final grades alone are not a pedagogical defect.
            return ['ceiling', 'info'];
        }

        if ($lowshare >= self::FLOOR_LOW_SHARE && $median <= self::FLOOR_MEDIAN) {
            return ['floor', 'red'];
        }

        if (
            $middleshare >= self::MIDDLE_SHARE &&
            $median >= self::MIDDLE_MEDIAN_MIN &&
            $median <= self::MIDDLE_MEDIAN_MAX
        ) {
            return ['middle', 'green'];
        }

        return ['mixed', 'yellow'];
    }

    /**
     * Build a ten-class histogram over [0, 100].
     *
     * Bins are [0,10), [10,20), ..., [80,90), [90,100]; the value 100 falls in
     * the last bin.
     *
     * @param array<int, float|int> $values Validated grades in [0, 100].
     * @return array<int, array{from: int, to: int, count: int, percentage: float}>
     */
    private function build_histogram(array $values): array {
        $counts = array_fill(0, self::BINS, 0);

        foreach ($values as $value) {
            $value = (float) $value;
            $bin = (int) floor($value / 10.0);
            if ($bin >= self::BINS) {
                $bin = self::BINS - 1;
            }
            $counts[$bin]++;
        }

        $total = count($values);
        $histogram = [];
        for ($i = 0; $i < self::BINS; $i++) {
            $histogram[] = [
                'from' => $i * 10,
                'to' => ($i + 1) * 10,
                'count' => $counts[$i],
                'percentage' => $total === 0 ? 0.0 : ($counts[$i] / $total) * 100.0,
            ];
        }

        return $histogram;
    }
}
