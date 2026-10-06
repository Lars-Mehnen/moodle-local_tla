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
 * Traffic-light indicator calculator.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\indicator;


/**
 * Calculate descriptive traffic-light states from rates.
 *
 * A higher rate represents a less desirable outcome, for example the
 * proportion of late submissions.
 */
final class traffic_light_calculator {
    /**
     * Calculate a traffic-light state.
     *
     * Thresholds are inclusive:
     * - rate below yellow threshold: green
     * - rate from yellow threshold up to red threshold: yellow
     * - rate from red threshold: red
     *
     * Insufficient observations return "unknown".
     *
     * @param int $numerator Number of matching observations.
     * @param int $denominator Total number of observations.
     * @param float $yellowthreshold Yellow threshold between 0 and 1.
     * @param float $redthreshold Red threshold between 0 and 1.
     * @param int $minobservations Minimum denominator required.
     * @return array
     * @throws \invalid_parameter_exception For invalid parameters.
     */
    public function calculate(
        int $numerator,
        int $denominator,
        float $yellowthreshold,
        float $redthreshold,
        int $minobservations
    ): array {
        $this->validate(
            $numerator,
            $denominator,
            $yellowthreshold,
            $redthreshold,
            $minobservations
        );

        if ($denominator < $minobservations) {
            return [
                'status' => 'unknown',
                'rate' => $denominator === 0 ? null : $numerator / $denominator,
                'numerator' => $numerator,
                'denominator' => $denominator,
            ];
        }

        $rate = $numerator / $denominator;

        if ($rate >= $redthreshold) {
            $status = 'red';
        } else if ($rate >= $yellowthreshold) {
            $status = 'yellow';
        } else {
            $status = 'green';
        }

        return [
            'status' => $status,
            'rate' => $rate,
            'numerator' => $numerator,
            'denominator' => $denominator,
        ];
    }

    /**
     * Validate calculator parameters.
     *
     * @param int $numerator Numerator.
     * @param int $denominator Denominator.
     * @param float $yellowthreshold Yellow threshold.
     * @param float $redthreshold Red threshold.
     * @param int $minobservations Minimum observations.
     */
    private function validate(
        int $numerator,
        int $denominator,
        float $yellowthreshold,
        float $redthreshold,
        int $minobservations
    ): void {
        if ($numerator < 0 || $denominator < 0 || $numerator > $denominator) {
            throw new \invalid_parameter_exception('Invalid observation counts.');
        }

        if (
            $yellowthreshold < 0.0 ||
            $yellowthreshold > 1.0 ||
            $redthreshold < 0.0 ||
            $redthreshold > 1.0 ||
            $yellowthreshold > $redthreshold
        ) {
            throw new \invalid_parameter_exception('Invalid traffic-light thresholds.');
        }

        if ($minobservations < 1) {
            throw new \invalid_parameter_exception('Minimum observations must be positive.');
        }
    }
}
