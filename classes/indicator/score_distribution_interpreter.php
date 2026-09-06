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
 * Contextualise descriptive score shapes using current assessment settings.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\indicator;

defined('MOODLE_INTERNAL') || die();

/**
 * Shared by dashboard and external report. No misconduct detection, no generated
 * question variants, and no causal inference from current rules to past grades.
 */
final class score_distribution_interpreter {
    /**
     * Attach a pedagogically cautious interpretation without changing metrics.
     *
     * @param array $distributions Descriptive results from the grade repository.
     * @param array $design Configuration snapshot from assessment_design_service.
     * @return array Contextualised results.
     */
    public function interpret(array $distributions, array $design): array {
        $bycm = [];
        foreach ($design['activities'] as $activity) {
            $bycm[$activity['cmid']] = $activity;
        }
        foreach ($distributions as &$distribution) {
            $status = $distribution['analysis']['status'];
            $config = $bycm[$distribution['cmid']] ?? null;
            $context = $config['retrycontext'] ?? 'unknown';
            $code = 'score_' . $status;
            $severity = $distribution['analysis']['severity'];
            $recommendation = null;
            if ($status === 'ceiling') {
                // A ceiling is a descriptive observation, NOT a red risk light.
                $severity = 'info';
                $code = match ($context) {
                    'bestofmultiple' => 'ceiling_best',
                    'lastofmultiple' => 'ceiling_last',
                    'averageofmultiple', 'firstofmultiple' => 'ceiling_retained',
                    'untilpass' => 'ceiling_untilpass',
                    default => 'ceiling_contextunknown',
                };
                $recommendation = in_array($context, ['bestofmultiple', 'lastofmultiple'], true)
                    ? 'review_first_and_final' : 'review_assessment_design';
                // Gated exception: a quiz ceiling with no retry mechanism to
                // explain it (single attempt or unknown context) is the
                // leak-prone case. Only then suggest per-student randomization.
                if (($config['module'] ?? '') === 'quiz'
                        && in_array($context, ['single', 'unknown'], true)) {
                    $severity = 'yellow';
                    $code = 'ceiling_unrandomised';
                    $recommendation = 'parameterize_quiz';
                }
            } else if ($status === 'floor') {
                $recommendation = 'review_difficulty';
            } else if ($status === 'ushape') {
                $recommendation = 'review_two_groups';
            }
            $distribution['analysis']['severity'] = $severity;
            $distribution['interpretation'] = [
                'code' => $code,
                'messagekey' => $status === 'ceiling' ? 'ad_interpret_' . $code : 'scoreexpl_' . $status,
                'severity' => $severity,
                'recommendationtype' => $recommendation,
                'recommendationkey' => $recommendation === null ? null : 'ad_recommend_' . $recommendation,
                'retrycontext' => $context,
                'configurationsource' => $config === null ? 'unavailable' : 'current_configuration',
                'checkedat' => $design['checkedat'],
                'historicalmatchverified' => false,
            ];
        }
        unset($distribution);
        return $distributions;
    }
}
