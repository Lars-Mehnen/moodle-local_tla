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
 * Demo-data generator for the local_tla dashboards.
 *
 * Creates clearly-marked, removable demo courses (three well-run courses plus
 * two instructive problem cases) that exercise every dashboard section. Not for
 * production content and not loaded by Moodle at runtime. Run as the web user
 * against the public/ dirroot:
 *   php local/tla/tools/make_demo_data.php [--undo [--yes]]
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->libdir . '/gradelib.php');
require_once($CFG->libdir . '/grade/grade_item.php');

global $DB, $CFG;
\core\session\manager::set_user(get_admin());
mt_srand(1234);

/** @var string Marker prefix on every demo course idnumber and demo username. */
const PREFIX = 'tla_demo_';

$studentrole = $DB->get_field('role', 'id', ['shortname' => 'student'], MUST_EXIST);
$now = time();
$day = 86400;

// Undo mode: preview (default) or delete with --yes.
if (in_array('--undo', $argv, true)) {
    $courses = $DB->get_records_select('course', $DB->sql_like('idnumber', '?'), [PREFIX . '%']);
    $users = $DB->get_records_select('user', $DB->sql_like('username', '?'), [PREFIX . '%']);
    $courses = array_filter($courses, fn($c) => str_starts_with($c->idnumber, PREFIX));
    $users = array_filter($users, fn($u) => str_starts_with($u->username, PREFIX));
    echo 'Demo courses: ' . count($courses) . ', demo users: ' . count($users) . "\n";
    if (!in_array('--yes', $argv, true)) {
        echo "Dry run. Re-run with --undo --yes to delete.\n";
        exit(0);
    }
    foreach ($courses as $c) {
        delete_course($c, false);
        echo "Deleted course {$c->shortname}.\n";
    }
    foreach ($users as $u) {
        if (is_siteadmin($u->id) || isguestuser($u->id)) {
            continue;
        }
        try {
            delete_user($u);
        } catch (\Throwable $e) {
            $DB->set_field('user', 'deleted', 1, ['id' => $u->id]);
        }
    }
    echo 'Deleted ' . count($users) . " demo users.\n";
    purge_all_caches();
    echo "Undo complete.\n";
    exit(0);
}

/**
 * Create or fetch a demo student user by index.
 *
 * @param int $n Stable per-student index.
 * @return int The user id.
 */
function demo_user(int $n): int {
    global $DB, $CFG;
    static $cache = [];
    $username = PREFIX . 's' . str_pad((string) $n, 3, '0', STR_PAD_LEFT);
    if (isset($cache[$username])) {
        return $cache[$username];
    }
    if ($existing = $DB->get_field('user', 'id', ['username' => $username])) {
        return $cache[$username] = (int) $existing;
    }
    $u = (object) [
        'auth' => 'manual', 'confirmed' => 1, 'mnethostid' => $CFG->mnet_localhost_id,
        'username' => $username, 'firstname' => 'Demo', 'lastname' => 'Student ' . $n,
        'email' => $username . '@example.invalid', 'password' => 'not-used',
    ];
    return $cache[$username] = (int) user_create_user($u, false, false);
}

/**
 * Clamp a value to a closed range.
 *
 * @param float $v Value to clamp.
 * @param float $lo Lower bound.
 * @param float $hi Upper bound.
 * @return float The clamped value.
 */
function clampi(float $v, float $lo = 0.0, float $hi = 100.0): float {
    return max($lo, min($hi, $v));
}

/**
 * Draw a normally distributed random value (Box-Muller).
 *
 * @param float $mean Distribution mean.
 * @param float $sd Standard deviation.
 * @return float The sampled value.
 */
function gauss(float $mean, float $sd): float {
    $u1 = max(1e-9, mt_rand() / mt_getrandmax());
    $u2 = mt_rand() / mt_getrandmax();
    return $mean + $sd * sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
}

/**
 * Create or fetch a demo course.
 *
 * @param string $suffix Short suffix appended to the marker prefix.
 * @param string $fullname Human-readable course name.
 * @return \stdClass The course record.
 */
function make_course(string $suffix, string $fullname): \stdClass {
    global $DB, $now, $day;
    $idnumber = PREFIX . $suffix;
    if ($c = $DB->get_record('course', ['idnumber' => $idnumber])) {
        return $c;
    }
    return create_course((object) [
        'fullname' => $fullname, 'shortname' => $idnumber, 'idnumber' => $idnumber,
        'category' => 1, 'visible' => 1, 'startdate' => $now - 70 * $day,
        'enablecompletion' => 1, 'summary' => 'local_tla demo course.', 'summaryformat' => FORMAT_HTML,
    ]);
}

/**
 * Create an assignment activity in a course.
 *
 * @param \stdClass $course The course record.
 * @param string $name Activity name.
 * @param int $duedate Due date as a Unix timestamp.
 * @return \stdClass The created module info (includes coursemodule and instance).
 */
function make_assign(\stdClass $course, string $name, int $duedate): \stdClass {
    global $CFG, $DB;
    $mi = (object) [
        'modulename' => 'assign', 'module' => $DB->get_field('modules', 'id', ['name' => 'assign'], MUST_EXIST),
        'course' => $course->id, 'section' => 0, 'visible' => 1,
        'name' => $name, 'intro' => 'Demo assignment', 'introformat' => FORMAT_HTML,
        'grade' => 100, 'duedate' => $duedate, 'allowsubmissionsfromdate' => $course->startdate,
        'cutoffdate' => $duedate + 14 * 86400, 'gradingduedate' => 0, 'alwaysshowdescription' => 1,
        'submissiondrafts' => 0, 'requiresubmissionstatement' => 0, 'sendnotifications' => 0,
        'sendlatenotifications' => 0, 'sendstudentnotifications' => 1, 'teamsubmission' => 0,
        'requireallteammemberssubmit' => 0, 'blindmarking' => 0, 'markingworkflow' => 0,
        'markingallocation' => 0, 'maxattempts' => 1, 'attemptreopenmethod' => 'none',
        'nosubmissions' => 0, 'completionsubmit' => 0,
        'assignsubmission_onlinetext_enabled' => 1, 'assignsubmission_onlinetext_wordlimit' => 0,
        'assignsubmission_onlinetext_wordlimit_enabled' => 0, 'assignsubmission_file_enabled' => 0,
        'assignsubmission_file_maxfiles' => 1, 'assignsubmission_file_maxsizebytes' => 1048576,
        'assignsubmission_comments_enabled' => 0,
        'assignfeedback_comments_enabled' => 1, 'assignfeedback_file_enabled' => 0,
        'assignfeedback_offline_enabled' => 0,
    ];
    return add_moduleinfo($mi, $course);
}

/**
 * Create a quiz activity (no questions; its maximum is set directly).
 *
 * @param \stdClass $course The course record.
 * @param string $name Activity name.
 * @param int $attemptsallowed Number of attempts allowed (0 = unlimited).
 * @param int $grademethod Grade method (1 highest, 2 average, 3 first, 4 last).
 * @param int $timeclose Close time as a Unix timestamp.
 * @return \stdClass The created module info (includes coursemodule and instance).
 */
function make_quiz(\stdClass $course, string $name, int $attemptsallowed, int $grademethod, int $timeclose): \stdClass {
    global $CFG, $DB;
    $review = 0x1113F;
    $mi = (object) [
        'modulename' => 'quiz', 'module' => $DB->get_field('modules', 'id', ['name' => 'quiz'], MUST_EXIST),
        'course' => $course->id, 'section' => 0, 'visible' => 1,
        'name' => $name, 'intro' => 'Demo quiz', 'introformat' => FORMAT_HTML,
        'grade' => 100, 'sumgrades' => 1, 'attempts' => $attemptsallowed, 'grademethod' => $grademethod,
        'questionsperpage' => 1, 'shufflequestions' => 0, 'shuffleanswers' => 1,
        'preferredbehaviour' => 'deferredfeedback', 'navmethod' => 'free',
        'timeopen' => $course->startdate, 'timeclose' => $timeclose, 'timelimit' => 0,
        'overduehandling' => 'autosubmit', 'graceperiod' => 0, 'canredoquestions' => 0,
        'delay1' => 0, 'delay2' => 0, 'showuserpicture' => 0, 'showblocks' => 0,
        'quizpassword' => '', 'password' => '', 'subnet' => '', 'browsersecurity' => '-',
        'decimalpoints' => 2, 'questiondecimalpoints' => -1, 'attemptonlast' => 0,
        'reviewattempt' => $review, 'reviewcorrectness' => $review, 'reviewmaxmarks' => $review,
        'reviewmarks' => $review, 'reviewspecificfeedback' => $review, 'reviewgeneralfeedback' => $review,
        'reviewrightanswer' => $review, 'reviewoverallfeedback' => $review,
        'completionattemptsexhausted' => 0, 'completionminattempts' => 0, 'completionpass' => 0,
    ];
    $created = add_moduleinfo($mi, $course);
    // Without questions the maximum defaults to 0, so set it so scores normalise to a percentage.
    $DB->set_field('quiz', 'sumgrades', 100, ['id' => $created->instance]);
    return $created;
}

/**
 * Set a student's final grade on a module's grade item.
 *
 * @param int $courseid The course id.
 * @param string $module Module name ('assign' or 'quiz').
 * @param int $instanceid Module instance id.
 * @param int $userid The student id.
 * @param float $pct Percentage grade in [0, 100].
 * @return void
 */
function set_mod_grade(int $courseid, string $module, int $instanceid, int $userid, float $pct): void {
    $gi = \grade_item::fetch([
        'itemtype' => 'mod', 'itemmodule' => $module, 'iteminstance' => $instanceid,
        'courseid' => $courseid, 'itemnumber' => 0,
    ]);
    if ($gi) {
        $gi->update_final_grade($userid, round($pct, 2), 'local_tla_demo');
    }
}

/**
 * Insert one finished quiz attempt.
 *
 * @param int $quizid The quiz instance id.
 * @param int $userid The student id.
 * @param int $attemptno The attempt number.
 * @param float $sumgrades The attempt score in quiz points.
 * @param int $timestart Attempt start time as a Unix timestamp.
 * @return void
 */
function add_quiz_attempt(int $quizid, int $userid, int $attemptno, float $sumgrades, int $timestart): void {
    global $DB;
    static $uniqueseq = null;
    if ($uniqueseq === null) {
        $uniqueseq = (int) $DB->get_field_sql('SELECT COALESCE(MAX(uniqueid), 0) FROM {quiz_attempts}') + 1000;
    }
    $DB->insert_record('quiz_attempts', (object) [
        'quiz' => $quizid, 'userid' => $userid, 'attempt' => $attemptno, 'uniqueid' => $uniqueseq++,
        'layout' => '', 'currentpage' => 0, 'preview' => 0, 'state' => 'finished',
        'timestart' => $timestart, 'timefinish' => $timestart + 1200, 'timemodified' => $timestart + 1200,
        'timemodifiedoffline' => 0, 'timecheckstate' => null, 'sumgrades' => round($sumgrades, 2),
        'gradednotificationsenttime' => null,
    ]);
}

/**
 * Insert one submitted assignment submission.
 *
 * @param int $assignid The assignment instance id.
 * @param int $userid The student id.
 * @param int $timemodified Submission time as a Unix timestamp.
 * @return void
 */
function add_submission(int $assignid, int $userid, int $timemodified): void {
    global $DB;
    $DB->insert_record('assign_submission', (object) [
        'assignment' => $assignid, 'userid' => $userid, 'timecreated' => $timemodified - 3600,
        'timemodified' => $timemodified, 'status' => 'submitted', 'groupid' => 0,
        'attemptnumber' => 0, 'latest' => 1,
    ]);
}

/**
 * Insert a course-level log event (cloning an existing row's shape).
 *
 * @param int $courseid The course id.
 * @param int $userid The acting user id.
 * @param string $component Frankenstyle component for the event breakdown.
 * @param string $eventname The event class name.
 * @param int $time Event time as a Unix timestamp.
 * @return void
 */
function log_event(int $courseid, int $userid, string $component, string $eventname, int $time): void {
    global $DB;
    static $template = null;
    if ($template === null) {
        $template = $DB->get_record_sql('SELECT * FROM {logstore_standard_log} ORDER BY id DESC', null, IGNORE_MULTIPLE)
            ?: false;
    }
    $ctx = \context_course::instance($courseid);
    $row = $template ? clone $template : (object) [];
    unset($row->id);
    $row->eventname = $eventname;
    $row->component = $component;
    $row->action = 'viewed';
    $row->target = 'course';
    $row->objecttable = null;
    $row->objectid = null;
    $row->crud = 'r';
    $row->edulevel = 2;
    $row->contextid = $ctx->id;
    $row->contextlevel = CONTEXT_COURSE;
    $row->contextinstanceid = $courseid;
    $row->userid = $userid;
    $row->courseid = $courseid;
    $row->relateduserid = null;
    $row->anonymous = 0;
    $row->other = 'N;';
    $row->timecreated = $time;
    $row->origin = 'web';
    $row->ip = '127.0.0.1';
    $row->realuserid = null;
    $DB->insert_record('logstore_standard_log', $row);
}

/**
 * Pick a student ability percentage for a course's distribution shape.
 *
 * @param string $shape One of 'normal', 'high_normal', 'ceiling', 'bimodal'.
 * @param int $idx Student index within the course (used for the bimodal split).
 * @return float Ability in [0, 100].
 */
function pick_ability(string $shape, int $idx): float {
    switch ($shape) {
        case 'ceiling':
            return clampi(gauss(90, 6));
        case 'bimodal':
            return ($idx % 2 === 0) ? clampi(gauss(42, 8)) : clampi(gauss(84, 7));
        case 'high_normal':
            return clampi(gauss(80, 9));
        default:
            return clampi(gauss(68, 12));
    }
}

// Course scenarios: three well-run courses plus two instructive problem cases.
$scenarios = [
    [
        'suffix' => 'A_fp', 'fullname' => 'Demo A: Funktionale Programmierung (gut gefuehrt)',
        'students' => 26, 'shape' => 'normal', 'dose' => 'positive',
        'quizattempts' => 4, 'grademethod' => 1, 'late' => 0.12,
    ],
    [
        'suffix' => 'B_algo', 'fullname' => 'Demo B: Algorithmen (exzellente Kohorte)',
        'students' => 24, 'shape' => 'high_normal', 'dose' => 'positive',
        'quizattempts' => 4, 'grademethod' => 1, 'late' => 0.08,
    ],
    [
        'suffix' => 'C_web', 'fullname' => 'Demo C: Web-Engineering (solide, aktiv)',
        'students' => 30, 'shape' => 'normal', 'dose' => 'positive',
        'quizattempts' => 5, 'grademethod' => 1, 'late' => 0.18,
    ],
    [
        'suffix' => 'D_stats', 'fullname' => 'Demo D: Statistik (Decken-Effekt, Quiz zu leicht)',
        'students' => 25, 'shape' => 'ceiling', 'dose' => 'flat',
        'quizattempts' => 8, 'grademethod' => 1, 'late' => 0.1,
    ],
    [
        'suffix' => 'E_db', 'fullname' => 'Demo E: Datenbanken (zwei Gruppen, viele Spaetabgaben)',
        'students' => 30, 'shape' => 'bimodal', 'dose' => 'moderate',
        'quizattempts' => 4, 'grademethod' => 1, 'late' => 0.6,
    ],
];

foreach ($scenarios as $s) {
    $course = make_course($s['suffix'], $s['fullname']);
    echo "== {$course->shortname} (id {$course->id}) ==\n";

    $students = [];
    for ($i = 1; $i <= $s['students']; $i++) {
        $uid = demo_user(($course->id * 1000) + $i);
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual']);
        if ($instance && !$DB->record_exists('user_enrolments', ['enrolid' => $instance->id, 'userid' => $uid])) {
            enrol_get_plugin('manual')->enrol_user($instance, $uid, $studentrole, $course->startdate);
        }
        $students[] = $uid;
    }

    $duedate1 = $now - 30 * $day;
    $duedate2 = $now - 10 * $day;
    $assign1 = make_assign($course, 'Uebung 1', $duedate1);
    $assign2 = make_assign($course, 'Uebung 2', $duedate2);
    $quizclose = $now - 5 * $day;
    $quiz1 = make_quiz($course, 'Quiz 1', $s['quizattempts'], $s['grademethod'], $quizclose);
    $quiz2 = make_quiz($course, 'Quiz 2', $s['quizattempts'], $s['grademethod'], $quizclose);

    foreach ($students as $idx => $uid) {
        $ability = pick_ability($s['shape'], $idx);

        // Assignment grades drive the score distribution.
        set_mod_grade($course->id, 'assign', $assign1->instance, $uid, clampi($ability + gauss(0, 6)));
        set_mod_grade($course->id, 'assign', $assign2->instance, $uid, clampi($ability + gauss(0, 6)));

        // Submissions: most on time, a share late (after the due date).
        $islate = (mt_rand() / mt_getrandmax()) < $s['late'];
        add_submission($assign1->instance, $uid, $islate ? $duedate1 + mt_rand(1, 6) * $day : $duedate1 - mt_rand(0, 5) * $day);
        add_submission($assign2->instance, $uid, $islate ? $duedate2 + mt_rand(1, 6) * $day : $duedate2 - mt_rand(0, 5) * $day);

        // Number of quiz attempts represents the practice "dose".
        if ($s['dose'] === 'positive') {
            $n = (int) round(1 + ($ability / 100) * ($s['quizattempts'] - 1) + gauss(0, 0.8));
        } else if ($s['dose'] === 'flat') {
            $n = mt_rand(2, $s['quizattempts']);
        } else {
            $n = (int) round(mt_rand(1, $s['quizattempts']) * 0.5 + ($ability / 100) * 2);
        }
        $n = max(1, min($s['quizattempts'], $n));

        foreach ([$quiz1, $quiz2] as $q) {
            $best = 0.0;
            for ($a = 1; $a <= $n; $a++) {
                if ($s['dose'] === 'positive') {
                    $perf = clampi(40 + 9 * $a + gauss(0, 7));
                } else if ($s['dose'] === 'flat') {
                    $perf = clampi($ability + gauss(0, 5));
                } else {
                    $perf = clampi($ability - 10 + 5 * $a + gauss(0, 8));
                }
                $tstart = $course->startdate + (15 + $a * 3) * $day + mt_rand(0, $day);
                add_quiz_attempt($q->instance, $uid, $a, $perf, $tstart);
                $best = max($best, $perf);
            }
            set_mod_grade($course->id, 'quiz', $q->instance, $uid, $best);
        }

        // Activity log events spread across the term.
        $events = mt_rand(8, 30);
        for ($e = 0; $e < $events; $e++) {
            $component = ['core', 'mod_quiz', 'mod_assign', 'mod_quiz'][mt_rand(0, 3)];
            $t = $course->startdate + (int) (mt_rand(0, 65) * $day * (0.5 + (mt_rand() / mt_getrandmax()) * 0.5));
            log_event($course->id, $uid, $component, '\\' . $component . '\\event\\course_module_viewed', min($now, $t));
        }
    }
    echo "  students={$s['students']} assigns=2 quizzes=2 shape={$s['shape']} dose={$s['dose']}\n";
}

rebuild_course_cache(0, true);
purge_all_caches();
echo "Demo data created.\n";
