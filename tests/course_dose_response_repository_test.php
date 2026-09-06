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
 * Tests for the learning dose-response repository.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla;

use local_tla\repository\course_dose_response_repository;
use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

#[CoversClass(course_dose_response_repository::class)]
final class course_dose_response_repository_test extends \advanced_testcase {
    /** @var int Counter for unique quiz_attempts.uniqueid values. */
    private int $uniqueid = 1;

    /**
     * Create a quiz with a usable maximum grade.
     *
     * @param int $courseid Course id.
     * @param float $sumgrades Quiz maximum (quiz.sumgrades).
     * @return \stdClass Module record.
     */
    private function make_quiz(int $courseid, float $sumgrades = 10.0): \stdClass {
        global $DB;

        $quiz = $this->getDataGenerator()->create_module('quiz', [
            'course' => $courseid,
            'grade' => 100,
            'attempts' => 0,
        ]);
        $DB->set_field('quiz', 'sumgrades', $sumgrades, ['id' => $quiz->id]);
        return $quiz;
    }

    /**
     * Insert a finished quiz attempt.
     *
     * @param int $quizid Quiz id.
     * @param int $userid User id.
     * @param int $attempt Attempt number.
     * @param float $sumgrades Attempt sum of marks.
     * @param int $preview Preview flag.
     */
    private function insert_attempt(
        int $quizid,
        int $userid,
        int $attempt,
        float $sumgrades,
        int $preview = 0
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
            'state' => 'finished',
            'timestart' => 0,
            'timefinish' => 0,
            'sumgrades' => $sumgrades,
        ]);
    }

    public function test_no_quizzes_returns_unknown(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $result = (new course_dose_response_repository())
            ->get_learning_dose_response((int) $course->id, 8);

        $this->assertSame(0, $result['observations']);
        $this->assertSame(0, $result['quizzes']);
        $this->assertSame('unknown', $result['analysis']['status']);
    }

    public function test_one_observation_per_student_quiz_pair(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->make_quiz((int) $course->id);
        // Student A: 3 attempts. Student B: 1 attempt.
        $a = $this->getDataGenerator()->create_user();
        $this->insert_attempt((int) $quiz->id, (int) $a->id, 1, 4.0);
        $this->insert_attempt((int) $quiz->id, (int) $a->id, 2, 6.0);
        $this->insert_attempt((int) $quiz->id, (int) $a->id, 3, 8.0);
        $b = $this->getDataGenerator()->create_user();
        $this->insert_attempt((int) $quiz->id, (int) $b->id, 1, 5.0);

        $result = (new course_dose_response_repository())
            ->get_learning_dose_response((int) $course->id, 1);

        // Two (student, quiz) pairs -> two observations.
        $this->assertSame(2, $result['observations']);
        $this->assertSame(1, $result['quizzes']);
    }

    public function test_preview_attempts_excluded(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->make_quiz((int) $course->id);
        $teacher = $this->getDataGenerator()->create_user();
        $this->insert_attempt((int) $quiz->id, (int) $teacher->id, 1, 9.0, 1);

        $result = (new course_dose_response_repository())
            ->get_learning_dose_response((int) $course->id, 1);

        $this->assertSame(0, $result['observations']);
    }

    public function test_course_isolation(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $other = $this->getDataGenerator()->create_course();
        $quiz = $this->make_quiz((int) $course->id);
        $otherquiz = $this->make_quiz((int) $other->id);
        $user = $this->getDataGenerator()->create_user();
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 1, 4.0);
        $this->insert_attempt((int) $otherquiz->id, (int) $user->id, 1, 4.0);

        $result = (new course_dose_response_repository())
            ->get_learning_dose_response((int) $course->id, 1);

        $this->assertSame(1, $result['observations']);
        $this->assertSame(1, $result['quizzes']);
    }

    public function test_deleted_quiz_excluded(): void {
        global $DB;

        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->make_quiz((int) $course->id);
        $user = $this->getDataGenerator()->create_user();
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 1, 4.0);

        $DB->set_field('course_modules', 'deletioninprogress', 1, ['id' => $quiz->cmid]);

        $result = (new course_dose_response_repository())
            ->get_learning_dose_response((int) $course->id, 1);

        $this->assertSame(0, $result['observations']);
        $this->assertSame(0, $result['quizzes']);
    }

    public function test_effect_uses_last_attempt_and_normalises(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        // Maximum 20; last attempt 15/20 = 75% for every student, exposure 2.
        $quiz = $this->make_quiz((int) $course->id, 20.0);
        for ($i = 0; $i < 10; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->insert_attempt((int) $quiz->id, (int) $user->id, 1, 5.0);
            $this->insert_attempt((int) $quiz->id, (int) $user->id, 2, 15.0);
        }
        // Add exposure variation so a fit is attempted.
        for ($i = 0; $i < 10; $i++) {
            $user = $this->getDataGenerator()->create_user();
            $this->insert_attempt((int) $quiz->id, (int) $user->id, 1, 15.0);
        }

        $result = (new course_dose_response_repository())
            ->get_learning_dose_response((int) $course->id, 8);

        $this->assertSame(20, $result['observations']);
        $this->assertSame('estimated', $result['analysis']['status']);
        // The 80% credible band at 2 attempts must be consistent with 75%.
        $lastcurve = end($result['analysis']['curve']);
        $this->assertGreaterThan(0.0, $lastcurve['med']);
    }

    public function test_query_count_is_constant(): void {
        global $DB;

        $this->resetAfterTest(true);

        $small = $this->getDataGenerator()->create_course();
        $this->seed_course((int) $small->id, 2);
        $large = $this->getDataGenerator()->create_course();
        $this->seed_course((int) $large->id, 6);

        $repository = new course_dose_response_repository();

        $before = $DB->perf_get_reads();
        $repository->get_learning_dose_response((int) $small->id, 1);
        $smallreads = $DB->perf_get_reads() - $before;

        $before = $DB->perf_get_reads();
        $repository->get_learning_dose_response((int) $large->id, 1);
        $largereads = $DB->perf_get_reads() - $before;

        $this->assertSame($smallreads, $largereads);
        $this->assertLessThanOrEqual(4, $largereads);
    }

    public function test_no_personal_data_returned(): void {
        $this->resetAfterTest(true);

        $course = $this->getDataGenerator()->create_course();
        $quiz = $this->make_quiz((int) $course->id);
        $user = $this->getDataGenerator()->create_user();
        $this->insert_attempt((int) $quiz->id, (int) $user->id, 1, 4.0);

        $result = (new course_dose_response_repository())
            ->get_learning_dose_response((int) $course->id, 1);

        $this->assertEqualsCanonicalizing(
            ['observations', 'quizzes', 'analysis'],
            array_keys($result)
        );
        $this->assertArrayNotHasKey('userid', $result);
    }

    /**
     * Seed a course with $quizcount quizzes, each with a couple of attempts.
     *
     * @param int $courseid Course id.
     * @param int $quizcount Number of quizzes.
     */
    private function seed_course(int $courseid, int $quizcount): void {
        for ($q = 0; $q < $quizcount; $q++) {
            $quiz = $this->make_quiz($courseid);
            $user = $this->getDataGenerator()->create_user();
            $this->insert_attempt((int) $quiz->id, (int) $user->id, 1, 4.0);
            $this->insert_attempt((int) $quiz->id, (int) $user->id, 2, 8.0);
        }
    }
}
