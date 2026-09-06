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
 * Tests for the course dashboard access guard.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla;

use local_tla\local\dashboard_access;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

#[CoversClass(dashboard_access::class)]
final class dashboard_access_test extends \advanced_testcase {
    public function test_editingteacher_with_capability_is_allowed(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id, 'editingteacher');
        $this->setUser($user);

        $context = dashboard_access::validate((int) $course->id);
        $this->assertInstanceOf(\context_course::class, $context);
        $this->assertSame((int) $course->id, (int) $context->instanceid);
    }

    public function test_student_without_capability_is_denied(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $course->id, 'student');
        $this->setUser($user);

        $this->expectException(\required_capability_exception::class);
        dashboard_access::validate((int) $course->id);
    }

    public function test_capability_in_one_course_does_not_grant_another(): void {
        $this->resetAfterTest();

        $coursea = $this->getDataGenerator()->create_course();
        $courseb = $this->getDataGenerator()->create_course();
        $user = $this->getDataGenerator()->create_user();
        $this->getDataGenerator()->enrol_user((int) $user->id, (int) $coursea->id, 'editingteacher');
        $this->setUser($user);

        // Access to course A works, course B must be refused.
        dashboard_access::validate((int) $coursea->id);
        $this->expectException(\moodle_exception::class);
        dashboard_access::validate((int) $courseb->id);
    }

    public function test_guest_is_denied(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->setGuestUser();

        $this->expectException(\moodle_exception::class);
        dashboard_access::validate((int) $course->id);
    }

    public function test_admin_is_allowed(): void {
        $this->resetAfterTest();

        $course = $this->getDataGenerator()->create_course();
        $this->setAdminUser();

        $context = dashboard_access::validate((int) $course->id);
        $this->assertInstanceOf(\context_course::class, $context);
    }

    public function test_invalid_course_id_is_refused(): void {
        $this->resetAfterTest();
        $this->setAdminUser();

        $this->expectException(\dml_missing_record_exception::class);
        dashboard_access::validate(999999);
    }
}
