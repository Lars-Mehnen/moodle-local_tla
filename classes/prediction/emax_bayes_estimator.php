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
 * Bayesian Emax (saturating dose-response) estimator.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\prediction;

use local_tla\statistics\descriptive;


/**
 * Fit a Bayesian Emax model E = Emax * C / (EC50 + C) + Normal(0, sigma).
 *
 * This is the pure-PHP, dependency-free counterpart of the rstan/HMC workflow in
 * the reference R notebook. Instead of Hamiltonian Monte Carlo it evaluates the
 * (unnormalised) log-posterior on a dense grid over the three parameters
 * (Emax, EC50, sigma) and normalises it. With only three parameters a grid is
 * exact in the limit of fine spacing, fully deterministic (identical input =>
 * identical output, which unit tests rely on) and needs nothing beyond core PHP.
 *
 * Applied to learning analytics, C ("exposure") is a student's amount of practice
 * on an activity (here: number of completed quiz attempts) and E ("effect") is the
 * performance level they reach (normalised percentage). Emax is then the score
 * ceiling that practice tends towards, EC50 the amount of practice associated with
 * half of that ceiling and sigma the spread of students around the curve.
 *
 * The relationship is associational, not causal: students who practise more may
 * differ systematically from those who practise less. Callers surface that caveat.
 *
 * The class is database-free and side-effect-free. It receives already normalised
 * and validated (C, E) pairs and returns aggregate posterior summaries only; no
 * per-student value is ever returned.
 */
final class emax_bayes_estimator {
    /** @var int Grid points for Emax. */
    public const EMAX_STEPS = 41;
    /** @var int Grid points for EC50. */
    public const EC50_STEPS = 61;
    /** @var int Grid points for sigma. */
    public const SIGMA_STEPS = 31;
    /** @var int Points on the posterior effect curve. */
    public const CURVE_STEPS = 41;

    /** @var float Prior scale for the Emax half-normal (performance is 0..100 %). */
    public const EMAX_PRIOR_SCALE = 100.0;
    /** @var float Upper bound of the Emax grid (asymptotic performance %). */
    public const EMAX_MAX = 110.0;

    /** @var float Smallest bin count whose aggregate mean is exposed on the plot. */
    public const MIN_BIN_COUNT = 3;

    /** @var float Maximum relative width of the Emax 90% interval for a "good" fit. */
    public const GOOD_RELWIDTH_MAX = 0.75;
    /** @var float Model/observed spread ratio below which the fit is "poor". */
    public const POOR_SPREAD_RATIO_LO = 0.70;
    /** @var float Model/observed spread ratio above which the fit is "poor". */
    public const POOR_SPREAD_RATIO_HI = 1.43;
    /** @var float Below this share of explained variance there is no clear dose-response. */
    public const FLAT_EXPLAINED_MAX = 0.10;

    /**
     * Fit the Emax model to per-observation exposure/effect pairs.
     *
     * Each observation is one (student, activity) pair: 'c' is the exposure
     * (>= 0, e.g. number of completed attempts) and 'e' the effect (a normalised
     * performance percentage in [0, 100]).
     *
     * Below $minobservations pairs no fit is attempted and status is 'unknown'.
     *
     * @param array<int, array{c: float|int, e: float|int}> $observations
     * @param int $minobservations Minimum observations for a fit (>= 1).
     * @return array{
     *     status: string,
     *     severity: string,
     *     fitquality: string|null,
     *     observations: int,
     *     emax: array{median: float|null, lo: float|null, hi: float|null, mean: float|null},
     *     ec50: array{median: float|null, lo: float|null, hi: float|null, mean: float|null},
     *     sigma: array{median: float|null, lo: float|null, hi: float|null, mean: float|null},
     *     explainedvariance: float|null,
     *     curve: array<int, array{c: float, lo: float, med: float, hi: float}>,
     *     ppc: array{obssd: float|null, repsdmedian: float|null, repsdlo: float|null,
     *                repsdhi: float|null, pvalue: float|null},
     *     bins: array<int, array{c: float, meaneffect: float, count: int}>,
     *     exposuremax: float|null
     * }
     * @throws \invalid_parameter_exception If input is invalid.
     */
    public function estimate(array $observations, int $minobservations): array {
        if ($minobservations < 1) {
            throw new \invalid_parameter_exception('Minimum observations must be positive.');
        }

        $cvals = [];
        $evals = [];
        foreach ($observations as $obs) {
            foreach (['c', 'e'] as $key) {
                if (!isset($obs[$key]) || (!is_int($obs[$key]) && !is_float($obs[$key]))) {
                    throw new \invalid_parameter_exception("Missing or non-numeric '{$key}'.");
                }
            }
            $c = (float) $obs['c'];
            $e = (float) $obs['e'];
            if (!is_finite($c) || $c < 0.0) {
                throw new \invalid_parameter_exception('Exposure must be finite and non-negative.');
            }
            if (!is_finite($e) || $e < 0.0 || $e > 100.0) {
                throw new \invalid_parameter_exception('Effect must be finite within [0, 100].');
            }
            $cvals[] = $c;
            $evals[] = $e;
        }

        $n = count($cvals);
        if ($n < $minobservations) {
            return self::unknown($n);
        }

        $cmax = max($cvals);
        // Without variation in exposure the saturating shape is not identifiable.
        if ($cmax <= 0.0 || (max($cvals) - min($cvals)) < 1e-9) {
            return self::unknown($n);
        }

        $sde = (float) descriptive::stddev($evals);
        if (!is_finite($sde)) {
            $sde = 0.0;
        }

        // Parameter grids. Linear spacing keeps the cell measure constant so it.
        // Cancels in the posterior normalisation.
        $emaxgrid = self::linspace(0.0, self::EMAX_MAX, self::EMAX_STEPS);
        $ec50grid = self::linspace(0.1, max(1.0, $cmax) * 2.0, self::EC50_STEPS);
        $sigmahi = min(60.0, max(5.0, 1.5 * $sde));
        $sigmagrid = self::linspace(0.5, $sigmahi, self::SIGMA_STEPS);

        // Prior scales (weakly informative, adapted to the data scale, mirroring.
        // The reference model's intent: Emax ~ HalfNormal, EC50 ~ HalfNormal,.
        // Sigma ~ Exponential).
        $ec50scale = max(1.0, $cmax);
        $sigmamean = max(1.0, $sde);

        $sumee = 0.0;
        foreach ($evals as $e) {
            $sumee += $e * $e;
        }

        // Grid evaluation. For a fixed EC50 the ratio r_i = C_i / (EC50 + C_i) is.
        // Constant, so the Gaussian sum of squares is quadratic in Emax:.
        // S2(Emax) = sum e^2 - 2 Emax * sum(e r) + Emax^2 * sum(r^2).
        // This removes the inner per-observation loop from the triple grid.
        $maxlog = -INF;
        $logpost = [];
        $varr = [];
        for ($j = 0; $j < self::EC50_STEPS; $j++) {
            $ec50 = $ec50grid[$j];
            $sumr = 0.0;
            $sumrr = 0.0;
            $sumer = 0.0;
            for ($i = 0; $i < $n; $i++) {
                $r = $cvals[$i] / ($ec50 + $cvals[$i]);
                $sumr += $r;
                $sumrr += $r * $r;
                $sumer += $evals[$i] * $r;
            }
            $varr[$j] = max(0.0, $sumrr / $n - ($sumr / $n) * ($sumr / $n));
            $lpec50 = -0.5 * ($ec50 / $ec50scale) * ($ec50 / $ec50scale);

            for ($a = 0; $a < self::EMAX_STEPS; $a++) {
                $emax = $emaxgrid[$a];
                $s2 = $sumee - 2.0 * $emax * $sumer + $emax * $emax * $sumrr;
                if ($s2 < 0.0) {
                    $s2 = 0.0;
                }
                $lpemax = -0.5 * ($emax / self::EMAX_PRIOR_SCALE) * ($emax / self::EMAX_PRIOR_SCALE);

                for ($s = 0; $s < self::SIGMA_STEPS; $s++) {
                    $sigma = $sigmagrid[$s];
                    $loglik = -$n * log($sigma) - $s2 / (2.0 * $sigma * $sigma);
                    $lpsigma = -$sigma / $sigmamean;
                    $lp = $loglik + $lpemax + $lpec50 + $lpsigma;
                    $logpost[$a][$j][$s] = $lp;
                    if ($lp > $maxlog) {
                        $maxlog = $lp;
                    }
                }
            }
        }

        // Convert to normalised weights (subtract the max for numerical stability).
        $weights = [];
        $total = 0.0;
        // Marginal accumulators.
        $wemax = array_fill(0, self::EMAX_STEPS, 0.0);
        $wec50 = array_fill(0, self::EC50_STEPS, 0.0);
        $wsigma = array_fill(0, self::SIGMA_STEPS, 0.0);
        // 2D (Emax, EC50) weights for the effect curve; predictive accumulators.
        $w2d = [];
        $repsdvals = [];
        $repsdw = [];
        for ($a = 0; $a < self::EMAX_STEPS; $a++) {
            for ($j = 0; $j < self::EC50_STEPS; $j++) {
                $cell2d = 0.0;
                for ($s = 0; $s < self::SIGMA_STEPS; $s++) {
                    $w = exp($logpost[$a][$j][$s] - $maxlog);
                    $weights[$a][$j][$s] = $w;
                    $total += $w;
                    $cell2d += $w;
                    $wemax[$a] += $w;
                    $wec50[$j] += $w;
                    $wsigma[$s] += $w;

                    // Posterior-predictive spread: expected SD of replicated data.
                    // For this parameter cell = sqrt(Var_i(mu_i) + sigma^2), where.
                    // Var_i(mu_i) = Emax^2 * Var_i(r_i).
                    $varmu = $emaxgrid[$a] * $emaxgrid[$a] * $varr[$j];
                    $repsdvals[] = sqrt($varmu + $sigmagrid[$s] * $sigmagrid[$s]);
                    $repsdw[] = $w;
                }
                $w2d[$a][$j] = $cell2d;
            }
        }

        $emaxsummary = self::summary($emaxgrid, $wemax);
        $ec50summary = self::summary($ec50grid, $wec50);
        $sigmasummary = self::summary($sigmagrid, $wsigma);

        // Posterior effect curve with an 80% credible band (10th/50th/90th).
        // The grid is one point per whole attempt so the category axis reads.
        // Cleanly and aligns with the aggregate per-attempt means.
        $curvegrid = self::integer_grid($cmax);
        $curve = [];
        foreach ($curvegrid as $cx) {
            $muvals = [];
            $muw = [];
            for ($a = 0; $a < self::EMAX_STEPS; $a++) {
                for ($j = 0; $j < self::EC50_STEPS; $j++) {
                    $muvals[] = $emaxgrid[$a] * $cx / ($ec50grid[$j] + $cx);
                    $muw[] = $w2d[$a][$j];
                }
            }
            $curve[] = [
                'c' => $cx,
                'lo' => self::weighted_quantile($muvals, $muw, 0.10),
                'med' => self::weighted_quantile($muvals, $muw, 0.50),
                'hi' => self::weighted_quantile($muvals, $muw, 0.90),
            ];
        }

        // Posterior predictive check on the spread of the effect.
        $obssd = $sde;
        $repsdmedian = self::weighted_quantile($repsdvals, $repsdw, 0.50);
        $ppcpvalue = self::ppc_pvalue($repsdvals, $repsdw, $obssd, $n);
        $ppc = [
            'obssd' => $obssd,
            'repsdmedian' => $repsdmedian,
            'repsdlo' => self::weighted_quantile($repsdvals, $repsdw, 0.10),
            'repsdhi' => self::weighted_quantile($repsdvals, $repsdw, 0.90),
            'pvalue' => $ppcpvalue,
        ];

        // Share of the effect variance the fitted curve explains (a pseudo-R^2):.
        // 1 - residual/total. Near zero means practice barely predicts the result,.
        // I.e. there is no clear dose-response however tidily the curve is drawn.
        $explained = $obssd > 1e-9
            ? max(0.0, min(1.0, 1.0 - ($sigmasummary['median'] * $sigmasummary['median'])
                / ($obssd * $obssd)))
            : 0.0;

        [$fitquality, $severity] = self::classify_fit($emaxsummary, $repsdmedian, $obssd, $explained);

        return [
            'status' => 'estimated',
            'severity' => $severity,
            'fitquality' => $fitquality,
            'observations' => $n,
            'emax' => $emaxsummary,
            'ec50' => $ec50summary,
            'sigma' => $sigmasummary,
            'explainedvariance' => $explained,
            'curve' => $curve,
            'ppc' => $ppc,
            'bins' => self::bin_means($cvals, $evals),
            'exposuremax' => $cmax,
        ];
    }

    /**
     * Build the "not enough data" result.
     *
     * @param int $n Observation count.
     * @return array
     */
    private static function unknown(int $n): array {
        $empty = ['median' => null, 'lo' => null, 'hi' => null, 'mean' => null];
        return [
            'status' => 'unknown',
            'severity' => 'unknown',
            'fitquality' => null,
            'observations' => $n,
            'emax' => $empty,
            'ec50' => $empty,
            'sigma' => $empty,
            'explainedvariance' => null,
            'curve' => [],
            'ppc' => [
                'obssd' => null,
                'repsdmedian' => null,
                'repsdlo' => null,
                'repsdhi' => null,
                'pvalue' => null,
            ],
            'bins' => [],
            'exposuremax' => null,
        ];
    }

    /**
     * Aggregate mean effect per integer exposure value, honouring a small-group
     * threshold so no near-individual value is exposed on the plot.
     *
     * @param array<int, float> $cvals Exposure values.
     * @param array<int, float> $evals Effect values.
     * @return array<int, array{c: float, meaneffect: float, count: int}>
     */
    private static function bin_means(array $cvals, array $evals): array {
        $sum = [];
        $count = [];
        foreach ($cvals as $i => $c) {
            $key = (int) round($c);
            $sum[$key] = ($sum[$key] ?? 0.0) + $evals[$i];
            $count[$key] = ($count[$key] ?? 0) + 1;
        }
        ksort($sum);
        $bins = [];
        foreach ($sum as $key => $s) {
            if ($count[$key] < self::MIN_BIN_COUNT) {
                continue;
            }
            $bins[] = [
                'c' => (float) $key,
                'meaneffect' => $s / $count[$key],
                'count' => $count[$key],
            ];
        }
        return $bins;
    }

    /**
     * Decide the fit quality and the (fit-reliability, not teaching-quality) severity.
     *
     * @param array{median: float|null, lo: float|null, hi: float|null} $emaxsummary
     * @param float $repsdmedian Posterior median of the model's predicted result spread.
     * @param float $obssd Observed standard deviation of the effect.
     * @param float $explained Share of the effect variance the curve explains (0..1).
     * @return array{0: string, 1: string} Fit quality and severity.
     */
    private static function classify_fit(
        array $emaxsummary,
        float $repsdmedian,
        float $obssd,
        float $explained
    ): array {
        // A model spread grossly different from the observed spread means the.
        // Emax + Gaussian model does not describe this course's data well. A ratio.
        // (unlike a posterior-predictive tail probability) does not collapse to a.
        // Verdict as the sample size grows, so a good fit stays "good" at any N.
        $ratio = ($obssd > 1e-9) ? $repsdmedian / $obssd : INF;
        if ($ratio < self::POOR_SPREAD_RATIO_LO || $ratio > self::POOR_SPREAD_RATIO_HI) {
            return ['poor', 'red'];
        }

        // The curve may fit the spread fine yet explain almost none of the.
        // Variation: practice does not predict performance. Report that plainly.
        // Instead of a tidy-but-meaningless curve with EC50 pinned at zero.
        if ($explained < self::FLAT_EXPLAINED_MAX) {
            return ['flat', 'unknown'];
        }

        $median = $emaxsummary['median'];
        $lo = $emaxsummary['lo'];
        $hi = $emaxsummary['hi'];
        $relwidth = ($median !== null && $median > 1e-9 && $lo !== null && $hi !== null)
            ? ($hi - $lo) / $median
            : INF;

        if ($relwidth <= self::GOOD_RELWIDTH_MAX) {
            return ['good', 'green'];
        }
        return ['weak', 'yellow'];
    }

    /**
     * Summarise a marginal posterior (median plus a 5th/95th credible interval).
     *
     * @param array<int, float> $values Grid values.
     * @param array<int, float> $weights Marginal weights.
     * @return array{median: float, lo: float, hi: float, mean: float}
     */
    private static function summary(array $values, array $weights): array {
        $total = array_sum($weights);
        $mean = 0.0;
        if ($total > 0.0) {
            foreach ($values as $i => $v) {
                $mean += $v * $weights[$i];
            }
            $mean /= $total;
        }
        return [
            'median' => self::weighted_quantile($values, $weights, 0.50),
            'lo' => self::weighted_quantile($values, $weights, 0.05),
            'hi' => self::weighted_quantile($values, $weights, 0.95),
            'mean' => $mean,
        ];
    }

    /**
     * Weighted quantile via linear interpolation of the weighted CDF.
     *
     * @param array<int, float> $values Values (need not be sorted).
     * @param array<int, float> $weights Non-negative weights, same length.
     * @param float $q Quantile in [0, 1].
     * @return float Interpolated quantile (0.0 if there is no positive weight).
     */
    private static function weighted_quantile(array $values, array $weights, float $q): float {
        $total = array_sum($weights);
        if ($total <= 0.0) {
            return 0.0;
        }

        $idx = array_keys($values);
        usort($idx, static fn(int $x, int $y): int => $values[$x] <=> $values[$y]);

        $target = $q * $total;
        $cum = 0.0;
        $prevval = $values[$idx[0]];
        $prevcum = 0.0;
        foreach ($idx as $i) {
            $w = $weights[$i];
            $newcum = $cum + $w;
            if ($newcum >= $target) {
                $span = $newcum - $cum;
                $frac = $span > 0.0 ? ($target - $cum) / $span : 0.0;
                return $prevval + ($values[$i] - $prevval) * $frac;
            }
            $cum = $newcum;
            $prevval = $values[$i];
            $prevcum = $newcum;
        }
        return $values[$idx[count($idx) - 1]];
    }

    /**
     * Posterior-predictive p-value for the observed effect spread.
     *
     * For each posterior parameter cell the model implies a total result spread
     * sigmatot; a replicate dataset of size n has a sample SD that scatters around
     * sigmatot with an (approximate) standard error sigmatot / sqrt(2(n-1)).
     * Integrating P(replicate SD >= observed SD) over both the parameter posterior
     * and that sampling variability yields a p-value that stays near 0.5 for a
     * good fit at any sample size and only becomes extreme for a genuine spread
     * mismatch.
     *
     * @param array<int, float> $sigmatot Per-cell model spread sqrt(Var(mu)+sigma^2).
     * @param array<int, float> $weights Non-negative posterior weights.
     * @param float $obssd Observed standard deviation of the effect.
     * @param int $n Number of observations.
     * @return float Probability in [0, 1] (0.5 if there is no positive weight).
     */
    private static function ppc_pvalue(array $sigmatot, array $weights, float $obssd, int $n): float {
        $total = array_sum($weights);
        if ($total <= 0.0) {
            return 0.5;
        }
        $dfscale = sqrt(2.0 * (float) max(1, $n - 1));
        $acc = 0.0;
        foreach ($sigmatot as $i => $st) {
            $sdsd = $st / $dfscale;
            if ($sdsd <= 1e-12) {
                $acc += $st >= $obssd ? $weights[$i] : 0.0;
                continue;
            }
            // P(replicate SD >= observed SD), replicate SD ~ Normal(sigmatot, sdsd).
            $acc += $weights[$i] * (1.0 - self::normal_cdf(($obssd - $st) / $sdsd));
        }
        return $acc / $total;
    }

    /**
     * Standard normal cumulative distribution function.
     *
     * @param float $x Value.
     * @return float Phi(x) in [0, 1].
     */
    private static function normal_cdf(float $x): float {
        return 0.5 * (1.0 + self::erf($x / sqrt(2.0)));
    }

    /**
     * Error function via the Abramowitz & Stegun 7.1.26 approximation
     * (absolute error <= 1.5e-7), sufficient for a diagnostic p-value.
     *
     * @param float $x Value.
     * @return float erf(x).
     */
    private static function erf(float $x): float {
        $sign = $x < 0.0 ? -1.0 : 1.0;
        $x = abs($x);
        $t = 1.0 / (1.0 + 0.3275911 * $x);
        $poly = $t * (0.254829592 + $t * (-0.284496736 + $t * (1.421413741
            + $t * (-1.453152027 + $t * 1.061405429))));
        return $sign * (1.0 - $poly * exp(-$x * $x));
    }

    /**
     * Whole-number exposure grid 0, 1, ... ceil(max), capped so the curve stays
     * a sensible length for very large exposure ranges.
     *
     * @param float $cmax Largest observed exposure (> 0).
     * @return array<int, float>
     */
    private static function integer_grid(float $cmax): array {
        $top = (int) ceil($cmax);
        if ($top < 1) {
            $top = 1;
        }
        if ($top > self::CURVE_STEPS - 1) {
            // Fall back to evenly spaced points if the range is unusually wide.
            return self::linspace(0.0, $cmax, self::CURVE_STEPS);
        }
        $out = [];
        for ($i = 0; $i <= $top; $i++) {
            $out[] = (float) $i;
        }
        return $out;
    }

    /**
     * Evenly spaced values from $from to $to inclusive.
     *
     * @param float $from Start.
     * @param float $to End (>= $from).
     * @param int $steps Number of points (>= 2).
     * @return array<int, float>
     */
    private static function linspace(float $from, float $to, int $steps): array {
        $out = [];
        $den = max(1, $steps - 1);
        for ($i = 0; $i < $steps; $i++) {
            $out[] = $from + ($to - $from) * $i / $den;
        }
        return $out;
    }
}
