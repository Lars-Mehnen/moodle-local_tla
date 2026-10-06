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
 * Course dashboard access guard.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\local;


/**
 * Central access check for the course dashboard.
 *
 * This is the single source of truth for who may view the dashboard. It always
 * enforces, in this order: a valid course, an authenticated and logged-in
 * course context (require_login, which also rejects guests and unenrolled users
 * without the right capability), and the local/tla:view capability in the
 * course context. It relies only on capabilities and course context, never on
 * role names or role ids.
 */
final class dashboard_access {
    /**
     * Validate access to the course dashboard and return the course context.
     *
     * @param int $courseid Course id.
     * @return \context_course The validated course context.
     * @throws \dml_missing_record_exception If the course does not exist.
     * @throws \require_login_exception If the user may not access the course.
     * @throws \required_capability_exception If the user lacks local/tla:view.
     */
    public static function validate(int $courseid): \context_course {
        $course = get_course($courseid);
        require_login($course);
        $context = \context_course::instance($course->id);
        require_capability('local/tla:view', $context);
        return $context;
    }
}
