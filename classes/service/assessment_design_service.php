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
 * Shared assessment-design snapshot for dashboard and external report.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\service;

use local_tla\indicator\assessment_design_analyzer;
use local_tla\repository\assessment_design_repository;

defined('MOODLE_INTERNAL') || die();

/** No student-observation threshold: this service analyses configuration only. */
final class assessment_design_service {
    /** @var assessment_design_repository Settings reader. */
    private assessment_design_repository $repository;

    /** @param assessment_design_repository|null $repository Optional reader. */
    public function __construct(?assessment_design_repository $repository = null) {
        $this->repository = $repository ?? new assessment_design_repository();
    }

    /**
     * Build a fresh snapshot. Access checks belong at the controller boundary.
     *
     * @param int $courseid Course id.
     * @return array Current configuration analysis, with explicit scope limits.
     */
    public function get_course_design(int $courseid): array {
        $analyzer = new assessment_design_analyzer();
        $activities = [];
        foreach ($this->repository->get_course_settings($courseid) as $activity) {
            $activities[] = $analyzer->analyze($activity);
        }
        return [
            'source' => 'current_configuration',
            'checkedat' => time(),
            'activities' => $activities,
            'limitations' => [
                'purpose' => 'unknown',
                'coursegradeweight' => 'not_evaluated',
                'historicalsettings' => 'not_reconstructed',
                'individualeffectiveaccess' => 'not_evaluated',
                'feedbackcontent' => 'not_evaluated',
                'questionpenalties' => 'not_evaluated',
                'manualgradeoverrides' => 'not_evaluated',
                'independentcompetence' => 'not_verifiable',
            ],
        ];
    }
}
