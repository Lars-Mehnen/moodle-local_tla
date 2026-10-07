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
 * Tests for the effective deadline resolver.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla;

use local_tla\assignment\effective_deadline_resolver;


/**
 * Tests for effective_deadline_resolver_test.
 * @covers \local_tla\assignment\effective_deadline_resolver
 */
final class effective_deadline_resolver_test extends \advanced_testcase {
    /** @var effective_deadline_resolver Resolver under test. */
    private effective_deadline_resolver $resolver;

    protected function setUp(): void {
        parent::setUp();
        $this->resolver = new effective_deadline_resolver();
    }

    public function test_normal_deadline_on_time(): void {
        $result = $this->resolver->resolve(900, 1000, false, null, [], 0);

        $this->assertSame(1000, $result['effective']);
        $this->assertSame(effective_deadline_resolver::MECH_ASSIGN, $result['mechanism']);
        $this->assertSame(effective_deadline_resolver::CLASS_ONTIME, $result['classification']);
    }

    public function test_normal_deadline_exactly_on_deadline(): void {
        $result = $this->resolver->resolve(1000, 1000, false, null, [], 0);

        $this->assertSame(effective_deadline_resolver::CLASS_ONTIME, $result['classification']);
    }

    public function test_normal_deadline_late(): void {
        $result = $this->resolver->resolve(1001, 1000, false, null, [], 0);

        $this->assertSame(effective_deadline_resolver::CLASS_LATE, $result['classification']);
    }

    public function test_no_deadline(): void {
        $result = $this->resolver->resolve(1001, 0, false, null, [], 0);

        $this->assertSame(0, $result['effective']);
        $this->assertSame(effective_deadline_resolver::MECH_NODUEDATE, $result['mechanism']);
        $this->assertSame(effective_deadline_resolver::CLASS_NODUEDATE, $result['classification']);
    }

    public function test_user_override_extends_deadline(): void {
        // Due 1000, user override to 2000, submitted at 1500.
        $result = $this->resolver->resolve(1500, 1000, true, 2000, [], 0);

        $this->assertSame(2000, $result['effective']);
        $this->assertSame(effective_deadline_resolver::MECH_USEROVERRIDE, $result['mechanism']);
        $this->assertSame(effective_deadline_resolver::CLASS_ONTIME, $result['classification']);
    }

    public function test_user_override_pulls_deadline_earlier(): void {
        // Due 2000, user override to 1000, submitted at 1500 -> late.
        $result = $this->resolver->resolve(1500, 2000, true, 1000, [], 0);

        $this->assertSame(1000, $result['effective']);
        $this->assertSame(effective_deadline_resolver::MECH_USEROVERRIDE, $result['mechanism']);
        $this->assertSame(effective_deadline_resolver::CLASS_LATE, $result['classification']);
    }

    public function test_user_override_wins_over_group_override(): void {
        $groups = [['sortorder' => 1, 'duedate' => 2000]];
        $result = $this->resolver->resolve(2500, 1000, true, 3000, $groups, 0);

        $this->assertSame(3000, $result['effective']);
        $this->assertSame(effective_deadline_resolver::MECH_USEROVERRIDE, $result['mechanism']);
        $this->assertSame(effective_deadline_resolver::CLASS_ONTIME, $result['classification']);
    }

    public function test_user_override_null_duedate_blocks_group_and_falls_back(): void {
        // User row exists but duedate NULL: group override is discarded, the.
        // Plain assignment due date applies, and the mechanism is still counted.
        // As a user override because the row was decisive.
        $groups = [['sortorder' => 1, 'duedate' => 2000]];
        $result = $this->resolver->resolve(1500, 1000, true, null, $groups, 0);

        $this->assertSame(1000, $result['effective']);
        $this->assertSame(effective_deadline_resolver::MECH_USEROVERRIDE, $result['mechanism']);
        $this->assertSame(effective_deadline_resolver::CLASS_LATE, $result['classification']);
    }

    public function test_group_override_extends_deadline(): void {
        $groups = [['sortorder' => 1, 'duedate' => 2000]];
        $result = $this->resolver->resolve(1500, 1000, false, null, $groups, 0);

        $this->assertSame(2000, $result['effective']);
        $this->assertSame(effective_deadline_resolver::MECH_GROUPOVERRIDE, $result['mechanism']);
        $this->assertSame(effective_deadline_resolver::CLASS_ONTIME, $result['classification']);
    }

    public function test_group_override_pulls_deadline_earlier(): void {
        // Due 2000, group override to 1000, submitted at 1500 -> late.
        $groups = [['sortorder' => 1, 'duedate' => 1000]];
        $result = $this->resolver->resolve(1500, 2000, false, null, $groups, 0);

        $this->assertSame(1000, $result['effective']);
        $this->assertSame(effective_deadline_resolver::MECH_GROUPOVERRIDE, $result['mechanism']);
        $this->assertSame(effective_deadline_resolver::CLASS_LATE, $result['classification']);
    }

    public function test_lowest_group_sortorder_wins(): void {
        $groups = [
            ['sortorder' => 3, 'duedate' => 5000],
            ['sortorder' => 1, 'duedate' => 2000],
            ['sortorder' => 2, 'duedate' => 4000],
        ];
        $result = $this->resolver->resolve(3000, 1000, false, null, $groups, 0);

        $this->assertSame(2000, $result['effective']);
        $this->assertSame(effective_deadline_resolver::MECH_GROUPOVERRIDE, $result['mechanism']);
        $this->assertSame(effective_deadline_resolver::CLASS_LATE, $result['classification']);
    }

    public function test_tie_on_lowest_group_sortorder_is_unresolved(): void {
        $groups = [
            ['sortorder' => 1, 'duedate' => 2000],
            ['sortorder' => 1, 'duedate' => 3000],
        ];
        $result = $this->resolver->resolve(2500, 1000, false, null, $groups, 0);

        $this->assertSame(
            effective_deadline_resolver::CLASS_UNRESOLVED,
            $result['classification']
        );
    }

    public function test_user_override_duedate_defeats_group_tie(): void {
        // A defined user override due date makes the ambiguous group tie irrelevant.
        $groups = [
            ['sortorder' => 1, 'duedate' => 2000],
            ['sortorder' => 1, 'duedate' => 3000],
        ];
        $result = $this->resolver->resolve(2500, 1000, true, 4000, $groups, 0);

        $this->assertSame(4000, $result['effective']);
        $this->assertSame(effective_deadline_resolver::MECH_USEROVERRIDE, $result['mechanism']);
        $this->assertSame(effective_deadline_resolver::CLASS_ONTIME, $result['classification']);
    }

    public function test_extension_extends_normal_deadline(): void {
        $result = $this->resolver->resolve(4500, 1000, false, null, [], 5000);

        $this->assertSame(5000, $result['effective']);
        $this->assertSame(effective_deadline_resolver::MECH_EXTENSION, $result['mechanism']);
        $this->assertSame(effective_deadline_resolver::CLASS_ONTIME, $result['classification']);
    }

    public function test_extension_extends_group_override(): void {
        $groups = [['sortorder' => 1, 'duedate' => 2000]];
        $result = $this->resolver->resolve(5500, 1000, false, null, $groups, 6000);

        $this->assertSame(6000, $result['effective']);
        $this->assertSame(effective_deadline_resolver::MECH_EXTENSION, $result['mechanism']);
        $this->assertSame(effective_deadline_resolver::CLASS_ONTIME, $result['classification']);
    }

    public function test_extension_not_after_deadline_is_ignored(): void {
        // Extension earlier than the current deadline: ignored.
        $earlier = $this->resolver->resolve(1500, 2000, false, null, [], 1500);
        $this->assertSame(2000, $earlier['effective']);
        $this->assertSame(effective_deadline_resolver::MECH_ASSIGN, $earlier['mechanism']);

        // Extension equal to the current deadline: ignored (must be strictly later).
        $equal = $this->resolver->resolve(1500, 2000, false, null, [], 2000);
        $this->assertSame(2000, $equal['effective']);
        $this->assertSame(effective_deadline_resolver::MECH_ASSIGN, $equal['mechanism']);
    }

    public function test_extension_supersedes_user_override(): void {
        // User override to 3000, extension to 5000 (later) -> extension decides.
        $result = $this->resolver->resolve(4500, 1000, true, 3000, [], 5000);

        $this->assertSame(5000, $result['effective']);
        $this->assertSame(effective_deadline_resolver::MECH_EXTENSION, $result['mechanism']);
        $this->assertSame(effective_deadline_resolver::CLASS_ONTIME, $result['classification']);
    }

    public function test_user_override_retained_when_extension_not_later(): void {
        // User override to 3000, extension to 2500 (earlier) -> extension ignored,.
        // The user override remains the deciding mechanism.
        $result = $this->resolver->resolve(2800, 1000, true, 3000, [], 2500);

        $this->assertSame(3000, $result['effective']);
        $this->assertSame(effective_deadline_resolver::MECH_USEROVERRIDE, $result['mechanism']);
        $this->assertSame(effective_deadline_resolver::CLASS_ONTIME, $result['classification']);
    }

    public function test_group_override_retained_when_extension_not_later(): void {
        // Group override to 2000, extension to 1500 (earlier) -> extension ignored,.
        // The group override remains the deciding mechanism.
        $groups = [['sortorder' => 1, 'duedate' => 2000]];
        $result = $this->resolver->resolve(1900, 1000, false, null, $groups, 1500);

        $this->assertSame(2000, $result['effective']);
        $this->assertSame(effective_deadline_resolver::MECH_GROUPOVERRIDE, $result['mechanism']);
        $this->assertSame(effective_deadline_resolver::CLASS_ONTIME, $result['classification']);
    }

    public function test_extension_without_deadline_creates_none(): void {
        $result = $this->resolver->resolve(4500, 0, false, null, [], 5000);

        $this->assertSame(0, $result['effective']);
        $this->assertSame(effective_deadline_resolver::MECH_NODUEDATE, $result['mechanism']);
        $this->assertSame(effective_deadline_resolver::CLASS_NODUEDATE, $result['classification']);
    }

    // Independent (overlapping) attribute flags.

    public function test_flags_user_override_plus_later_extension(): void {
        // User override to 3000, extension to 5000 (later) -> both flags set.
        $result = $this->resolver->resolve(4500, 1000, true, 3000, [], 5000);

        $this->assertTrue($result['useduseroverride']);
        $this->assertFalse($result['usedgroupoverride']);
        $this->assertTrue($result['usedextension']);
        $this->assertFalse($result['unresolved']);
        $this->assertSame(5000, $result['effective']);
    }

    public function test_flags_group_override_plus_later_extension(): void {
        // Group override to 2000, extension to 6000 (later) -> both flags set.
        $groups = [['sortorder' => 1, 'duedate' => 2000]];
        $result = $this->resolver->resolve(5500, 1000, false, null, $groups, 6000);

        $this->assertFalse($result['useduseroverride']);
        $this->assertTrue($result['usedgroupoverride']);
        $this->assertTrue($result['usedextension']);
        $this->assertSame(6000, $result['effective']);
    }

    public function test_flags_extension_not_effective_keeps_only_override(): void {
        // Group override to 2000, extension to 1500 (earlier) -> only the override flag.
        $groups = [['sortorder' => 1, 'duedate' => 2000]];
        $result = $this->resolver->resolve(1900, 1000, false, null, $groups, 1500);

        $this->assertFalse($result['useduseroverride']);
        $this->assertTrue($result['usedgroupoverride']);
        $this->assertFalse($result['usedextension']);
        $this->assertSame(2000, $result['effective']);
    }

    public function test_flags_pure_extension_without_override(): void {
        // No override, extension to 5000 -> only usedextension.
        $result = $this->resolver->resolve(4500, 1000, false, null, [], 5000);

        $this->assertFalse($result['useduseroverride']);
        $this->assertFalse($result['usedgroupoverride']);
        $this->assertTrue($result['usedextension']);
        $this->assertSame(5000, $result['effective']);
    }

    public function test_flags_plain_deadline_sets_no_attribute(): void {
        // Plain due date, no override, no extension -> no attribute flags.
        $result = $this->resolver->resolve(900, 1000, false, null, [], 0);

        $this->assertFalse($result['useduseroverride']);
        $this->assertFalse($result['usedgroupoverride']);
        $this->assertFalse($result['usedextension']);
        $this->assertFalse($result['unresolved']);
    }

    public function test_group_override_null_duedate_falls_back_to_assignment(): void {
        // A selected group override with NULL duedate falls back to the plain.
        // Assignment due date.
        $groups = [['sortorder' => 1, 'duedate' => null]];
        $result = $this->resolver->resolve(1500, 1000, false, null, $groups, 0);

        $this->assertSame(1000, $result['effective']);
        $this->assertSame(effective_deadline_resolver::MECH_ASSIGN, $result['mechanism']);
        $this->assertSame(effective_deadline_resolver::CLASS_LATE, $result['classification']);
    }
}
