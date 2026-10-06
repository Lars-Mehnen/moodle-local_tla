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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Descriptive statistics class for local_tla plugin.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\statistics;


/**
 * Descriptive statistics class for local_tla plugin.
 */
class descriptive {
    /**
     * Calculate mean of an array of values.
     *
     * @param array $values Array of numeric values
     * @return float|null Mean value or null if invalid input
     */
    public static function mean($values) {
        if (!is_array($values) || empty($values)) {
            return null;
        }

        $sum = array_sum($values);
        $count = count($values);

        return $sum / $count;
    }

    /**
     * Calculate median of an array of values.
     *
     * @param array $values Array of numeric values
     * @return float|null Median value or null if invalid input
     */
    public static function median($values) {
        if (!is_array($values) || empty($values)) {
            return null;
        }

        sort($values);
        $count = count($values);
        $middle = floor(($count - 1) / 2);

        if ($count % 2) {
            return $values[$middle];
        } else {
            $low = $values[$middle];
            $high = $values[$middle + 1];
            return ($low + $high) / 2;
        }
    }

    /**
     * Calculate sample variance of an array of values.
     *
     * @param array $values Array of numeric values
     * @return float|null Variance value or null if invalid input
     */
    public static function variance($values) {
        if (!is_array($values) || count($values) < 2) {
            return null;
        }

        $mean = self::mean($values);
        $sum = 0;

        foreach ($values as $value) {
            $sum += pow(($value - $mean), 2);
        }

        $variance = $sum / (count($values) - 1);
        return $variance;
    }

    /**
     * Calculate standard deviation of an array of values.
     *
     * @param array $values Array of numeric values
     * @return float|null Standard deviation or null if invalid input
     */
    public static function stddev($values) {
        if (!is_array($values) || count($values) < 2) {
            return null;
        }

        $variance = self::variance($values);
        return sqrt($variance);
    }

    /**
     * Calculate quantile of an array of values using R Type 7.
     *
     * @param array $values Array of numeric values
     * @param float $q Quantile (0-1)
     * @return float|null Quantile value or null if invalid input
     */
    public static function quantile($values, $q) {
        if (!is_array($values) || empty($values) || !is_numeric($q) || $q < 0 || $q > 1) {
            return null;
        }

        sort($values);
        $count = count($values);
        $index = ($count - 1) * $q;

        // R Type 7.
        $lower = floor($index);
        $upper = ceil($index);
        $weight = $index - $lower;

        if ($upper == $lower) {
            return $values[$lower];
        } else {
            return $values[$lower] * (1 - $weight) + $values[$upper] * $weight;
        }
    }

    /**
     * Calculate median absolute deviation.
     *
     * @param array $values Array of numeric values
     * @return float|null MAD or null if invalid input
     */
    public static function mad($values) {
        if (!is_array($values) || empty($values)) {
            return null;
        }

        $median = self::median($values);
        $deviations = [];

        foreach ($values as $value) {
            $deviations[] = abs($value - $median);
        }

        return self::median($deviations);
    }

    /**
     * Calculate skewness of an array of values.
     *
     * @param array $values Array of numeric values
     * @return float|null Skewness or null if invalid input
     */
    public static function skew($values) {
        if (!is_array($values) || count($values) < 3) {
            return null;
        }

        $count = count($values);
        $mean = self::mean($values);
        $stddev = self::stddev($values);

        if ($stddev == 0) {
            return 0;
        }

        $sum = 0;
        foreach ($values as $value) {
            $sum += pow(($value - $mean) / $stddev, 3);
        }

        $skewness = $sum / $count;
        return $skewness;
    }

    /**
     * Calculate kurtosis of an array of values.
     *
     * @param array $values Array of numeric values
     * @return float|null Kurtosis or null if invalid input
     */
    public static function kurtosis($values) {
        if (!is_array($values) || count($values) < 4) {
            return null;
        }

        $count = count($values);
        $mean = self::mean($values);
        $stddev = self::stddev($values);

        if ($stddev == 0) {
            return 0;
        }

        $sum = 0;
        foreach ($values as $value) {
            $sum += pow(($value - $mean) / $stddev, 4);
        }

        $kurtosis = $sum / $count - 3;
        return $kurtosis;
    }

    /**
     * Calculate histogram of an array of values.
     *
     * @param array $values Array of numeric values
     * @param int $bins Number of bins
     * @return array|null Histogram array or null if invalid input
     */
    public static function histogram($values, $bins = 10) {
        if (!is_array($values) || empty($values) || !is_numeric($bins) || $bins <= 0) {
            return null;
        }

        $min = min($values);
        $max = max($values);
        $range = $max - $min;

        if ($range == 0) {
            return array_fill(0, $bins, 0);
        }

        $histogram = array_fill(0, $bins, 0);

        foreach ($values as $value) {
            $bin = min(floor(($value - $min) / $range * $bins), $bins - 1);
            $histogram[$bin]++;
        }

        return $histogram;
    }

    /**
     * Calculate correlation between two arrays of values.
     *
     * @param array $x First array of numeric values
     * @param array $y Second array of numeric values
     * @return float|null Correlation or null if invalid input
     */
    public static function correlation($x, $y) {
        if (!is_array($x) || !is_array($y) || count($x) != count($y) || empty($x) || count($x) < 2) {
            return null;
        }

        $n = count($x);
        $meanx = self::mean($x);
        $meany = self::mean($y);

        $sumxy = 0;
        $sumx2 = 0;
        $sumy2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $diffx = $x[$i] - $meanx;
            $diffy = $y[$i] - $meany;

            $sumxy += $diffx * $diffy;
            $sumx2 += $diffx * $diffx;
            $sumy2 += $diffy * $diffy;
        }

        $denominator = sqrt($sumx2 * $sumy2);

        if ($denominator == 0) {
            return null;
        }

        return $sumxy / $denominator;
    }

    /**
     * Calculate moving average.
     *
     * @param array $values Array of numeric values
     * @param int $window Size of the window
     * @return array|null Moving average or null if invalid input
     */
    public static function moving_average($values, $window) {
        if (!is_array($values) || empty($values) || !is_numeric($window) || $window <= 0 || $window > count($values)) {
            return null;
        }

        $result = [];
        $count = count($values);

        for ($i = 0; $i <= $count - $window; $i++) {
            $sum = 0;
            for ($j = 0; $j < $window; $j++) {
                $sum += $values[$i + $j];
            }
            $result[] = $sum / $window;
        }

        return $result;
    }

    /**
     * Calculate exponential moving average.
     *
     * @param array $values Array of numeric values
     * @param float $alpha Smoothing factor (0-1)
     * @return array|null EMA or null if invalid input
     */
    public static function ema($values, $alpha) {
        if (!is_array($values) || empty($values) || !is_numeric($alpha) || $alpha < 0 || $alpha > 1) {
            return null;
        }

        $result = [];
        $ema = $values[0];

        for ($i = 0; $i < count($values); $i++) {
            if ($i == 0) {
                $result[] = $values[$i];
            } else {
                $ema = $alpha * $values[$i] + (1 - $alpha) * $ema;
                $result[] = $ema;
            }
        }

        return $result;
    }

    /**
     * Calculate linear regression.
     *
     * @param array $x Array of x values
     * @param array $y Array of y values
     * @return array|null Regression results or null if invalid input
     */
    public static function linear_regression($x, $y) {
        if (!is_array($x) || !is_array($y) || count($x) != count($y) || empty($x) || count($x) < 2) {
            return null;
        }

        $n = count($x);
        $sumx = array_sum($x);
        $sumy = array_sum($y);
        $sumxy = 0;
        $sumx2 = 0;

        for ($i = 0; $i < $n; $i++) {
            $sumxy += $x[$i] * $y[$i];
            $sumx2 += $x[$i] * $x[$i];
        }

        $slope = ($n * $sumxy - $sumx * $sumy) / ($n * $sumx2 - $sumx * $sumx);
        $intercept = ($sumy - $slope * $sumx) / $n;

        // Calculate R-squared.
        $sstot = 0;
        $ssreg = 0;
        $meany = self::mean($y);

        for ($i = 0; $i < $n; $i++) {
            $sstot += pow($y[$i] - $meany, 2);
            $ssreg += pow($slope * $x[$i] + $intercept - $meany, 2);
        }

        $r2 = $ssreg / $sstot;

        return [
            'slope' => $slope,
            'intercept' => $intercept,
            'r2' => $r2,
        ];
    }

    /**
     * Calculate weighted linear regression.
     *
     * @param array $x Array of x values
     * @param array $y Array of y values
     * @param array $weights Array of weights
     * @return array|null Regression results or null if invalid input
     */
    public static function weighted_linear_regression($x, $y, $weights) {
        if (
            !is_array($x) || !is_array($y) || !is_array($weights) || count($x) != count($y) ||
            count($x) != count($weights) || empty($x) || count($x) < 2
        ) {
            return null;
        }

        $sumw = array_sum($weights);
        $sumwx = 0;
        $sumwy = 0;
        $sumwxy = 0;
        $sumwxx = 0;

        for ($i = 0; $i < count($x); $i++) {
            $w = $weights[$i];
            $sumwx += $w * $x[$i];
            $sumwy += $w * $y[$i];
            $sumwxy += $w * $x[$i] * $y[$i];
            $sumwxx += $w * $x[$i] * $x[$i];
        }

        // Use normal equations to solve for slope and intercept.
        // We can also calculate it using the general form:.
        // (Σw × Σwx × x) - (Σw × Σwy).
        // Slope = -----------------------------------.
        // (Σw × Σx²) - (Σwx)².

        $numerator = ($sumw * $sumwxy) - ($sumwx * $sumwy);
        $denominator = ($sumw * $sumwxx) - ($sumwx * $sumwx);

        if ($denominator == 0) {
            return null;
        }

        $slope = $numerator / $denominator;
        $intercept = ($sumwy - $slope * $sumwx) / $sumw;

        // For R-squared, we would need to calculate a weighted R² but for simplicity we'll return basic values.
        return [
            'slope' => $slope,
            'intercept' => $intercept,
        ];
    }

    /**
     * Calculate descriptive statistics for an array of values.
     *
     * @param array $values Array of numeric values
     * @return array|bool Statistics or false if invalid input
     */
    public static function calculate($values) {
        if (!is_array($values) || empty($values)) {
            return false;
        }

        return [
            'count' => count($values),
            'mean' => self::mean($values),
            'median' => self::median($values),
            'stdev' => self::stddev($values),
            'min' => min($values),
            'max' => max($values),
        ];
    }
}
