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
 * Attempt / activity progress analyzer.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\indicator;

use local_tla\statistics\descriptive;


/**
 * Classify how a cohort's results change between a first and a later result.
 *
 * Database-free and side-effect-free: it receives per-participant first / last /
 * best percentages (already normalised and validated) and returns aggregate
 * statistics plus a transparent, rule-based classification. It is used both for
 * quiz attempt progress (first vs last vs best completed attempt) and, with
 * best = max(first, last), for course-level progress across activities.
 *
 * The classification never claims teaching quality. "improving" only means that
 * for a large share of participants the later result is higher than the first;
 * the cause (repetition, feedback, question knowledge, collaboration, differing
 * attempt difficulty, ...) is not determined here and stays visible in the
 * explanation strings.
 */
final class attempt_progress_analyzer {
    /** @var float Change within +/- this many points counts as "stable". */
    public const STABLE_TOLERANCE = 2.0;
    /** @var float A change of at least this many points is "substantial". */
    public const SUBSTANTIAL_IMPROVEMENT = 10.0;

    /** @var float Minimum improved share for an "improving" verdict. */
    public const IMPROVED_SHARE_MIN = 0.60;
    /** @var float Minimum median change for an "improving" verdict. */
    public const IMPROVED_MEDIAN_MIN = 5.0;
    /** @var float Minimum declined share for a "declining" verdict. */
    public const DECLINED_SHARE_MIN = 0.50;
    /** @var float Maximum median change for a "declining" verdict. */
    public const DECLINED_MEDIAN_MAX = -5.0;
    /** @var float Minimum stable share for a "stable" verdict. */
    public const STABLE_SHARE_MIN = 0.60;

    /**
     * Analyze per-participant first / last / best percentages.
     *
     * Per participant: change = last - first.
     * - stable:   |change| <= 2 points
     * - improved: change  >  +2 points
     * - declined: change  <  -2 points
     * - substantial improvement: change >= +10 points
     *
     * Classification order: unknown -> improving -> declining -> stable -> mixed.
     * (improving and declining are mutually exclusive at these thresholds.)
     *
     * @param array<int, array{first: float|int, last: float|int, best: float|int, attempts: int}> $participants
     * @param int $minobservations Minimum participants for a classification (>= 1).
     * @return array{
     *     status: string,
     *     severity: string,
     *     observations: int,
     *     meanfirst: float|null,
     *     meanlast: float|null,
     *     meanbest: float|null,
     *     medianchange: float|null,
     *     meanchange: float|null,
     *     improvedshare: float|null,
     *     stableshare: float|null,
     *     declinedshare: float|null,
     *     substantialimprovementshare: float|null,
     *     meanattempts: float|null
     * }
     * @throws \invalid_parameter_exception If input is invalid.
     */
    public function analyze(array $participants, int $minobservations): array {
        if ($minobservations < 1) {
            throw new \invalid_parameter_exception('Minimum observations must be positive.');
        }

        $firsts = [];
        $lasts = [];
        $bests = [];
        $attempts = [];
        $changes = [];

        foreach ($participants as $p) {
            foreach (['first', 'last', 'best'] as $key) {
                if (!isset($p[$key]) || (!is_int($p[$key]) && !is_float($p[$key]))) {
                    throw new \invalid_parameter_exception("Missing or non-numeric '{$key}'.");
                }
                $value = (float) $p[$key];
                if (!is_finite($value) || $value < 0.0 || $value > 100.0) {
                    throw new \invalid_parameter_exception(
                        "Percentage '{$key}' must be finite within [0, 100]."
                    );
                }
            }

            $first = (float) $p['first'];
            $last = (float) $p['last'];
            $best = (float) $p['best'];

            // Best must be at least as high as both first and last.
            if ($best < $first - 1e-9 || $best < $last - 1e-9) {
                throw new \invalid_parameter_exception(
                    'Best must be at least the first and last value.'
                );
            }

            if (!isset($p['attempts']) || !is_int($p['attempts']) || $p['attempts'] < 0) {
                throw new \invalid_parameter_exception('Attempts must be a non-negative integer.');
            }

            $firsts[] = $first;
            $lasts[] = $last;
            $bests[] = $best;
            $attempts[] = $p['attempts'];
            $changes[] = $last - $first;
        }

        $observations = count($participants);

        if ($observations < $minobservations) {
            return [
                'status' => 'unknown',
                'severity' => 'unknown',
                'observations' => $observations,
                'meanfirst' => null,
                'meanlast' => null,
                'meanbest' => null,
                'medianchange' => null,
                'meanchange' => null,
                'improvedshare' => null,
                'stableshare' => null,
                'declinedshare' => null,
                'substantialimprovementshare' => null,
                'meanattempts' => null,
            ];
        }

        $improved = 0;
        $declined = 0;
        $stable = 0;
        $substantial = 0;
        foreach ($changes as $change) {
            if ($change > self::STABLE_TOLERANCE) {
                $improved++;
            } else if ($change < -self::STABLE_TOLERANCE) {
                $declined++;
            } else {
                $stable++;
            }
            if ($change >= self::SUBSTANTIAL_IMPROVEMENT) {
                $substantial++;
            }
        }

        $improvedshare = $improved / $observations;
        $stableshare = $stable / $observations;
        $declinedshare = $declined / $observations;
        $substantialshare = $substantial / $observations;
        $medianchange = (float) descriptive::quantile($changes, 0.5);
        $meanchange = (float) descriptive::mean($changes);

        [$status, $severity] = $this->classify(
            $improvedshare,
            $stableshare,
            $declinedshare,
            $medianchange
        );

        return [
            'status' => $status,
            'severity' => $severity,
            'observations' => $observations,
            'meanfirst' => (float) descriptive::mean($firsts),
            'meanlast' => (float) descriptive::mean($lasts),
            'meanbest' => (float) descriptive::mean($bests),
            'medianchange' => $medianchange,
            'meanchange' => $meanchange,
            'improvedshare' => $improvedshare,
            'stableshare' => $stableshare,
            'declinedshare' => $declinedshare,
            'substantialimprovementshare' => $substantialshare,
            'meanattempts' => (float) descriptive::mean($attempts),
        ];
    }

    /**
     * Apply the transparent progress-classification rules.
     *
     * @param float $improvedshare Share improved.
     * @param float $stableshare Share stable.
     * @param float $declinedshare Share declined.
     * @param float $medianchange Median change in points.
     * @return array{0: string, 1: string} Status and severity.
     */
    private function classify(
        float $improvedshare,
        float $stableshare,
        float $declinedshare,
        float $medianchange
    ): array {
        if ($improvedshare >= self::IMPROVED_SHARE_MIN && $medianchange >= self::IMPROVED_MEDIAN_MIN) {
            return ['improving', 'green'];
        }
        if ($declinedshare >= self::DECLINED_SHARE_MIN && $medianchange <= self::DECLINED_MEDIAN_MAX) {
            return ['declining', 'red'];
        }
        if ($stableshare >= self::STABLE_SHARE_MIN) {
            return ['stable', 'yellow'];
        }
        return ['mixed', 'yellow'];
    }
}
