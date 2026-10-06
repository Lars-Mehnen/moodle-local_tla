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
 * Read-only configuration check, independent of log scans and student grades.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_tla\local\dashboard_access;
use local_tla\output\assessment_design_presenter;
use local_tla\service\assessment_design_service;

$courseid = required_param('id', PARAM_INT);
$page = max(0, optional_param('designpage', 0, PARAM_INT));
$course = get_course($courseid);
$context = dashboard_access::validate((int) $course->id);
require_login($course);
$url = new moodle_url('/local/tla/assessment_design.php', ['id' => $course->id, 'designpage' => $page]);
$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('ad_title', 'local_tla'));
$PAGE->set_heading(format_string($course->fullname));

$design = (new assessment_design_service())->get_course_design((int) $course->id);
$view = (new assessment_design_presenter())->export($design, $url, $page);

echo $OUTPUT->header();
echo html_writer::link(
    new moodle_url('/local/tla/dashboard.php', ['id' => $course->id]),
    get_string('ad_back', 'local_tla'),
    ['class' => 'd-inline-block mb-3']
);
echo $OUTPUT->render_from_template('local_tla/assessment_design', $view);
echo $OUTPUT->footer();
