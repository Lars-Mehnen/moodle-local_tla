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
 * Integration tests for the U-shape origin repository.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla;

use local_tla\repository\course_ushape_origin_repository;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for course_ushape_origin_repository.
 */
#[CoversClass(course_ushape_origin_repository::class)]
final class course_ushape_origin_repository_test extends \advanced_testcase {
    /**
     * Create graded assignments with weekly deadlines and a two-group result.
     *
     * @param int $count Number of assignments.
     * @param int $start First deadline.
     * @return array [course, item ids]
     */
    private function create_course_with_grades(int $count, int $start): array {
        global $DB;

        $course = $this->getDataGenerator()->create_course();
        $itemids = [];
        for ($j = 0; $j < $count; $j++) {
            $assign = $this->getDataGenerator()->create_module('assign', [
                'course' => $course->id,
                'grade' => 10,
                'duedate' => $start + $j * WEEKSECS,
            ]);
            $itemids[] = (int) $DB->get_field('grade_items', 'id', [
                'itemtype' => 'mod', 'itemmodule' => 'assign', 'iteminstance' => $assign->id,
                'itemnumber' => 0, 'courseid' => $course->id,
            ], MUST_EXIST);
        }
        for ($u = 0; $u < 20; $u++) {
            $user = $this->getDataGenerator()->create_user();
            foreach ($itemids as $j => $itemid) {
                $DB->insert_record('grade_grades', (object) [
                    'itemid' => $itemid,
                    'userid' => $user->id,
                    'rawgrademin' => 0,
                    'rawgrademax' => 10,
                    'finalgrade' => $u < 10 ? 9.0 - ($j % 2) * 0.5 : 2.0 + ($u % 3) * 0.5,
                    'timecreated' => time(),
                    'timemodified' => time(),
                ]);
            }
        }
        return [$course, $itemids];
    }

    public function test_two_groups_from_start_and_gradebook_pass(): void {
        global $DB;
        $this->resetAfterTest(true);

        $start = time() - 10 * WEEKSECS;
        [$course] = $this->create_course_with_grades(6, $start);
        $DB->set_field('grade_items', 'gradepass', 60.0, ['courseid' => $course->id, 'itemtype' => 'course']);
        $DB->set_field('grade_items', 'grademax', 100.0, ['courseid' => $course->id, 'itemtype' => 'course']);

        $result = (new course_ushape_origin_repository())->get_ushape_origin((int) $course->id, 8);

        $this->assertSame('analysed', $result['status']);
        $this->assertSame(20, $result['participants']);
        $this->assertSame(6, $result['dueitems']);
        $this->assertTrue($result['twogroups']);
        $this->assertSame('observed', $result['fromstart']['status']);
        $this->assertSame('gradebook', $result['passsource']);
        $this->assertEqualsWithDelta(0.6, $result['passshare'], 1e-9);
        $this->assertCount(6, $result['items']);
    }

    public function test_future_deadlines_are_pending_and_setting_fallback(): void {
        $this->resetAfterTest(true);
        set_config('ushape_pass_share', '0.4', 'local_tla');

        $start = time() - 2 * WEEKSECS + HOURSECS;
        [$course] = $this->create_course_with_grades(6, $start);

        $result = (new course_ushape_origin_repository())->get_ushape_origin((int) $course->id, 8);

        $this->assertSame('unknown', $result['status']);
        $this->assertSame('toofewitems', $result['reason']);
        $this->assertSame(4, $result['pendingitems']);
        $this->assertSame('setting', $result['passsource']);
        $this->assertEqualsWithDelta(0.4, $result['passshare'], 1e-9);
    }

    public function test_invalid_course_id(): void {
        $this->expectException(\invalid_parameter_exception::class);
        (new course_ushape_origin_repository())->get_ushape_origin(0, 8);
    }
}
