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
 * Web service definitions for local_tla.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$functions = [
    'local_tla_get_course_quality_report' => [
        'classname' => 'local_tla\external\get_course_quality_report',
        'methodname' => 'execute',
        'description' => 'Return the machine-readable course quality report '
            . '(aggregate findings and draft recommendations) for a course.',
        'type' => 'read',
        'capabilities' => 'local/tla:view',
        'ajax' => true,
    ],
];

$services = [
    'Teaching and Learning Analytics' => [
        'functions' => ['local_tla_get_course_quality_report'],
        'restrictedusers' => 0,
        'enabled' => 1,
        'shortname' => 'local_tla_ws',
    ],
];
