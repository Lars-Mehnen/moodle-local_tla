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
 * Machine-readable course quality report.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\service;

use local_tla\indicator\parameterization_advisor;
use local_tla\indicator\score_distribution_interpreter;
use local_tla\repository\course_grade_repository;
use local_tla\repository\course_dose_response_repository;

defined('MOODLE_INTERNAL') || die();

/**
 * Aggregate the dashboard analyzers into a typed list of findings.
 *
 * This is the grounding layer for an external course-quality agent: instead of
 * guessing what is wrong with a course, the agent receives evidence-backed,
 * caveated findings, each with a severity, a confidence and (where applicable) a
 * contextual review action. Configuration observations are informational, not
 * teaching-quality traffic lights.
 *
 * Read-only and aggregate: it returns no personal data, and it never changes the
 * course. Recommendations are drafts for a human or an agent to apply.
 */
final class course_quality_report {
    /** @var course_grade_repository Gradebook repository. */
    private course_grade_repository $graderepository;

    /** @var course_dose_response_repository Dose-response repository. */
    private course_dose_response_repository $doseresponserepository;

    /** @var assessment_design_service Shared settings analysis. */
    private assessment_design_service $designservice;

    /**
     * Constructor.
     *
     * @param course_grade_repository|null $graderepository Grade repository.
     * @param course_dose_response_repository|null $doseresponserepository Dose-response repository.
     * @param assessment_design_service|null $designservice Current settings analysis.
     */
    public function __construct(
        ?course_grade_repository $graderepository = null,
        ?course_dose_response_repository $doseresponserepository = null,
        ?assessment_design_service $designservice = null
    ) {
        $this->graderepository = $graderepository ?? new course_grade_repository();
        $this->doseresponserepository = $doseresponserepository
            ?? new course_dose_response_repository();
        $this->designservice = $designservice ?? new assessment_design_service();
    }

    /**
     * Generate the quality report for a course.
     *
     * Uses only the gradebook and quiz analyzers (no event-log scans), so it is
     * fast to call from an external agent.
     *
     * @param int $courseid Course ID.
     * @param int $minobservations Minimum observations for a classification.
     * @return array{
     *     courseid: int,
     *     generated: int,
     *     findings: array<int, array{
     *         area: string, target: string, severity: string, confidence: string,
     *         status: string, summary: string, evidence: array, recommendation: array|null
     *     }>,
     *     summary: array{red: int, yellow: int, green: int, unknown: int, info: int}
     * }
     * @throws \invalid_parameter_exception If a parameter is invalid.
     */
    public function generate(int $courseid, int $minobservations = 8): array {
        $design = $this->designservice->get_course_design($courseid);
        $scoredistributions = $this->graderepository->get_activity_score_distributions(
            $courseid,
            $minobservations
        );
        $scoredistributions = (new score_distribution_interpreter())->interpret($scoredistributions, $design);
        $courseprogressdata = $this->graderepository->get_course_progress(
            $courseid,
            $minobservations
        );
        $doseresponsedata = $this->doseresponserepository->get_learning_dose_response(
            $courseid,
            $minobservations
        );

        $findings = $this->assessment_design_findings($design);
        foreach ($this->score_distribution_findings($scoredistributions) as $f) {
            $findings[] = $f;
        }
        $doseresponse = $this->dose_response_finding($doseresponsedata);
        if ($doseresponse !== null) {
            $findings[] = $doseresponse;
        }
        $courseprogress = $this->course_progress_finding($courseprogressdata);
        if ($courseprogress !== null) {
            $findings[] = $courseprogress;
        }

        $summary = ['red' => 0, 'yellow' => 0, 'green' => 0, 'unknown' => 0, 'info' => 0];
        foreach ($findings as $f) {
            $summary[$f['severity']] = ($summary[$f['severity']] ?? 0) + 1;
        }

        // Most actionable first: red, then yellow, then the rest.
        $order = ['red' => 0, 'yellow' => 1, 'green' => 2, 'unknown' => 3, 'info' => 4];
        usort($findings, static function (array $a, array $b) use ($order): int {
            return [$order[$a['severity']] ?? 9, $a['area']]
                <=> [$order[$b['severity']] ?? 9, $b['area']];
        });

        return [
            'courseid' => $courseid,
            'generated' => count($findings),
            'findings' => $findings,
            'summary' => $summary,
        ];
    }

    /**
     * Expose the same criterion results shown on the dashboard.
     *
     * @param array $design Current settings snapshot.
     * @return array Informational findings, never a fairness score.
     */
    private function assessment_design_findings(array $design): array {
        $out = [];
        foreach ($design['activities'] as $activity) {
            foreach ($activity['findings'] as $finding) {
                $out[] = [
                    'area' => 'assessment_design',
                    'target' => (string) $activity['name'],
                    'severity' => 'info',
                    'confidence' => $finding['status'] === 'unknown' ? 'low' : 'high',
                    'status' => $finding['status'],
                    'summary' => get_string($finding['messagekey'], 'local_tla'),
                    'evidence' => [
                        'cmid' => $activity['cmid'],
                        'module' => $activity['module'],
                        'criterion' => $finding['criterion'],
                        'rule' => $finding['code'],
                        'source' => $design['source'],
                        'checkedat' => $design['checkedat'],
                        'scope' => 'base_settings',
                        'settings' => $activity['settings'],
                        'hasoverrides' => $activity['hasoverrides'],
                        'hasexceptions' => $activity['hasexceptions'],
                        'hasrestrictions' => $activity['hasrestrictions'],
                        'visible' => $activity['visible'],
                        'limits' => $design['limitations'],
                        'confidencebasis' => 'configuration_observation_not_pedagogical_judgment',
                    ],
                    'recommendation' => $finding['actionkey'] === null ? null : [
                        'type' => 'review_assessment_design',
                        'text' => get_string($finding['actionkey'], 'local_tla'),
                        'params' => null,
                    ],
                ];
            }
        }
        return $out;
    }

    /**
     * Report score shapes with the shared, design-aware interpretation.
     *
     * @param array $scoredistributions Contextualised distribution results.
     * @return array Findings.
     */
    private function score_distribution_findings(array $scoredistributions): array {
        $out = [];
        foreach ($scoredistributions as $activity) {
            $analysis = $activity['analysis'];
            $status = $analysis['status'];
            if ($status === 'unknown') {
                continue;
            }
            $interpretation = $activity['interpretation'];
            $n = (int) $activity['validgrades'];
            $out[] = [
                'area' => 'score_distribution',
                'target' => (string) $activity['name'],
                'severity' => $interpretation['severity'],
                'confidence' => self::confidence($n),
                'status' => $status,
                'summary' => get_string($interpretation['messagekey'], 'local_tla'),
                'evidence' => [
                    'cmid' => $activity['cmid'],
                    'validgrades' => $n,
                    'median' => $analysis['median'],
                    'q1' => $analysis['q1'],
                    'q3' => $analysis['q3'],
                    'lowshare' => $analysis['lowshare'],
                    'middleshare' => $analysis['middleshare'],
                    'highshare' => $analysis['highshare'],
                    'assessmentcontext' => $interpretation,
                    'confidencebasis' => 'sample_size_heuristic_not_causal_confidence',
                ],
                'recommendation' => $this->score_recommendation($interpretation, $n),
            ];
        }
        return $out;
    }

    /**
     * Build the recommendation for a score-distribution finding.
     *
     * For the gated quiz-ceiling case the interpreter asks for
     * 'parameterize_quiz'; only then is the concrete parameterization plan
     * (variant space + CodeRunner snippet) attached, sized to the cohort. Every
     * other recommendation is a review suggestion with no parameters.
     *
     * @param array $interpretation Interpretation block from the interpreter.
     * @param int $n Number of valid grades (cohort proxy for the advisor).
     * @return array|null
     */
    private function score_recommendation(array $interpretation, int $n): ?array {
        if ($interpretation['recommendationtype'] === 'parameterize_quiz') {
            return [
                'type' => 'parameterize_quiz',
                'text' => get_string($interpretation['recommendationkey'], 'local_tla'),
                'params' => (new parameterization_advisor())->recommend(max(1, $n)),
            ];
        }
        if ($interpretation['recommendationkey'] === null) {
            return null;
        }
        return [
            'type' => $interpretation['recommendationtype'],
            'text' => get_string($interpretation['recommendationkey'], 'local_tla'),
            'params' => null,
        ];
    }

    /**
     * Build the dose-response finding.
     *
     * @param array $doseresponse Dose-response result.
     * @return array|null
     */
    private function dose_response_finding(array $doseresponse): ?array {
        $a = $doseresponse['analysis'];
        if ($a['status'] === 'unknown') {
            return null;
        }
        $recommendation = null;
        if ($a['fitquality'] === 'flat') {
            $recommendation = [
                'type' => 'do_not_reward_volume',
                'text' => 'This fitted model captures no clear practice-performance association. '
                    . 'That does not establish that practice has no benefit. Review task '
                    . 'alignment and alternative explanations before changing assessment.',
                'params' => null,
            ];
        }
        return [
            'area' => 'dose_response',
            'target' => 'course',
            'severity' => $a['severity'],
            'confidence' => self::confidence((int) $a['observations']),
            'status' => (string) $a['fitquality'],
            'summary' => 'Practice-vs-performance fit: ' . $a['fitquality']
                . ' (explains ' . round(($a['explainedvariance'] ?? 0) * 100) . '% of variance).',
            'evidence' => [
                'observations' => $a['observations'],
                'emax' => $a['emax']['median'],
                'ec50' => $a['ec50']['median'],
                'explainedvariance' => $a['explainedvariance'],
            ],
            'recommendation' => $recommendation,
        ];
    }

    /**
     * Build the course-progress finding.
     *
     * @param array $courseprogress Course-progress result.
     * @return array|null
     */
    private function course_progress_finding(array $courseprogress): ?array {
        if ($courseprogress['status'] === 'unknown') {
            return null;
        }
        return [
            'area' => 'course_progress',
            'target' => 'course',
            'severity' => $courseprogress['severity'],
            'confidence' => self::confidence((int) $courseprogress['participants']),
            'status' => (string) $courseprogress['status'],
            'summary' => 'Course development: ' . $courseprogress['status']
                . ' across ' . $courseprogress['activities'] . ' activities.',
            'evidence' => [
                'activities' => $courseprogress['activities'],
                'participants' => $courseprogress['participants'],
                'medianchange' => $courseprogress['medianchange'],
            ],
            'recommendation' => null,
        ];
    }

    /**
     * Map an observation count to a confidence label.
     *
     * @param int $n Observation count.
     * @return string 'high' | 'medium' | 'low'
     */
    private static function confidence(int $n): string {
        if ($n >= 20) {
            return 'high';
        }
        if ($n >= 8) {
            return 'medium';
        }
        return 'low';
    }
}
