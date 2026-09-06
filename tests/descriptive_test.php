<?php
// This file is part of the local_tla plugin.
namespace local_tla;

use local_tla\statistics\descriptive;
use local_tla\forecast\forecaster;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(descriptive::class)]
#[CoversClass(forecaster::class)]
final class descriptive_test extends \advanced_testcase {

    public function test_basic_stats(): void {
        $x = [2, 4, 4, 4, 5, 5, 7, 9];
        $this->assertEqualsWithDelta(5.0, descriptive::mean($x), 1e-9);
        $this->assertEqualsWithDelta(4.5, descriptive::median($x), 1e-9);
        // Stichproben-Varianz (n-1) dieser Reihe = 4.571428...
        $this->assertEqualsWithDelta(4.5714285714, descriptive::variance($x), 1e-6);
        $this->assertEqualsWithDelta(2.1380899353, descriptive::stddev($x), 1e-6);
    }

    public function test_quantile_type7(): void {
        $x = [1, 2, 3, 4, 5];
        $this->assertEqualsWithDelta(1.0, descriptive::quantile($x, 0.0), 1e-9);
        $this->assertEqualsWithDelta(3.0, descriptive::quantile($x, 0.5), 1e-9);
        $this->assertEqualsWithDelta(5.0, descriptive::quantile($x, 1.0), 1e-9);
        $this->assertEqualsWithDelta(2.0, descriptive::quantile($x, 0.25), 1e-9);
    }

    public function test_edge_cases_return_null(): void {
        $this->assertNull(descriptive::mean([]));
        $this->assertNull(descriptive::variance([5]));
        $this->assertNull(descriptive::correlation([1], [1]));
    }

    public function test_correlation_and_regression(): void {
        $x = [1, 2, 3, 4, 5];
        $y = [2, 4, 6, 8, 10]; // y = 2x
        $this->assertEqualsWithDelta(1.0, descriptive::correlation($x, $y), 1e-9);
        $reg = descriptive::linear_regression($x, $y);
        $this->assertEqualsWithDelta(2.0, $reg['slope'], 1e-9);
        $this->assertEqualsWithDelta(0.0, $reg['intercept'], 1e-9);
        $this->assertEqualsWithDelta(1.0, $reg['r2'], 1e-9);
    }

    public function test_moving_average_and_ema(): void {
        $this->assertEquals([2.0, 3.0, 4.0], descriptive::moving_average([1, 2, 3, 4, 5], 3));
        $ema = descriptive::ema([10, 10, 10], 0.5);
        $this->assertEqualsWithDelta(10.0, end($ema), 1e-9);
    }

    public function test_forecast_linear_trend_selected(): void {
        // Klar linearer Verlauf -> linear_trend sollte naive schlagen.
        $h = range(1, 30);
        $fc = forecaster::forecast($h, 5, 10);
        $this->assertContains($fc['model'], ['linear_trend', 'naive', 'moving_average',
            'exponential_smoothing', 'seasonal_naive']);
        $this->assertCount(5, $fc['point']);
        $this->assertCount(5, $fc['lower']);
        $this->assertCount(5, $fc['upper']);
        // Punktprognose muss zwischen unterer und oberer Grenze liegen.
        foreach ($fc['point'] as $i => $p) {
            $this->assertLessThanOrEqual($fc['upper'][$i] + 1e-9, $p);
            $this->assertGreaterThanOrEqual($fc['lower'][$i] - 1e-9, $p);
        }
    }

    public function test_mad(): void {
        // Test with a simple known series
        $x = [1, 2, 3, 4, 5];
        $mad = descriptive::mad($x);
        // For [1,2,3,4,5], median is 3, deviations are [2,1,0,1,2], median of deviations is 1
        $this->assertEqualsWithDelta(1.0, $mad, 1e-9);
    }

    public function test_skew(): void {
        // Test with a symmetric series where skew should be close to 0
        $x = [1, 2, 3, 3, 3, 4, 5];
        $skew = descriptive::skew($x);
        // For symmetric distribution, skew should be near 0
        $this->assertEqualsWithDelta(0.0, $skew, 1e-9);
    }

    public function test_kurtosis(): void {
        // Test with finite numeric result and edge cases
        $x = [1, 2, 3, 4, 5];
        $kurt = descriptive::kurtosis($x);
        $this->assertTrue(is_finite($kurt));

        // Test with constant values (should return 0)
        $y = [5, 5, 5, 5];
        $kurt2 = descriptive::kurtosis($y);
        $this->assertEqualsWithDelta(0.0, $kurt2, 1e-9);
    }

    public function test_histogram(): void {
        // Test histogram properties
        $x = [1, 2, 3, 4, 5];
        $bins = 3;
        
        $hist = descriptive::histogram($x, $bins);
        $this->assertIsArray($hist);
        $this->assertCount($bins, $hist);
        
        // Sum of all frequencies should equal count of input values
        $sum = array_sum($hist);
        $this->assertEquals(count($x), $sum);
    }

    public function test_weighted_linear_regression(): void {
        // Test with exactly linear series
        $x = [1, 2, 3, 4, 5];
        $y = [2, 4, 6, 8, 10]; // y = 2x
        $weights = [1, 1, 1, 1, 1];
        
        $reg = descriptive::weighted_linear_regression($x, $y, $weights);
        $this->assertIsArray($reg);
        $this->assertArrayHasKey('slope', $reg);
        $this->assertArrayHasKey('intercept', $reg);
        // Slope should be 2 and intercept should be 0 for y = 2x
        $this->assertEqualsWithDelta(2.0, $reg['slope'], 1e-9);
        $this->assertEqualsWithDelta(0.0, $reg['intercept'], 1e-9);
    }

    public function test_calculate(): void {
        // Test expected keys and correct values
        $x = [1, 2, 3, 4, 5];
        $stats = descriptive::calculate($x);
        
        $this->assertIsArray($stats);
        $this->assertArrayHasKey('count', $stats);
        $this->assertArrayHasKey('mean', $stats);
        $this->assertArrayHasKey('median', $stats);
        $this->assertArrayHasKey('stdev', $stats);
        $this->assertArrayHasKey('min', $stats);
        $this->assertArrayHasKey('max', $stats);
        
        // Check if values are correct
        $this->assertEqualsWithDelta(3.0, $stats['mean'], 1e-9);
        $this->assertEqualsWithDelta(3.0, $stats['median'], 1e-9);
        $this->assertEqualsWithDelta(sqrt(2.5), $stats['stdev'], 1e-9);
        $this->assertEquals(1, $stats['min']);
        $this->assertEquals(5, $stats['max']);
    }

    public function test_forecast_methods(): void {
        // Test individual forecast methods
        $values = [1, 2, 3, 4, 5];
        
        // Naive forecast
        $naive = forecaster::naive($values, 3);
        $this->assertCount(3, $naive);
        $this->assertEqualsWithDelta(5.0, $naive[0], 1e-9);
        $this->assertEqualsWithDelta(5.0, $naive[1], 1e-9);
        $this->assertEqualsWithDelta(5.0, $naive[2], 1e-9);
        
        // Seasonal naive forecast 
        $seasonal = forecaster::seasonal_naive($values, 3, 2);
        $this->assertCount(3, $seasonal);
        $this->assertEquals([4, 5, 5], $seasonal);
        
        // Moving average forecast
        $ma = forecaster::moving_average($values, 3, 3);
        $this->assertCount(3, $ma);
        $this->assertEquals([4.0, 4.0, 4.0], $ma);
        
        // Exponential smoothing
        $es = forecaster::exponential_smoothing($values, 3, 0.5);
        $this->assertCount(3, $es);
        foreach ($es as $value) {
            $this->assertEqualsWithDelta(4.0625, $value, 1e-9);
        }
        
        // Linear trend
        $lt = forecaster::linear_trend($values, 3);
        $this->assertCount(3, $lt);
        foreach ([6.0, 7.0, 8.0] as $i => $expected) {
            $this->assertEqualsWithDelta($expected, $lt[$i], 1e-9);
        }
    }

    public function test_backtest(): void {
        // Test backtest properties
        $history = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10];
        $horizon = 2;
        
        $error = forecaster::backtest($history, $horizon, 7, 'naive');
        $this->assertIsNumeric($error);
        $this->assertNotEquals(PHP_FLOAT_MAX, $error);
        $this->assertGreaterThanOrEqual(0.0, $error); // Error should not be negative
        $this->assertEqualsWithDelta(1.5, $error, 1e-9);
    }

    public function test_invalid_inputs(): void {
        // Test with invalid inputs
        $this->assertNull(descriptive::mad([]));
        $this->assertEqualsWithDelta(0.0, descriptive::mad([1]), 1e-9);
        $this->assertNull(descriptive::skew([1, 2]));
        $this->assertNull(descriptive::kurtosis([1, 2, 3]));
        
        $this->assertNull(descriptive::histogram([], 5));
        $this->assertNull(descriptive::histogram([1, 2, 3], 0));
        
        $this->assertNull(descriptive::weighted_linear_regression([1, 2], [1, 2], [1]));
        
        // Test forecast invalid inputs
        $result = forecaster::forecast([], 1);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('point', $result);
        $this->assertCount(1, $result['point']);
        
        $error = forecaster::backtest([1, 2], 5, 7, 'naive');
        $this->assertSame(PHP_FLOAT_MAX, $error);
    }
}
