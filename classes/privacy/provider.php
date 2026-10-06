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
 * Privacy Subsystem implementation for local_tla.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\privacy;


/**
 * Privacy provider for local_tla.
 *
 * The plugin stores no personal data. Its dashboard reads Moodle core tables
 * (logs, gradebook, quiz attempts, assignment submissions) live and returns
 * only aggregated, anonymous figures. The only plugin-owned table,
 * {local_tla_course_daily}, holds per-course, per-day aggregate counts with no
 * user identifier. Therefore the null provider is the correct choice.
 */
class provider implements \core_privacy\local\metadata\null_provider {
    /**
     * Return the reason why this plugin stores no personal data.
     *
     * @return string A language string identifier.
     */
    public static function get_reason(): string {
        return 'privacy:metadata';
    }
}
