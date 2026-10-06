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
 * Database-free developer checks. CLI only; NEVER included by the runtime.
 *
 * This is not a substitute for Moodle PHPUnit/integration tests. It connects to
 * no Moodle installation, writes no grades and creates no demonstration data.
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
require_once($root . '/classes/indicator/assessment_design_analyzer.php');
require_once($root . '/classes/indicator/score_distribution_interpreter.php');
require_once($root . '/classes/repository/assessment_design_repository.php');
require_once($root . '/tests/fixtures/assessment_design_cases.php');

use local_tla\indicator\assessment_design_analyzer;
use local_tla\indicator\score_distribution_analyzer;
use local_tla\indicator\score_distribution_interpreter;
use local_tla\repository\assessment_design_repository;
use local_tla\tests\assessment_design_cases;

$assertions = 0;
$checks = 0;
$check = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$loadstrings = static function (string $file): array {
    $string = [];
    require($file);
    return $string;
};
$en = $loadstrings($root . '/lang/en/local_tla.php');
$de = $loadstrings($root . '/lang/de/local_tla.php');
$enkeys = array_keys($en);
$dekeys = array_keys($de);
sort($enkeys);
sort($dekeys);
$check($enkeys === $dekeys, 'Language key parity');
$checks++;
echo "PASS language key parity (" . count($en) . " keys)\n";

$analyzer = new assessment_design_analyzer();
foreach (assessment_design_cases::cases() as $label => [$input, $code, $status]) {
    $before = $input;
    $result = $analyzer->analyze($input);
    $bycode = array_column($result['findings'], null, 'code');
    $check(isset($bycode[$code]), $label . ': missing ' . $code);
    $check($bycode[$code]['status'] === $status, $label . ': wrong status');
    $check($before === $input, $label . ': input changed');
    $check($result['purpose'] === 'unknown', $label . ': invented purpose');
    foreach ($result['findings'] as $finding) {
        $check(!isset($finding['severity']), 'No fairness traffic lights');
        $check(isset($en[$finding['messagekey']], $de[$finding['messagekey']]), 'Translated finding');
        $check(isset($en['ad_criterion_' . $finding['criterion']]), 'Translated criterion');
        $check(isset($en['ad_status_' . $finding['status']]), 'Translated status');
        if ($finding['actionkey'] !== null) {
            $check(isset($en[$finding['actionkey']], $de[$finding['actionkey']]), 'Translated action');
        }
    }
    $checks++;
    echo 'PASS ' . $label . "\n";
}

// Negative-boundary cases prevent overclaiming the absence of retry access.
foreach (
    [
    'delay below window' => assessment_design_cases::quiz(['timeopen' => 100, 'timeclose' => 300, 'delay1' => 199]),
    'no known opening' => assessment_design_cases::quiz(['timeclose' => 300, 'delay1' => 400]),
    'no known closing' => assessment_design_cases::quiz(['timeopen' => 100, 'delay1' => 400]),
    ] as $label => $input
) {
    $codes = array_column($analyzer->analyze($input)['findings'], 'code');
    $check(!in_array('quiz_retry_window', $codes, true), $label);
    $checks++;
    echo 'PASS ' . $label . "\n";
}
$single = $analyzer->analyze(assessment_design_cases::quiz(['attempts' => 1, 'grademethod' => 2], ['hasoverrides' => true]));
$check(!in_array('quiz_average', array_column($single['findings'], 'code'), true), 'Single attempt: no average penalty claim');
$checks++;
echo "PASS single attempt does not imply average penalty\n";

$shapes = new score_distribution_analyzer();
$interpreter = new score_distribution_interpreter();
$ceiling = $shapes->analyze(array_fill(0, 800, 100), 8);
$check($ceiling['status'] === 'ceiling' && $ceiling['severity'] === 'info', 'Ceiling alone is neutral');
foreach ([1 => 'ceiling_best', 2 => 'ceiling_retained', 3 => 'ceiling_retained', 4 => 'ceiling_last'] as $method => $code) {
    $design = ['checkedat' => 100, 'activities' => [$analyzer->analyze(assessment_design_cases::quiz(['grademethod' => $method]))]];
    $result = $interpreter->interpret([['cmid' => 101, 'analysis' => $ceiling]], $design);
    $check($result[0]['analysis']['severity'] === 'info', 'No red ceiling');
    $check($result[0]['analysis']['histogram'] === $ceiling['histogram'], 'Histogram preserved');
    $check($result[0]['analysis']['median'] === $ceiling['median'], 'Median preserved');
    $i = $result[0]['interpretation'];
    $check($i['code'] === $code, 'Correct design context');
    $check($i['historicalmatchverified'] === false, 'Historical limits');
    $check(isset($en[$i['messagekey']], $de[$i['messagekey']]), 'Interpretation translated');
    $check(isset($en[$i['recommendationkey']], $de[$i['recommendationkey']]), 'Recommendation translated');
    $checks++;
    echo "PASS contextual ceiling method $method\n";
}
$result = $interpreter->interpret([['cmid' => 101, 'analysis' => $ceiling]], ['checkedat' => 100, 'activities' => []]);
$check($result[0]['interpretation']['code'] === 'ceiling_contextunknown', 'Missing context not invented');
$check($result[0]['interpretation']['recommendationtype'] === 'review_assessment_design', 'No randomisation advice');
$checks++;
echo "PASS absent context remains unknown\n";
$small = $shapes->analyze([100, 100], 8);
$result = $interpreter->interpret([['cmid' => 101, 'analysis' => $small]], ['checkedat' => 100, 'activities' => []]);
$check($result[0]['analysis'] === $small, 'Small-group suppression preserved');
$check($result[0]['interpretation']['recommendationtype'] === null, 'No recommendation from small sample');
$checks++;
echo "PASS small-group suppression preserved\n";
$configs = [$analyzer->analyze(assessment_design_cases::quiz()),
    $analyzer->analyze(assessment_design_cases::assignment(['attemptreopenmethod' => 'untilpass']))];
$result = $interpreter->interpret(
    [['cmid' => 101, 'analysis' => $ceiling], ['cmid' => 102, 'analysis' => $ceiling]],
    ['checkedat' => 100, 'activities' => $configs]
);
$check($result[0]['interpretation']['code'] === 'ceiling_best', 'Quiz cmid match');
$check($result[1]['interpretation']['code'] === 'ceiling_untilpass', 'Assignment cmid match');
$checks++;
echo "PASS instance-id collisions do not mix activities\n";

// DML recording double: checks the PHP data contract, NOT SQL execution in Moodle.
$DB = new class {
    public array $calls = [];
    public array $responses = [];
    public function get_records_sql(string $sql, array $params): array {
        $this->calls[] = ['sql' => $sql, 'params' => $params];
        return array_shift($this->responses) ?? [];
    }
};
$quiz = assessment_design_cases::quiz();
$assign = assessment_design_cases::assignment();
$asrecord = static function (array $row): object {
    $data = array_merge($row, $row['settings']);
    unset($data['settings'], $data['module']);
    $data['userid'] = 888888;
    $data['password'] = 'NOT-FOR-EXPORT';
    $data['availability'] = 'PRIVATE-CONDITION';
    return (object) $data;
};
$DB->responses = [[$asrecord($quiz)], [$asrecord($assign)]];
$rows = (new assessment_design_repository())->get_course_settings(15);
$check(count($DB->calls) === 2, 'Exactly two settings queries');
$check(count($rows) === 2, 'Both module types returned');
foreach ($DB->calls as $call) {
    $check($call['params'] === ['courseid' => 15], 'Course-scoped named parameter');
    $check(str_starts_with(ltrim($call['sql']), 'SELECT'), 'Read-only query');
    $check(str_contains($call['sql'], 'deletioninprogress = 0'), 'Deletion filter');
}
$json = json_encode($rows);
foreach (['userid', 'NOT-FOR-EXPORT', 'PRIVATE-CONDITION', 'password'] as $private) {
    $check(!str_contains($json, $private), 'Whitelist prevents private field export');
}
$check($rows[0]['settings']['attempts'] === 3.0, 'Database numeric values normalised');
$checks++;
echo "PASS repository whitelist/query-count contract (recording double; not a DB integration test)\n";

$thrown = false;
try {
    (new assessment_design_repository())->get_course_settings(0);
} catch (invalid_parameter_exception $e) {
    $thrown = true;
}
$check($thrown, 'Invalid course rejected');
$checks++;
echo "PASS invalid course id rejected\n";

// Note: parameterization_advisor.php is intentionally KEPT (as a gated.
// Recommendation), unlike the penalty simulator and demo generator.
foreach (
    ['classes/indicator/late_penalty_simulator.php',
        'classes/local/demo_data.php', 'tools/create_demo_data.php'] as $removed
) {
    $check(!file_exists($root . '/' . $removed), 'Removed production file still present: ' . $removed);
}
$checks++;
echo "PASS experimental/generator files absent from runtime package\n";
echo "\nSUCCESS: $checks checks, $assertions assertions. PHP " . PHP_VERSION . ".\n";
echo "Moodle installation, PHPUnit, MariaDB/PostgreSQL and real-course load tests were NOT run by this script.\n";
