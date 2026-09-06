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
 * Effective per-user assignment deadline resolver.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\assignment;

defined('MOODLE_INTERNAL') || die();

/**
 * Resolves the effective deadline for a single individual assignment submission
 * and classifies it as on time, late, without a due date, or unresolved.
 *
 * This class is intentionally free of any database access or Moodle global
 * state so that it can be unit tested in isolation. The repository is
 * responsible for loading the required override, group-membership and
 * extension data in bulk and passing it in per submission.
 *
 * The resolution mirrors Moodle core mod_assign::override_exists() and
 * mod_assign::update_effective_access() (mod/assign/locallib.php):
 *
 *  1. If a user-override ROW exists, that row decides the due date for every
 *     key. Faithful to core's array_merge(defaults, group, user) followed by
 *     an isset() check, a user-override row whose duedate column is NULL
 *     discards any group override and falls back to the plain assignment due
 *     date, while still counting as the deciding "user override" mechanism.
 *  2. Otherwise the applicable group override with the lowest sortorder wins
 *     (core: ORDER BY sortorder ASC + IGNORE_MULTIPLE). A NULL duedate on that
 *     row falls back to the plain assignment due date.
 *  3. Otherwise the plain assignment due date applies (0 means no due date).
 *  4. An individual extension (assign_user_flags.extensionduedate) then extends
 *     the result only when it is strictly later than the deadline computed so
 *     far and a deadline actually exists.
 *
 * When two or more group overrides that apply to the same user share the lowest
 * sortorder, core's choice is database-order dependent and therefore not
 * reproducible; such a submission is classified as unresolved rather than
 * guessed, unless a user-override due date makes the group overrides irrelevant.
 */
final class effective_deadline_resolver {
    /** @var string Mechanism: assignment has no due date at all. */
    public const MECH_NODUEDATE = 'noduedate';
    /** @var string Mechanism: plain assignment due date applied. */
    public const MECH_ASSIGN = 'assignduedate';
    /** @var string Mechanism: a user-specific override decided the deadline. */
    public const MECH_USEROVERRIDE = 'useroverride';
    /** @var string Mechanism: a group override decided the deadline. */
    public const MECH_GROUPOVERRIDE = 'groupoverride';
    /** @var string Mechanism: an individual extension pushed the deadline later. */
    public const MECH_EXTENSION = 'extension';

    /** @var string Classification: submitted on or before the effective deadline. */
    public const CLASS_ONTIME = 'ontime';
    /** @var string Classification: submitted after the effective deadline. */
    public const CLASS_LATE = 'late';
    /** @var string Classification: no effective deadline exists. */
    public const CLASS_NODUEDATE = 'noduedate';
    /** @var string Classification: deadline could not be reproduced safely. */
    public const CLASS_UNRESOLVED = 'unresolved';

    /**
     * Resolve and classify one individual submission.
     *
     * @param int $timemodified Submission timemodified.
     * @param int $assignduedate Plain assign.duedate (0 means no due date).
     * @param bool $useroverrideexists Whether a user-override row exists for this
     *        user and assignment.
     * @param int|null $useroverrideduedate The user override duedate column
     *        (null when the row exists but the column is not set).
     * @param array<int, array{sortorder: int|null, duedate: int|null}> $groupoverrides
     *        Group overrides that apply to this user for this assignment (already
     *        filtered to the user's groups). May be empty.
     * @param int $extensionduedate assign_user_flags.extensionduedate (0 = none).
     * @return array{
     *     effective: int,
     *     classification: string,
     *     mechanism: string,
     *     useduseroverride: bool,
     *     usedgroupoverride: bool,
     *     usedextension: bool,
     *     unresolved: bool
     * }
     *         The classification is exclusive (exactly one effective deadline).
     *         The used* flags are independent attributes describing which
     *         mechanisms influenced that deadline and may overlap: a submission
     *         whose base deadline came from a group override and was then pushed
     *         later by an extension has both usedgroupoverride and usedextension
     *         set. The mechanism string is the single final deciding mechanism,
     *         kept for diagnostics only.
     */
    public function resolve(
        int $timemodified,
        int $assignduedate,
        bool $useroverrideexists,
        ?int $useroverrideduedate,
        array $groupoverrides,
        int $extensionduedate
    ): array {
        $picked = self::pick_group_override($groupoverrides);
        $groupoverrideduedate = $picked['row']['duedate'] ?? null;
        $groupambiguous = $picked['ambiguous'];

        // Build the merged candidate exactly like core.
        $candidate = null;
        $mechanism = null;

        // Whether a group override would otherwise have supplied a due date.
        $groupwouldapply = $groupoverrideduedate !== null;

        // Group override row contributes its (possibly null) duedate first.
        if ($groupoverrideduedate !== null) {
            $candidate = $groupoverrideduedate;
            $mechanism = self::MECH_GROUPOVERRIDE;
        }

        // A user override row clobbers the whole key, even when its column is null.
        if ($useroverrideexists) {
            if ($useroverrideduedate !== null) {
                $candidate = $useroverrideduedate;
                $mechanism = self::MECH_USEROVERRIDE;
            } else {
                // Core: user row present but duedate NULL -> merged value NULL ->
                // isset() false -> assignment default is used, group value discarded.
                $candidate = null;
                // The user-override row was decisive only where it actually
                // discarded a group override; otherwise it changed nothing.
                $mechanism = $groupwouldapply ? self::MECH_USEROVERRIDE : null;
            }
        }

        if ($candidate !== null) {
            $effective = $candidate;
        } else {
            $effective = $assignduedate;
            if ($mechanism === null) {
                $mechanism = $assignduedate > 0 ? self::MECH_ASSIGN : self::MECH_NODUEDATE;
            }
        }

        // Independent attribute flags: which mechanism decided the BASE deadline
        // (before any extension). These may overlap with usedextension below.
        $useduseroverride = $mechanism === self::MECH_USEROVERRIDE;
        $usedgroupoverride = $mechanism === self::MECH_GROUPOVERRIDE;

        // Extension only moves an existing deadline strictly later. It is an
        // independent attribute: it does not erase the base-override attribute.
        $usedextension = false;
        if ($extensionduedate > 0 && $effective > 0 && $extensionduedate > $effective) {
            $effective = $extensionduedate;
            $mechanism = self::MECH_EXTENSION;
            $usedextension = true;
        }

        // A defined user-override due date makes group ambiguity irrelevant.
        $useroverridewins = $useroverrideexists && $useroverrideduedate !== null;
        $unresolved = $groupambiguous && !$useroverridewins;

        if ($unresolved) {
            $classification = self::CLASS_UNRESOLVED;
        } else if ($effective === 0) {
            $classification = self::CLASS_NODUEDATE;
        } else if ($timemodified <= $effective) {
            $classification = self::CLASS_ONTIME;
        } else {
            $classification = self::CLASS_LATE;
        }

        return [
            'effective' => $effective,
            'classification' => $classification,
            'mechanism' => $mechanism,
            'useduseroverride' => $useduseroverride,
            'usedgroupoverride' => $usedgroupoverride,
            'usedextension' => $usedextension,
            'unresolved' => $unresolved,
        ];
    }

    /**
     * Pick the applicable group override with the lowest sortorder.
     *
     * Mirrors core: ORDER BY sortorder ASC, first row wins (IGNORE_MULTIPLE).
     * A tie on the lowest sortorder is flagged as ambiguous.
     *
     * @param array<int, array{sortorder: int|null, duedate: int|null}> $rows
     * @return array{row: array{sortorder: int|null, duedate: int|null}|null, ambiguous: bool}
     */
    private static function pick_group_override(array $rows): array {
        if (count($rows) === 0) {
            return ['row' => null, 'ambiguous' => false];
        }

        // Sort by sortorder ascending; nulls sort last (core stores integers).
        usort($rows, static function (array $a, array $b): int {
            $sa = $a['sortorder'] ?? PHP_INT_MAX;
            $sb = $b['sortorder'] ?? PHP_INT_MAX;
            return $sa <=> $sb;
        });

        $first = $rows[0];
        $firstsort = $first['sortorder'] ?? PHP_INT_MAX;

        $ambiguous = false;
        if (count($rows) > 1) {
            $secondsort = $rows[1]['sortorder'] ?? PHP_INT_MAX;
            if ($firstsort === $secondsort) {
                $ambiguous = true;
            }
        }

        return ['row' => $first, 'ambiguous' => $ambiguous];
    }
}
