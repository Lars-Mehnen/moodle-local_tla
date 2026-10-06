<?php
// This file is part of Moodle - https://moodle.org/
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
 * Administration settings for the local_tla plugin.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
if ($hassiteconfig) {
    $settings = new admin_settingpage('local_tla', get_string('pluginname', 'local_tla'));
    $ADMIN->add('localplugins', $settings);
    // Deadline-Ampel (Anteil Abgaben in letzten 24h).
    $settings->add(new admin_setting_configtext(
        'local_tla/deadline_yellow',
        get_string('deadline_yellow', 'local_tla'),
        '',
        '0.25',
        PARAM_FLOAT
    ));
    $settings->add(new admin_setting_configtext(
        'local_tla/deadline_red',
        get_string('deadline_red', 'local_tla'),
        '',
        '0.50',
        PARAM_FLOAT
    ));
    // Mindest-Stichprobengroesse fuer belastbare Ampeln/Prognosen.
    $settings->add(new admin_setting_configtext(
        'local_tla/min_observations',
        get_string('min_observations', 'local_tla'),
        '',
        '8',
        PARAM_INT
    ));
}
