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
 * Course dashboard data service.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\service;

use local_tla\repository\course_activity_repository;
use local_tla\repository\course_grade_repository;
use local_tla\repository\course_dose_response_repository;
use local_tla\repository\quiz_progress_repository;
use local_tla\indicator\score_distribution_interpreter;

defined('MOODLE_INTERNAL') || die();

/**
 * Assemble read-only course dashboard data.
 *
 * Capability checks intentionally belong to the controller or external API
 * boundary, not to this service or its repository.
 */
final class course_dashboard_service {
    /** @var course_activity_repository Course activity repository. */
    private course_activity_repository $repository;

    /** @var course_grade_repository Course gradebook repository. */
    private course_grade_repository $graderepository;

    /** @var quiz_progress_repository Quiz attempt progress repository. */
    private quiz_progress_repository $quizprogressrepository;

    /** @var course_dose_response_repository Learning dose-response repository. */
    private course_dose_response_repository $doseresponserepository;

    /** @var assessment_design_service Current assessment settings. */
    private assessment_design_service $designservice;

    /**
     * Constructor.
     *
     * @param course_activity_repository|null $repository Activity repository.
     * @param course_grade_repository|null $graderepository Grade repository.
     * @param quiz_progress_repository|null $quizprogressrepository Quiz progress repository.
     * @param course_dose_response_repository|null $doseresponserepository Dose-response repository.
     * @param assessment_design_service|null $designservice Settings analysis.
     */
    public function __construct(
        ?course_activity_repository $repository = null,
        ?course_grade_repository $graderepository = null,
        ?quiz_progress_repository $quizprogressrepository = null,
        ?course_dose_response_repository $doseresponserepository = null,
        ?assessment_design_service $designservice = null
    ) {
        $this->repository = $repository ?? new course_activity_repository();
        $this->graderepository = $graderepository ?? new course_grade_repository();
        $this->quizprogressrepository = $quizprogressrepository ?? new quiz_progress_repository();
        $this->doseresponserepository = $doseresponserepository
            ?? new course_dose_response_repository();
        $this->designservice = $designservice ?? new assessment_design_service();
    }

    /**
     * Return all baseline dashboard data for one course and time interval.
     *
     * The interval is start-inclusive and end-exclusive.
     *
     * @param int $courseid Course ID.
     * @param int $timestart Inclusive Unix timestamp.
     * @param int $timeend Exclusive Unix timestamp.
     * @return array{
     *     courseid: int,
     *     timestart: int,
     *     timeend: int,
     *     eventsperday: array,
     *     activeusersperday: array,
     *     moduleevents: array,
     *     assignmentsubmissions: array{
     *         submitted: int,
     *         ontime: int,
     *         late: int,
     *         noduedate: int,
     *         extended: int,
     *         useroverride: int,
     *         groupoverride: int,
     *         unresolved: int
     *     },
     *     scoredistributions: array,
     *     quizprogress: array,
     *     courseprogress: array,
     *     assessmentdesign: array,
     *     doseresponse: array
     * }
     * @param int $courseid Course ID.
     * @param int $timestart Inclusive Unix timestamp.
     * @param int $timeend Exclusive Unix timestamp.
     * @param int $minobservations Minimum valid grades for a classification.
     * @throws \invalid_parameter_exception If a parameter is invalid.
     */
    public function get_course_dashboard_data(
        int $courseid,
        int $timestart,
        int $timeend,
        int $minobservations = 8
    ): array {
        $design = $this->designservice->get_course_design($courseid);
        $scores = $this->graderepository->get_activity_score_distributions($courseid, $minobservations);
        $scores = (new score_distribution_interpreter())->interpret($scores, $design);
        return [
            'courseid' => $courseid,
            'timestart' => $timestart,
            'timeend' => $timeend,
            'eventsperday' => $this->repository->get_events_per_day(
                $courseid,
                $timestart,
                $timeend
            ),
            'activeusersperday' => $this->repository->get_active_users_per_day(
                $courseid,
                $timestart,
                $timeend
            ),
            'moduleevents' => $this->repository->get_module_events(
                $courseid,
                $timestart,
                $timeend
            ),
            'assignmentsubmissions' =>
                $this->repository->get_effective_individual_assignment_submission_summary(
                    $courseid,
                    $timestart,
                    $timeend
                ),
            'scoredistributions' => $scores,
            'assessmentdesign' => $design,
            'quizprogress' =>
                $this->quizprogressrepository->get_quiz_attempt_progress(
                    $courseid,
                    $minobservations
                ),
            'courseprogress' =>
                $this->graderepository->get_course_progress(
                    $courseid,
                    $minobservations
                ),
            'doseresponse' =>
                $this->doseresponserepository->get_learning_dose_response(
                    $courseid,
                    $minobservations
                ),
        ];
    }
}
