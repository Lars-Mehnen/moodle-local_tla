<?php
// phpcs:ignoreFile
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
 * Standalone CLI check of the U-shape origin analyser (no Moodle needed).
 *
 * Run: php tests/standalone/ushape_origin_check.php
 *
 * This is not a substitute for Moodle PHPUnit/integration tests. It connects to
 * no Moodle installation and writes nothing.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
define('MOODLE_INTERNAL', true);

// Only needed to check the same exception type without bootstrapping Moodle.
if (!class_exists('invalid_parameter_exception')) {
    class invalid_parameter_exception extends InvalidArgumentException {
    }
}

$root = dirname(__DIR__, 2);
require_once($root . '/classes/statistics/descriptive.php');
require_once($root . '/classes/indicator/score_distribution_analyzer.php');
require_once($root . '/classes/indicator/ushape_origin_analyzer.php');
require_once($root . '/tests/fixtures/ushape_origin_cases.php');

use local_tla\indicator\ushape_origin_analyzer;
use local_tla\tests\ushape_origin_cases;

$assertions = 0;
$check = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};

$analyzer = new ushape_origin_analyzer();
$verbose = in_array('-v', $argv, true);

foreach (ushape_origin_cases::all() as $name => $case) {
    $r = $analyzer->analyze(
        $case['items'],
        $case['grades'],
        $case['activity'],
        $case['passshare'],
        $case['now'],
        8
    );
    if ($verbose) {
        echo "== {$name}\n";
        echo json_encode(array_diff_key($r, ['items' => 1]), JSON_PRETTY_PRINT), "\n";
    }
    foreach ($case['expect'] as $path => $expected) {
        $value = $r;
        foreach (explode('.', $path) as $key) {
            $value = $value[$key] ?? null;
        }
        $check($value === $expected, "{$name}: {$path} expected " . var_export($expected, true)
            . ', got ' . var_export($value, true));
    }
}

// Helpers.
$check(abs(ushape_origin_analyzer::auc([3, 4], [1, 2]) - 1.0) < 1e-9, 'auc separated');
$check(abs(ushape_origin_analyzer::auc([1, 2], [1, 2]) - 0.5) < 1e-9, 'auc equal');
$check(ushape_origin_analyzer::otsu_threshold([5, 5, 5]) === null, 'otsu constant');
$t = ushape_origin_analyzer::otsu_threshold([0, 5, 10, 90, 95, 100]);
$check($t > 10 && $t < 90, 'otsu split');

try {
    $analyzer->analyze([], [], [], 0.0, 0, 8);
    $check(false, 'pass share 0 must be rejected');
} catch (invalid_parameter_exception $e) {
    $check(true, '');
}

echo "OK: {$assertions} assertions\n";
