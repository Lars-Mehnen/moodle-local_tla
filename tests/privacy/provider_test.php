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
 * Tests for the local_tla privacy provider.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\privacy;



/**
 * Tests for provider_test.
 * @covers \provider
 */
final class provider_test extends \advanced_testcase {
    public function test_is_a_null_provider(): void {
        $this->assertInstanceOf(
            \core_privacy\local\metadata\null_provider::class,
            new provider()
        );
    }

    public function test_get_reason_is_a_defined_string(): void {
        $reason = provider::get_reason();
        $this->assertIsString($reason);
        $this->assertTrue(
            get_string_manager()->string_exists($reason, 'local_tla'),
            "Privacy reason string '{$reason}' must exist for local_tla."
        );
    }
}
