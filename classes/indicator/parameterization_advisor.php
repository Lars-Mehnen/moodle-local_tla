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
 * Quiz parameterization advisor.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\indicator;


/**
 * Recommend how much per-student randomization a quiz needs.
 *
 * Pure, database-free and side-effect-free. Given a cohort size it computes how
 * many distinct question variants are needed so that a leaked or shared solution
 * helps almost no one, factorises that into a small number of random parameters
 * with concrete ranges, and emits a ready-to-paste CodeRunner (Twig) snippet.
 *
 * Anti-leak reasoning: with N students and V equally likely variants, the average
 * number of students who share any given variant is N / V. Keeping that well
 * below one means a solution for one variant is worthless to the others. The
 * default target is V >= RATIO * N (RATIO = 20, i.e. on average fewer than one in
 * twenty students shares a variant).
 *
 * This addresses peer / back-channel copying (a copied, unadapted solution fails
 * the grader on the copier's own variant); it does not, by itself, stop a student
 * using an LLM on their own variant. It is advice: the lecturer applies and
 * adapts the snippet in the question bank; the plugin never edits questions.
 */
final class parameterization_advisor {
    /** @var int Default target ratio of variants to students. */
    public const DEFAULT_RATIO = 20;
    /** @var int Largest number of values a single random parameter should take. */
    public const MAX_VALUES_PER_PARAM = 20;
    /** @var int Safety cap on the number of random parameters. */
    public const MAX_PARAMS = 5;

    /**
     * Recommend a variant space and parameterization for a cohort.
     *
     * @param int $cohortn Number of students in the cohort (>= 1).
     * @param int $ratio Target variants-per-student ratio (>= 1).
     * @return array{
     *     cohortn: int,
     *     ratio: int,
     *     targetvariants: int,
     *     params: int,
     *     valuesperparam: int,
     *     variants: int,
     *     ranges: array<int, array{name: string, lo: int, hi: int}>,
     *     snippet: string
     * }
     * @throws \invalid_parameter_exception If a parameter is invalid.
     */
    public function recommend(int $cohortn, int $ratio = self::DEFAULT_RATIO): array {
        if ($cohortn < 1) {
            throw new \invalid_parameter_exception('Cohort size must be at least one.');
        }
        if ($ratio < 1) {
            throw new \invalid_parameter_exception('Ratio must be at least one.');
        }

        $target = $cohortn * $ratio;

        // Smallest number of parameters whose max value space covers the target.
        $params = 1;
        while (
            ($this->pow_int(self::MAX_VALUES_PER_PARAM, $params)) < $target
                && $params < self::MAX_PARAMS
        ) {
            $params++;
        }
        // Values per parameter so that valuesperparam^params >= target.
        $valuesperparam = (int) ceil($target ** (1.0 / $params) - 1e-9);
        $valuesperparam = max(2, min(self::MAX_VALUES_PER_PARAM, $valuesperparam));
        $variants = $this->pow_int($valuesperparam, $params);

        // Distinct, natural-looking integer ranges for each parameter.
        $starts = [10, 3, 2, 7, 5];
        $names = ['a', 'b', 'c', 'd', 'e'];
        $ranges = [];
        for ($i = 0; $i < $params; $i++) {
            $lo = $starts[$i % count($starts)];
            $ranges[] = [
                'name' => $names[$i % count($names)],
                'lo' => $lo,
                'hi' => $lo + $valuesperparam - 1,
            ];
        }

        return [
            'cohortn' => $cohortn,
            'ratio' => $ratio,
            'targetvariants' => $target,
            'params' => $params,
            'valuesperparam' => $valuesperparam,
            'variants' => $variants,
            'ranges' => $ranges,
            'snippet' => $this->build_snippet($cohortn, $target, $variants, $ranges),
        ];
    }

    /**
     * Build a ready-to-paste CodeRunner (Twig) randomization snippet.
     *
     * @param int $cohortn Cohort size.
     * @param int $target Target variant count.
     * @param int $variants Provided variant count.
     * @param array $ranges Parameter ranges.
     * @return string Twig snippet.
     */
    private function build_snippet(int $cohortn, int $target, int $variants, array $ranges): string {
        $lines = [];
        $lines[] = "{# local_tla: ~{$cohortn} students -> aim for >= {$target} variants "
            . "({$variants} provided). Enable 'Twig all' on the question. #}";
        foreach ($ranges as $r) {
            $lines[] = "{% set {$r['name']} = random({$r['lo']}, {$r['hi']}) %}";
        }
        $lines[] = "{# Use " . implode(', ', array_column($ranges, 'name'))
            . " in the question text AND the expected test output so each "
            . "student's task and answer differ. #}";
        return implode("\n", $lines);
    }

    /**
     * Integer power (avoids float rounding for the variant counts).
     *
     * @param int $base Base.
     * @param int $exp Exponent (>= 0).
     * @return int
     */
    private function pow_int(int $base, int $exp): int {
        $result = 1;
        for ($i = 0; $i < $exp; $i++) {
            $result *= $base;
        }
        return $result;
    }
}
