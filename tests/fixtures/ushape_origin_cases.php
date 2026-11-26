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
 * Shared, deterministic test cases for the U-shape origin analyser.
 *
 * Synthetic input arrays for a pure function; nothing is written to Moodle.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\tests;

defined('MOODLE_INTERNAL') || die();

/**
 * Case builder.
 */
final class ushape_origin_cases {
    /** @var int Fixed start time of the synthetic course. */
    public const START = 1767225600;

    /** @var int Pseudo-random state. */
    private static int $state = 1;

    /**
     * Deterministic pseudo-random number in [0, 1).
     *
     * @return float
     */
    private static function rand(): float {
        self::$state = (self::$state * 1103515245 + 12345) % 2147483648;
        return self::$state / 2147483648;
    }

    /**
     * Deterministic value around a centre, clamped to [0, 100].
     *
     * @param float $centre Centre.
     * @param float $spread Half width.
     * @return float
     */
    private static function around(float $centre, float $spread): float {
        return max(0.0, min(100.0, round($centre + (2 * self::rand() - 1) * $spread, 1)));
    }

    /**
     * Weekly activities with equal points.
     *
     * @param int $count Number of activities.
     * @return array
     */
    private static function items(int $count): array {
        $items = [];
        for ($j = 0; $j < $count; $j++) {
            $items[] = [
                'itemid' => 100 + $j,
                'ordertime' => self::START + ($j + 1) * 7 * 86400,
                'maxpoints' => 10.0,
                'name' => 'Activity ' . ($j + 1),
                'section' => 'Topic ' . ($j + 1),
                'hasdeadline' => true,
            ];
        }
        return $items;
    }

    /**
     * All cases with their expectations (dot paths into the result).
     *
     * @return array
     */
    public static function all(): array {
        self::$state = 42;
        $now = self::START + 400 * 86400;
        $cases = [];

        // 1) Two groups from the first activity on.
        $items = self::items(8);
        $grades = [];
        for ($u = 1; $u <= 40; $u++) {
            foreach ($items as $item) {
                $grades[$u][$item['itemid']] = $u <= 20 ? self::around(85, 10) : self::around(25, 10);
            }
        }
        $cases['fromstart'] = [
            'items' => $items, 'grades' => $grades, 'activity' => [], 'passshare' => 0.5, 'now' => $now,
            'expect' => [
                'status' => 'analysed',
                'twogroups' => true,
                'fromstart.status' => 'observed',
                'divergence.status' => 'notobserved',
            ],
        ];

        // 2) Same level for four activities, then separation from activity 5 on.
        $grades = [];
        for ($u = 1; $u <= 40; $u++) {
            foreach ($items as $j => $item) {
                if ($j < 4) {
                    $grades[$u][$item['itemid']] = self::around(65, 20);
                } else {
                    $grades[$u][$item['itemid']] = $u <= 20 ? self::around(88, 10) : self::around(20, 15);
                }
            }
        }
        $cases['divergence'] = [
            'items' => $items, 'grades' => $grades, 'activity' => [], 'passshare' => 0.5, 'now' => $now,
            'expect' => [
                'status' => 'analysed',
                'twogroups' => true,
                'fromstart.status' => 'notobserved',
                'divergence.status' => 'observed',
                'divergence.itemindex' => 4,
                'divergence.name' => 'Activity 5',
            ],
        ];

        // 3) Strong students stop after passing, weak students give up.
        $items = self::items(10);
        $grades = [];
        $activity = [];
        for ($u = 1; $u <= 45; $u++) {
            $passed = false;
            $cum = 0.0;
            $type = $u <= 15 ? 'strong' : ($u <= 30 ? 'middle' : 'weak');
            foreach ($items as $j => $item) {
                $value = match ($type) {
                    'strong' => self::around(95, 4),
                    'middle' => self::around(60, 10),
                    'weak' => self::around(15, 8),
                };
                $skip = ($type === 'strong' && $passed && self::rand() < 0.7)
                    || ($type === 'weak' && $j >= 6 && self::rand() < 0.8);
                if (!$skip) {
                    $grades[$u][$item['itemid']] = $value;
                    $cum += $value / 10.0;
                }
                $passed = $passed || $cum >= 50.0;
                // Daily activity: 3 events per day, reduced after passing / giving up.
                for ($d = 0; $d < 7; $d++) {
                    $day = self::START + ($j * 7 + $d + 1) * 86400;
                    $low = ($type === 'strong' && $passed) || ($type === 'weak' && $j >= 6);
                    $activity[$u][$day] = $low ? 1 : 3;
                }
            }
        }
        $cases['motivation'] = [
            'items' => $items, 'grades' => $grades, 'activity' => $activity, 'passshare' => 0.5, 'now' => $now,
            'expect' => [
                'status' => 'analysed',
                'motivation.status' => 'observed',
                'givingup.status' => 'descriptive',
            ],
        ];

        // 4) Unimodal, no structure: no pattern may be reported.
        $items8 = self::items(8);
        $grades8 = [];
        for ($u = 1; $u <= 40; $u++) {
            foreach ($items8 as $item) {
                $grades8[$u][$item['itemid']] = self::around(60, 30);
            }
        }
        $cases['unimodal'] = [
            'items' => $items8, 'grades' => $grades8, 'activity' => [], 'passshare' => 0.5, 'now' => $now,
            'expect' => [
                'status' => 'analysed',
                'twogroups' => false,
                'severity' => 'info',
                'fromstart.status' => 'notapplicable',
                'divergence.status' => 'notapplicable',
                'motivation.status' => 'notobserved',
            ],
        ];

        // 5) A due activity without any grade is "not yet marked", not "nobody submitted".
        $items9 = self::items(9);
        $grades9 = $grades8;
        $cases['ungraded'] = [
            'items' => $items9, 'grades' => $grades9, 'activity' => [], 'passshare' => 0.5, 'now' => $now,
            'expect' => [
                'status' => 'analysed',
                'dueitems' => 8,
                'ungradeditems' => 1,
                'pendingitems' => 0,
            ],
        ];

        // 6) Pending activities are excluded; with two due activities -> unknown.
        $cases['toofewitems'] = [
            'items' => $items, 'grades' => $grades, 'activity' => [], 'passshare' => 0.5,
            'now' => self::START + 15 * 86400,
            'expect' => [
                'status' => 'unknown',
                'reason' => 'toofewitems',
                'pendingitems' => 8,
            ],
        ];

        // 7) Too few participants.
        $cases['toofewparticipants'] = [
            'items' => $items, 'grades' => array_slice($grades, 0, 4, true), 'activity' => [],
            'passshare' => 0.5, 'now' => $now,
            'expect' => [
                'status' => 'unknown',
                'reason' => 'toofewparticipants',
            ],
        ];

        return $cases;
    }
}
