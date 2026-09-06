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
 * External function: course quality report.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_multiple_structure;
use core_external\external_value;

defined('MOODLE_INTERNAL') || die();

/**
 * Return the machine-readable course quality report for a course.
 *
 * Read-only. Aggregate only (no personal data). Intended as the grounding
 * interface for an external course-quality agent: typed findings with severity,
 * confidence, evidence and draft recommendations. The variable evidence and
 * recommendation payloads are returned as JSON strings so the schema stays
 * stable as recommendation types evolve.
 */
final class get_course_quality_report extends external_api {
    /**
     * Parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'minobservations' => new external_value(
                PARAM_INT,
                'Minimum observations for a classification',
                VALUE_DEFAULT,
                8
            ),
        ]);
    }

    /**
     * Build the report.
     *
     * @param int $courseid Course ID.
     * @param int $minobservations Minimum observations.
     * @return array
     */
    public static function execute(int $courseid, int $minobservations = 8): array {
        $params = self::validate_parameters(self::execute_parameters(), [
            'courseid' => $courseid,
            'minobservations' => $minobservations,
        ]);

        $context = \context_course::instance($params['courseid']);
        self::validate_context($context);
        require_capability('local/tla:view', $context);

        $configuredminimum = get_config('local_tla', 'min_observations');
        $minimum = max(1, (int) $params['minobservations'],
            $configuredminimum === false ? 8 : (int) $configuredminimum);

        $report = (new \local_tla\service\course_quality_report())->generate(
            $params['courseid'],
            $minimum
        );

        $findings = [];
        foreach ($report['findings'] as $f) {
            $findings[] = [
                'area' => $f['area'],
                'target' => $f['target'],
                'severity' => $f['severity'],
                'confidence' => $f['confidence'],
                'status' => $f['status'],
                'summary' => $f['summary'],
                'evidence' => json_encode($f['evidence']),
                'recommendation' => $f['recommendation'] === null
                    ? ''
                    : json_encode($f['recommendation']),
            ];
        }

        return [
            'courseid' => $report['courseid'],
            'generated' => $report['generated'],
            'summary' => $report['summary'],
            'findings' => $findings,
        ];
    }

    /**
     * Return structure.
     *
     * @return external_single_structure
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'courseid' => new external_value(PARAM_INT, 'Course ID'),
            'generated' => new external_value(PARAM_INT, 'Number of findings'),
            'summary' => new external_single_structure([
                'red' => new external_value(PARAM_INT, 'Number of red findings'),
                'yellow' => new external_value(PARAM_INT, 'Number of yellow findings'),
                'green' => new external_value(PARAM_INT, 'Number of green findings'),
                'unknown' => new external_value(PARAM_INT, 'Number of unknown findings'),
                'info' => new external_value(PARAM_INT, 'Informational observations, not a quality rating'),
            ]),
            'findings' => new external_multiple_structure(new external_single_structure([
                'area' => new external_value(PARAM_ALPHAEXT, 'Analysis area'),
                'target' => new external_value(PARAM_TEXT, 'Activity name or "course"'),
                'severity' => new external_value(PARAM_ALPHA, 'red|yellow|green|unknown|info'),
                'confidence' => new external_value(PARAM_ALPHA, 'high|medium|low; interpretation basis is provided in evidence'),
                'status' => new external_value(PARAM_ALPHAEXT, 'Classifier status'),
                'summary' => new external_value(PARAM_TEXT, 'Human-readable one-line summary'),
                'evidence' => new external_value(PARAM_RAW, 'Evidence as a JSON object'),
                'recommendation' => new external_value(
                    PARAM_RAW,
                    'Draft recommendation as a JSON object, or empty if none'
                ),
            ])),
        ]);
    }
}
