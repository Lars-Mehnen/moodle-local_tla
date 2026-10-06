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
 * Tests for the quiz progress repository.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla;

use local_tla\repository\quiz_progress_repository;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

/**
 * Tests for quiz_progress_repository_test.
 */
#[CoversClass(quiz_progress_repository::class)]
final class quiz_progress_repository_test extends \advanced_testcase {
    /** @var int Counter for unique quiz_attempts.uniqueid values. */
    private int $uniqueid = 1;

    /**
     * Create a quiz with a usable maximum grade.
     *
     * @param int $courseid Course id.
     * @param float $sumgrades Quiz maximum (quiz.sumgrades).
     * @param int $maxattempts Allowed attempts (0 = unlimited).
     * @return \stdClass Module record (has ->id and ->cmid).
     */
    private function make_quiz(int $courseid, float $sumgrades = 10.0, int $maxattempts = 0): \stdClass {
        global $DB;

        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $courseid,
            'grade' => 100,
            'attempts' => $maxattempts,
        ]);
        $DB->set_field('quiz', 'sumgrades', $sumgrades, ['id' => $quiz->id]);
        return $quiz;
    }

    /**
     * Insert a quiz attempt.
     *
     * @param int $quizid Quiz id.
     * @param int $userid User id.
     * @param int $attempt Attempt number.
     * @param string $state Attempt state.
     * @param float|null $sumgrades Attempt sum of marks.
     * @param int $preview Preview flag.
     * @param int $timefinish Finish timestamp.
     */
    private function insert_attempt(
        int $quizid,
        int $userid,
        int $attempt,
        string $state,
        ?float $sumgrades,
        int $preview = 0,
        int $timefinish = 0
    ): void {
        global $DB;

        $DB->insert_record('quiz_attempts', (object) [
            'quiz' => $quizid,
            'userid' => $userid,
            'attempt' => $attempt,
            'uniqueid' => $this->uniqueid++,
            'layout' => '',
            'currentpage' => 0,
            'preview' => $preview,
            'state' => $state,
            'timestart' => $timefinish > 0 ? $timefinish - 60 : 0,
            'timefinish' => $timefinish,
            'sumgrades' => $sumgrades,
        ]);
    }

    /**
     * Find a quiz result by quizid.
     *
     * @param array $result Repository result.
     * @param int $quizid Quiz id.
     * @return array|null
     */
    private function find_quiz(array $result, int $quizid): ?array {
        foreach ($result as $quiz) {
            if ($quiz['quizid'] === $quizid) {
                return $quiz;
            }
        }
        return null;
    }

    public function test_two_completed_attempts(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->make_quiz((int) $course->id);
        $user = $this->getDataGenerator()->create_user();
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 1, 'finished', 4.0);
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 2, 'finished', 8.0);

        $result = (new quiz_progress_repository())
            ->get_quiz_attempt_progress((int) $course->id, 1);

        $q = $this->find_quiz($result, (int) $quiz->id);
        $this->assertNotNull($q);
        $this->assertSame(1, $q['participants']);
        $this->assertSame(2, $q['totalcompletedattempts']);
    }

    public function test_three_completed_attempts(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->make_quiz((int) $course->id);
        $user = $this->getDataGenerator()->create_user();
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 1, 'finished', 4.0);
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 2, 'finished', 6.0);
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 3, 'finished', 8.0);

        $result = (new quiz_progress_repository())
            ->get_quiz_attempt_progress((int) $course->id, 1);

        $q = $this->find_quiz($result, (int) $quiz->id);
        $this->assertSame(3, $q['totalcompletedattempts']);
        $this->assertSame(1, $q['participants']);
        $this->assertEqualsWithDelta(3.0, $q['analysis']['meanattempts'], 1e-9);
    }

    public function test_first_last_best_are_correct(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->make_quiz((int) $course->id, 10.0);
        $user = $this->getDataGenerator()->create_user();
        // First=5 (50%), then 7 (70%), then 6 (60%): best is the middle attempt.
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 1, 'finished', 5.0);
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 2, 'finished', 7.0);
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 3, 'finished', 6.0);

        $result = (new quiz_progress_repository())
            ->get_quiz_attempt_progress((int) $course->id, 1);

        $q = $this->find_quiz($result, (int) $quiz->id);
        $this->assertEqualsWithDelta(50.0, $q['analysis']['meanfirst'], 1e-9);
        $this->assertEqualsWithDelta(60.0, $q['analysis']['meanlast'], 1e-9);
        $this->assertEqualsWithDelta(70.0, $q['analysis']['meanbest'], 1e-9);
    }

    public function test_inprogress_attempt_excluded(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->make_quiz((int) $course->id);
        $user = $this->getDataGenerator()->create_user();
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 1, 'finished', 4.0);
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 2, 'finished', 8.0);
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 3, 'inprogress', null);

        $result = (new quiz_progress_repository())
            ->get_quiz_attempt_progress((int) $course->id, 1);

        $q = $this->find_quiz($result, (int) $quiz->id);
        $this->assertSame(2, $q['totalcompletedattempts']);
    }

    public function test_preview_attempts_excluded(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->make_quiz((int) $course->id);
        $teacher = $this->getDataGenerator()->create_user();
        $this->insert_attempt((int) $quiz->id, (int) $teacher->id, 1, 'finished', 9.0, 1);
        $this->insert_attempt((int) $quiz->id, (int) $teacher->id, 2, 'finished', 9.0, 1);

        $result = (new quiz_progress_repository())
            ->get_quiz_attempt_progress((int) $course->id, 1);

        $q = $this->find_quiz($result, (int) $quiz->id);
        $this->assertNotNull($q);
        $this->assertSame(0, $q['totalcompletedattempts']);
        $this->assertSame(0, $q['participants']);
    }

    public function test_single_attempt_user_not_included(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->make_quiz((int) $course->id);
        $user = $this->getDataGenerator()->create_user();
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 1, 'finished', 5.0);

        $result = (new quiz_progress_repository())
            ->get_quiz_attempt_progress((int) $course->id, 1);

        $q = $this->find_quiz($result, (int) $quiz->id);
        $this->assertNotNull($q);
        $this->assertSame(0, $q['participants']);
        $this->assertSame(1, $q['totalcompletedattempts']);
    }

    public function test_percentage_normalization(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->make_quiz((int) $course->id, 20.0);
        $user = $this->getDataGenerator()->create_user();
        // 10/20 = 50% on both attempts.
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 1, 'finished', 10.0);
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 2, 'finished', 10.0);

        $result = (new quiz_progress_repository())
            ->get_quiz_attempt_progress((int) $course->id, 1);

        $q = $this->find_quiz($result, (int) $quiz->id);
        $this->assertEqualsWithDelta(50.0, $q['analysis']['meanfirst'], 1e-9);
        $this->assertEqualsWithDelta(50.0, $q['analysis']['meanlast'], 1e-9);
    }

    public function test_multiple_students(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->make_quiz((int) $course->id);
        for ($i = 0; $i < 3; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->insert_attempt((int) $quiz->id, (int) $user->id, 1, 'finished', 4.0);
            $this->insert_attempt((int) $quiz->id, (int) $user->id, 2, 'finished', 8.0);
        }

        $result = (new quiz_progress_repository())
            ->get_quiz_attempt_progress((int) $course->id, 1);

        $q = $this->find_quiz($result, (int) $quiz->id);
        $this->assertSame(3, $q['participants']);
        $this->assertSame(6, $q['totalcompletedattempts']);
    }

    public function test_course_isolation(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $othercourse = $this->getDataGenerator()->create_course();
        $quiz = $this->make_quiz((int) $course->id);
        $otherquiz = $this->make_quiz((int) $othercourse->id);
        $user = $this->getDataGenerator()->create_user();
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 1, 'finished', 4.0);
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 2, 'finished', 8.0);
        $this->insert_attempt((int) $otherquiz->id, (int) $user->id, 1, 'finished', 4.0);
        $this->insert_attempt((int) $otherquiz->id, (int) $user->id, 2, 'finished', 8.0);

        $result = (new quiz_progress_repository())
            ->get_quiz_attempt_progress((int) $course->id, 1);

        $this->assertCount(1, $result);
        $this->assertSame((int) $quiz->id, $result[0]['quizid']);
    }

    public function test_deleted_quiz_excluded(): void {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->make_quiz((int) $course->id);
        $user = $this->getDataGenerator()->create_user();
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 1, 'finished', 4.0);
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 2, 'finished', 8.0);

        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $quiz->cmid]);

        $result = (new quiz_progress_repository())
            ->get_quiz_attempt_progress((int) $course->id, 1);

        $this->assertNull($this->find_quiz($result, (int) $quiz->id));
    }

    public function test_minobservations_respected(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->make_quiz((int) $course->id);
        $user = $this->getDataGenerator()->create_user();
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 1, 'finished', 4.0);
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 2, 'finished', 8.0);

        $result = (new quiz_progress_repository())
            ->get_quiz_attempt_progress((int) $course->id, 8);

        $q = $this->find_quiz($result, (int) $quiz->id);
        $this->assertNotNull($q);
        $this->assertSame(1, $q['participants']);
        $this->assertSame('unknown', $q['analysis']['status']);
    }

    public function test_no_personal_data_returned(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->make_quiz((int) $course->id);
        $user = $this->getDataGenerator()->create_user();
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 1, 'finished', 4.0);
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 2, 'finished', 8.0);

        $result = (new quiz_progress_repository())
            ->get_quiz_attempt_progress((int) $course->id, 1);

        $q = $this->find_quiz($result, (int) $quiz->id);
        $this->assertEqualsCanonicalizing(
            ['cmid', 'quizid', 'name', 'participants', 'totalcompletedattempts', 'analysis'],
            array_keys($q)
        );
        $this->assertArrayNotHasKey('userid', $q);
    }

    public function test_query_count_is_constant(): void {
        global $DB;

        $this->resetAfterTest(true);

        $small = $this->getDataGenerator()->create_course();
        for ($i = 0; $i < 2; $i++) {
            $this->make_quiz((int) $small->id);
        }
        $large = $this->getDataGenerator()->create_course();
        for ($i = 0; $i < 6; $i++) {
            $this->make_quiz((int) $large->id);
        }

        $repository = new quiz_progress_repository();

        $before = $DB->perf_get_reads();
        $repository->get_quiz_attempt_progress((int) $small->id, 1);
        $smallreads = $DB->perf_get_reads() - $before;

        $before = $DB->perf_get_reads();
        $repository->get_quiz_attempt_progress((int) $large->id, 1);
        $largereads = $DB->perf_get_reads() - $before;

        $this->assertSame($smallreads, $largereads);
        $this->assertLessThanOrEqual(4, $largereads);
    }

    public function test_equal_timestamps_are_ordered_by_attempt_number(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->make_quiz((int) $course->id, 10.0);
        $user = $this->getDataGenerator()->create_user();
        // Both attempts finish at the same timestamp; ordering must use the.
        // Attempt number, so first = attempt 1 (40%), last = attempt 2 (80%).
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 1, 'finished', 4.0, 0, 1000);
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 2, 'finished', 8.0, 0, 1000);

        $result = (new quiz_progress_repository())
            ->get_quiz_attempt_progress((int) $course->id, 1);

        $q = $this->find_quiz($result, (int) $quiz->id);
        $this->assertEqualsWithDelta(40.0, $q['analysis']['meanfirst'], 1e-9);
        $this->assertEqualsWithDelta(80.0, $q['analysis']['meanlast'], 1e-9);
    }

    public function test_improving_quiz_stays_green(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->make_quiz((int) $course->id, 10.0);
        for ($i = 0; $i < 8; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->insert_attempt((int) $quiz->id, (int) $user->id, 1, 'finished', 4.0);
            $this->insert_attempt((int) $quiz->id, (int) $user->id, 2, 'finished', 7.0);
        }

        $result = (new quiz_progress_repository())
            ->get_quiz_attempt_progress((int) $course->id, 8);

        $q = $this->find_quiz($result, (int) $quiz->id);
        // Quiz attempt progress compares comparable attempts, so it keeps green.
        $this->assertSame('improving', $q['analysis']['status']);
        $this->assertSame('green', $q['analysis']['severity']);
    }

    public function test_declining_quiz_stays_red(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->make_quiz((int) $course->id, 10.0);
        for ($i = 0; $i < 8; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->insert_attempt((int) $quiz->id, (int) $user->id, 1, 'finished', 7.0);
            $this->insert_attempt((int) $quiz->id, (int) $user->id, 2, 'finished', 4.0);
        }

        $result = (new quiz_progress_repository())
            ->get_quiz_attempt_progress((int) $course->id, 8);

        $q = $this->find_quiz($result, (int) $quiz->id);
        $this->assertSame('declining', $q['analysis']['status']);
        $this->assertSame('red', $q['analysis']['severity']);
    }

    public function test_only_finished_state_counts(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->make_quiz((int) $course->id, 10.0);
        $user = $this->getDataGenerator()->create_user();
        // Two finished attempts count; overdue/abandoned/inprogress do not.
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 1, 'finished', 5.0);
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 2, 'finished', 7.0);
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 3, 'overdue', 3.0);
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 4, 'abandoned', 1.0);
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 5, 'inprogress', null);

        $result = (new quiz_progress_repository())
            ->get_quiz_attempt_progress((int) $course->id, 1);

        $q = $this->find_quiz($result, (int) $quiz->id);
        $this->assertSame(2, $q['totalcompletedattempts']);
        $this->assertEqualsWithDelta(50.0, $q['analysis']['meanfirst'], 1e-9);
        $this->assertEqualsWithDelta(70.0, $q['analysis']['meanlast'], 1e-9);
    }
}
