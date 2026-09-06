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
 * Forecaster class for local_tla plugin.
 *
 * @package    local_tla
 * @copyright  2023 Your Name <your.email@example.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\forecast;

defined('MOODLE_INTERNAL') || die();

/**
 * Forecaster class for local_tla plugin.
 */
class forecaster {
    
    /**
     * Naive forecast (last value).
     *
     * @param array $values Array of historical values
     * @param int $steps Number of steps to forecast
     * @return array Forecasted values
     */
    public static function naive($values, $steps = 1) {
        if (!is_array($values) || empty($values)) {
            return array_fill(0, $steps, 0);
        }

        $lastvalue = end($values);
        return array_fill(0, $steps, $lastvalue);
    }
    
    /**
     * Seasonal naive forecast.
     *
     * @param array $values Array of historical values
     * @param int $steps Number of steps to forecast
     * @param int $seasonlength Season length
     * @return array Forecasted values
     */
    public static function seasonal_naive($values, $steps = 1, $seasonlength = 7) {
        if (!is_array($values) || empty($values)) {
            return array_fill(0, $steps, 0);
        }

        $result = array();
        $count = count($values);
        
        for ($i = 0; $i < $steps; $i++) {
            $index = $count - $seasonlength + $i;
            if ($index >= 0 && $index < $count) {
                $result[] = $values[$index];
            } else {
                // If we don't have enough data for seasonality, fall back to naive
                $result[] = end($values);
            }
        }
        
        return $result;
    }
    
    /**
     * Moving average forecast.
     *
     * @param array $values Array of historical values
     * @param int $steps Number of steps to forecast
     * @param int $period Period for moving average
     * @return array Forecasted values
     */
    public static function moving_average($values, $steps = 1, $period = 3) {
        if (!is_array($values) || empty($values)) {
            return array_fill(0, $steps, 0);
        }

        // For multi-step forecast, we use last period's average
        $last_values = array_slice($values, -$period);
        $sum = array_sum($last_values);
        $forecastvalue = $sum / $period;
        
        return array_fill(0, $steps, $forecastvalue);
    }
    
    /**
     * Exponential smoothing forecast.
     *
     * @param array $values Array of historical values
     * @param int $steps Number of steps to forecast
     * @param float $alpha Smoothing factor (0 < alpha < 1)
     * @return array Forecasted values
     */
    public static function exponential_smoothing($values, $steps = 1, $alpha = 0.3) {
        if (!is_array($values) || empty($values)) {
            return array_fill(0, $steps, 0);
        }
        
        // Calculate EMA for all values
        $forecast = $values[0];
        
        for ($i = 1; $i < count($values); $i++) {
            $forecast = $alpha * $values[$i] + (1 - $alpha) * $forecast;
        }

        // For multi-step forecast, we assume the same value
        return array_fill(0, $steps, $forecast);
    }
    
    /**
     * Linear trend forecast.
     *
     * @param array $values Array of historical values
     * @param int $steps Number of steps to forecast
     * @return array Forecasted values
     */
    public static function linear_trend($values, $steps = 1) {
        if (!is_array($values) || empty($values)) {
            return array_fill(0, $steps, 0);
        }

        $n = count($values);
        
        // Use the existing descriptive statistics to calculate linear regression
        $x = range(1, $n);
        $regression = \local_tla\statistics\descriptive::linear_regression($x, $values);
        
        if ($regression === null) {
            // Fallback to simple naive forecast if regression fails
            return self::naive($values, $steps);
        }
        
        $slope = $regression['slope'];
        $intercept = $regression['intercept'];
        
        // Forecast future values using the linear trend
        $forecast = array();
        for ($i = 1; $i <= $steps; $i++) {
            $forecast[] = $intercept + $slope * ($n + $i);
        }
        
        return $forecast;
    }

    /**
     * Simple linear regression forecast (backward compatible).
     *
     * @param array $values Array of historical values
     * @param int $steps Number of steps to forecast
     * @return array|bool Forecasted values or false if invalid input
     */
    public static function linear($values, $steps = 1) {
        return self::linear_trend($values, $steps);
    }
    
    /**
     * Get forecast statistics.
     *
     * @param array $values Array of historical values
     * @return array Forecast statistics
     */
    public static function get_statistics($values) {
        if (!is_array($values) || empty($values)) {
            return array();
        }
        
        $statistics = \local_tla\statistics\descriptive::calculate($values);
        
        // Add additional forecast statistics
        $statistics['forecast_linear'] = self::linear($values, 1);
        $statistics['forecast_moving_average'] = self::moving_average($values, 1, 3);
        $statistics['forecast_exponential'] = self::exponential_smoothing($values, 1, 0.3);
        
        return $statistics;
    }
    
    /**
     * Get forecast for given history using automatic model selection based on backtest error.
     *
     * @param array $history Array of historical values
     * @param int $horizon Number of steps to forecast
     * @param int $seasonlength Season length
     * @return array Forecast result with model, point, lower and upper bounds
     */
    public static function forecast(
        array $history,
        int $horizon,
        int $seasonlength = 7
    ): array {
        if (empty($history) || $horizon <= 0) {
            return [
                'model' => 'naive',
                'point' => array_fill(0, $horizon, 0),
                'lower' => array_fill(0, $horizon, 0),
                'upper' => array_fill(0, $horizon, 0)
            ];
        }
        
        // Model names and functions
        $models = [
            'naive' => 'naive',
            'seasonal_naive' => 'seasonal_naive',
            'moving_average' => 'moving_average', 
            'exponential_smoothing' => 'exponential_smoothing',
            'linear_trend' => 'linear_trend'
        ];
        
        // Try to backtest all models and select the best one
        $bestmodel = 'naive';
        $besterror = PHP_FLOAT_MAX;
        
        foreach ($models as $name => $method) {
            $error = self::backtest($history, $horizon, $seasonlength, $method);
            if ($error < $besterror) {
                $besterror = $error;
                $bestmodel = $name;
            }
        }
        
        // Calculate forecast with best model
        $point = self::$bestmodel($history, $horizon, $seasonlength);
        
        // Create lower and upper bounds using standard error  
        $residuals = self::calculate_residuals($history, $horizon, $seasonlength, $bestmodel);
        $stddev = \local_tla\statistics\descriptive::stddev($residuals);
        
        if (is_null($stddev)) {
            $stddev = 0;
        }
        
        // Calculate error bounds using 2 standard deviations (95% confidence)
        $errorbound = 2 * $stddev;
        
        $lower = array();
        $upper = array();
        foreach ($point as $value) {
            $lower[] = $value - $errorbound;
            $upper[] = $value + $errorbound;
        }
        
        return [
            'model' => $bestmodel,
            'point' => $point,
            'lower' => $lower,
            'upper' => $upper
        ];
    }
    
    /**
     * Backtest a model on historical data to compute error.
     *
     * @param array $history Array of historical values
     * @param int $horizon Number of steps to forecast
     * @param int $seasonlength Season length
     * @param string $method Forecast method name
     * @return float Error (MAE)
     */
    public static function backtest(
        array $history,
        int $horizon,
        int $seasonlength = 7,
        string $method = 'naive'
    ): float {
        if (count($history) < 2 * $horizon) {
            return PHP_FLOAT_MAX;
        }
        
        // Take the last part of history for training
        $traindata = array_slice($history, 0, - $horizon);
        $actual = array_slice($history, -$horizon);
        
        // Forecast using this model and calculate error
        $forecast = self::$method($traindata, $horizon, $seasonlength);
        
        // Calculate MAE
        if (empty($forecast) || count($forecast) != count($actual)) {
            return PHP_FLOAT_MAX;
        }
        
        $sumerror = 0;
        for ($i = 0; $i < count($forecast); $i++) {
            $sumerror += abs($forecast[$i] - $actual[$i]);
        }
        
        return $sumerror / count($forecast);
    }
    
    /**
     * Calculate residuals for a model.
     *
     * @param array $history Array of historical values
     * @param int $horizon Number of steps to forecast
     * @param int $seasonlength Season length
     * @param string $method Forecast method name
     * @return array Residuals
     */
    private static function calculate_residuals(
        array $history,
        int $horizon,
        int $seasonlength = 7,
        string $method = 'naive'
    ): array {
        if (count($history) < 2 * $horizon) {
            return [];
        }
        
        // Take the last part of history for training
        $traindata = array_slice($history, 0, - $horizon);
        $actual = array_slice($history, -$horizon);
        
        // Forecast using this model
        $forecast = self::$method($traindata, $horizon, $seasonlength);
        
        // Calculate residuals (actual - forecast)
        $residuals = array();
        for ($i = 0; $i < count($forecast); $i++) {
            $residuals[] = $actual[$i] - $forecast[$i];
        }
        
        return $residuals;
    }
}