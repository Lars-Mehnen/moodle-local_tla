<?php
// This file is part of Moodle - https://moodle.org/
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
 * Course analytics dashboard page.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../config.php');

use local_tla\indicator\traffic_light_calculator;
use local_tla\service\course_dashboard_service;
use local_tla\local\dashboard_access;
use local_tla\output\assessment_design_presenter;

$courseid = required_param('id', PARAM_INT);
$period = optional_param('period', 28, PARAM_INT);
$designpage = max(0, optional_param('designpage', 0, PARAM_INT));

$allowedperiods = [7, 28, 90];
if (!in_array($period, $allowedperiods, true)) {
    $period = 28;
}

$timeend = optional_param('timeend', time() + 1, PARAM_INT);
$timestart = optional_param(
    'timestart',
    $timeend - ($period * DAYSECS),
    PARAM_INT
);

// Defend against manipulated or nonsensical time parameters so the service is.
// Never called with an invalid interval.
if ($timestart < 0 || $timestart >= $timeend) {
    $timeend = time() + 1;
    $timestart = $timeend - ($period * DAYSECS);
}

// Single access guard: valid course, logged-in course context, local/tla:view.
$course = get_course($courseid);
$context = dashboard_access::validate((int) $course->id);
require_login($course);

$url = new moodle_url('/local/tla/dashboard.php', [
    'id' => $course->id,
    'designpage' => $designpage,
    'period' => $period,
    'timestart' => $timestart,
    'timeend' => $timeend,
]);

$PAGE->set_url($url);
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_pagelayout('incourse');
$PAGE->set_title(get_string('dashboardtitle', 'local_tla'));
$PAGE->set_heading(format_string($course->fullname));

// Config keys must match the names persisted by settings.php.
$yellowconfig = get_config('local_tla', 'deadline_yellow');
$redconfig = get_config('local_tla', 'deadline_red');
$minobservationsconfig = get_config('local_tla', 'min_observations');

$yellowthreshold = $yellowconfig === false ? 0.25 : (float) $yellowconfig;
$redthreshold = $redconfig === false ? 0.50 : (float) $redconfig;
$minobservations = $minobservationsconfig === false
    ? 8
    : (int) $minobservationsconfig;

// Harden against misconfigured admin settings so a bad value can never crash.
// The dashboard: clamp thresholds to [0, 1], keep yellow <= red, and enforce a.
// Minimum observation count of at least one.
$yellowthreshold = min(1.0, max(0.0, $yellowthreshold));
$redthreshold = min(1.0, max(0.0, $redthreshold));
if ($yellowthreshold > $redthreshold) {
    $yellowthreshold = $redthreshold;
}
$minobservations = max(1, $minobservations);

$service = new course_dashboard_service();
$data = $service->get_course_dashboard_data(
    (int) $course->id,
    $timestart,
    $timeend,
    $minobservations
);

// Only classifiable submissions form the denominator: noduedate cannot be late.
// And unresolved must not count as an unremarkable observation.
$classifiable = (int) $data['assignmentsubmissions']['ontime']
    + (int) $data['assignmentsubmissions']['late'];

$trafficlight = (new traffic_light_calculator())->calculate(
    (int) $data['assignmentsubmissions']['late'],
    $classifiable,
    $yellowthreshold,
    $redthreshold,
    $minobservations
);

$trafficlightstatus = $trafficlight['status'];
$trafficlightlabel = get_string(
    'trafficlight_' . $trafficlightstatus,
    'local_tla'
);

$trafficlightclass = match ($trafficlightstatus) {
    'green' => 'bg-success',
    'yellow' => 'bg-warning text-dark',
    'red' => 'bg-danger',
    default => 'bg-secondary',
};

$trafficlightpercentage = $trafficlight['rate'] === null
    ? null
    : round($trafficlight['rate'] * 100, 1);

$eventsperday = array_map(static fn(array $row): array => [
    'date' => userdate($row['daystart'], get_string('strftimedatefullshort', 'langconfig')),
    'eventcount' => $row['eventcount'],
], $data['eventsperday']);

$activeusersperday = array_map(static fn(array $row): array => [
    'date' => userdate($row['daystart'], get_string('strftimedatefullshort', 'langconfig')),
    'activeusers' => $row['activeusers'],
], $data['activeusersperday']);

$eventlabels = array_column($eventsperday, 'date');
$eventvalues = array_map(
    static fn(array $row): int => (int) $row['eventcount'],
    $eventsperday
);

$eventchart = new core\chart_line();
$eventchart->set_labels($eventlabels);
$eventchart->add_series(new core\chart_series(
    get_string('events', 'local_tla'),
    $eventvalues
));

$activeuserlabels = array_column($activeusersperday, 'date');
$activeuservalues = array_map(
    static fn(array $row): int => (int) $row['activeusers'],
    $activeusersperday
);

$activeuserchart = new core\chart_line();
$activeuserchart->set_labels($activeuserlabels);
$activeuserchart->add_series(new core\chart_series(
    get_string('activeusers', 'local_tla'),
    $activeuservalues
));

$stringmanager = get_string_manager();

$moduleevents = array_map(
    static function (array $row) use ($stringmanager): array {
        $component = (string) $row['component'];
        $displayname = $component;

        if ($stringmanager->string_exists('pluginname', $component)) {
            $displayname = get_string('pluginname', $component);
        }

        return [
            'component' => $component,
            'displayname' => $displayname,
            'eventcount' => $row['eventcount'],
        ];
    },
    $data['moduleevents']
);

$periodoptions = [];
foreach ($allowedperiods as $days) {
    $periodoptions[] = [
        'days' => $days,
        'label' => get_string('perioddays', 'local_tla', $days),
        'url' => (new moodle_url('/local/tla/dashboard.php', [
            'id' => $course->id,
            'period' => $days,
        ]))->out(false),
        'active' => $period === $days,
    ];
}

// Score distributions: sort problematic first, then show a bounded number.
$severityorder = ['red' => 0, 'yellow' => 1, 'green' => 2, 'info' => 3, 'unknown' => 4];
$scoredistributionsall = $data['scoredistributions'];
usort(
    $scoredistributionsall,
    static function (array $a, array $b) use ($severityorder): int {
        $sa = $severityorder[$a['analysis']['severity']] ?? 9;
        $sb = $severityorder[$b['analysis']['severity']] ?? 9;
        return [$sa, $a['name']] <=> [$sb, $b['name']];
    }
);

$scoredistributionlimit = 20;
$scoredistributionstotal = count($scoredistributionsall);
$scoredistributionsshown = array_slice($scoredistributionsall, 0, $scoredistributionlimit);

$severityclasses = [
    'red' => 'bg-danger',
    'yellow' => 'bg-warning text-dark',
    'green' => 'bg-success',
    'unknown' => 'bg-secondary',
    'info' => 'bg-secondary',
];

$scoredistributions = array_map(
    static function (array $activity) use ($stringmanager, $severityclasses): array {
        $analysis = $activity['analysis'];
        $status = $analysis['status'];
        $severity = $analysis['severity'];

        $modulecomponent = 'mod_' . $activity['module'];
        $moduletype = $activity['module'];
        if ($stringmanager->string_exists('pluginname', $modulecomponent)) {
            $moduletype = get_string('pluginname', $modulecomponent);
        }

        $fmtpct = static fn(?float $share): ?string =>
            $share === null ? null : format_float($share * 100, 1);
        $fmtval = static fn(?float $value): ?string =>
            $value === null ? null : format_float($value, 1);

        $maxcount = 0;
        foreach ($analysis['histogram'] as $bin) {
            $maxcount = max($maxcount, $bin['count']);
        }
        $histogram = array_map(
            static function (array $bin) use ($maxcount): array {
                return [
                    'from' => $bin['from'],
                    'to' => $bin['to'],
                    'count' => $bin['count'],
                    'barwidth' => $maxcount > 0
                        ? round($bin['count'] / $maxcount * 100, 1)
                        : 0,
                ];
            },
            $analysis['histogram']
        );

        return [
            'name' => format_string($activity['name']),
            'moduletype' => $moduletype,
            'validgrades' => $activity['validgrades'],
            'invalidgrades' => $activity['invalidgrades'],
            'hasinvalidgrades' => $activity['invalidgrades'] > 0,
            'analyzed' => $status !== 'unknown',
            'median' => $fmtval($analysis['median']),
            'q1' => $fmtval($analysis['q1']),
            'q3' => $fmtval($analysis['q3']),
            'lowshare' => $fmtpct($analysis['lowshare']),
            'middleshare' => $fmtpct($analysis['middleshare']),
            'highshare' => $fmtpct($analysis['highshare']),
            'statuslabel' => get_string('scorestatus_' . $status, 'local_tla'),
            'explanation' => get_string($activity['interpretation']['messagekey'], 'local_tla'),
            'recommendation' => $activity['interpretation']['recommendationkey'] === null ? ''
                : get_string($activity['interpretation']['recommendationkey'], 'local_tla'),
            'severityclass' => $severityclasses[$severity] ?? 'bg-secondary',
            'histogram' => $histogram,
        ];
    },
    $scoredistributionsshown
);

// Learning progress: quiz attempt progress, sorted problematic first, bounded.
$fmtpct = static fn(?float $share): ?string =>
    $share === null ? null : format_float($share * 100, 1);
$fmtval = static fn(?float $value): ?string =>
    $value === null ? null : format_float($value, 1);
$fmtsigned = static fn(?float $value): ?string =>
    $value === null ? null : format_float($value, 1);

$quizprogressall = $data['quizprogress'];
usort(
    $quizprogressall,
    static function (array $a, array $b) use ($severityorder): int {
        $sa = $severityorder[$a['analysis']['severity']] ?? 9;
        $sb = $severityorder[$b['analysis']['severity']] ?? 9;
        return [$sa, $a['name']] <=> [$sb, $b['name']];
    }
);
$quizprogresstotal = count($quizprogressall);
$quizprogressshown = array_slice($quizprogressall, 0, 20);

$quizprogress = array_map(
    static function (array $quiz) use ($severityclasses, $fmtpct, $fmtval, $fmtsigned): array {
        $analysis = $quiz['analysis'];
        $status = $analysis['status'];
        return [
            'name' => format_string($quiz['name']),
            'participants' => $quiz['participants'],
            'totalcompletedattempts' => $quiz['totalcompletedattempts'],
            'analyzed' => $status !== 'unknown',
            'meanfirst' => $fmtval($analysis['meanfirst']),
            'meanlast' => $fmtval($analysis['meanlast']),
            'meanbest' => $fmtval($analysis['meanbest']),
            'medianchange' => $fmtsigned($analysis['medianchange']),
            'improvedshare' => $fmtpct($analysis['improvedshare']),
            'stableshare' => $fmtpct($analysis['stableshare']),
            'declinedshare' => $fmtpct($analysis['declinedshare']),
            'meanattempts' => $fmtval($analysis['meanattempts']),
            'statuslabel' => get_string('progressstatus_' . $status, 'local_tla'),
            'explanation' => get_string('progressexpl_' . $status, 'local_tla'),
            'severityclass' => $severityclasses[$analysis['severity']] ?? 'bg-secondary',
        ];
    },
    $quizprogressshown
);

// Course-level progress (single, conservative summary).
$cp = $data['courseprogress'];
$courseprogress = [
    'activities' => $cp['activities'],
    'participants' => $cp['participants'],
    'analyzed' => $cp['status'] !== 'unknown',
    'hasactivities' => $cp['activities'] >= 2,
    'medianchange' => $fmtsigned($cp['medianchange']),
    'improvedshare' => $fmtpct($cp['improvedshare']),
    'stableshare' => $fmtpct($cp['stableshare']),
    'declinedshare' => $fmtpct($cp['declinedshare']),
    'statuslabel' => get_string('progressstatus_' . $cp['status'], 'local_tla'),
    'severityclass' => $severityclasses[$cp['severity']] ?? 'bg-secondary',
];

// Learning dose-response (Bayesian Emax fit over practice vs performance).
$dr = $data['doseresponse'];
$dra = $dr['analysis'];
$drfit = $dra['fitquality'] ?? 'unknown';
$drppcdetail = null;
if ($dra['ppc']['obssd'] !== null) {
    $drppcdetail = get_string('doseresponseppcdetail', 'local_tla', (object) [
        'obs' => $fmtval($dra['ppc']['obssd']),
        'rep' => $fmtval($dra['ppc']['repsdmedian']),
    ]);
}
$drexplained = $dra['explainedvariance'] === null
    ? null
    : $fmtval($dra['explainedvariance'] * 100);

// Data table backing the chart: per whole attempt, the observed average (where a.
// Large enough group exists) and the posterior curve with its 80% band.
$drcurvedata = [];
if ($dra['status'] !== 'unknown') {
    $binbyc = [];
    foreach ($dra['bins'] as $bin) {
        $binbyc[(int) round($bin['c'])] = $bin['meaneffect'];
    }
    foreach ($dra['curve'] as $point) {
        $ci = (int) round($point['c']);
        $drcurvedata[] = [
            'c' => $ci,
            'observed' => isset($binbyc[$ci]) ? $fmtval($binbyc[$ci]) : null,
            'median' => $fmtval($point['med']),
            'lo' => $fmtval($point['lo']),
            'hi' => $fmtval($point['hi']),
        ];
    }
}
// Build the posterior curve as a Moodle Chart.js line chart, consistent with the.
// Other dashboard graphs. The x-axis is whole completed attempts; the credible.
// Band is drawn as lower/median/upper bound lines with the aggregate per-attempt.
// Average overlaid.
$doseresponsechart = '';
if ($dra['status'] !== 'unknown' && !empty($dra['curve'])) {
    $curvelabels = array_map(
        static fn(array $p): string => (string) (int) round($p['c']),
        $dra['curve']
    );
    $index = array_flip($curvelabels);

    $lowerseries = array_map(static fn(array $p): float => round($p['lo'], 1), $dra['curve']);
    $medianseries = array_map(static fn(array $p): float => round($p['med'], 1), $dra['curve']);
    $upperseries = array_map(static fn(array $p): float => round($p['hi'], 1), $dra['curve']);

    // Observed aggregate means aligned to the label positions (null elsewhere).
    $observedseries = array_fill(0, count($curvelabels), null);
    foreach ($dra['bins'] as $bin) {
        $key = (string) (int) round($bin['c']);
        if (isset($index[$key])) {
            $observedseries[$index[$key]] = round($bin['meaneffect'], 1);
        }
    }

    $chart = new core\chart_line();
    $chart->set_smooth(true);
    $chart->set_labels($curvelabels);

    // The band is a translucent fill so the median and observed lines drawn on.
    // Top of it stay visible; its own boundary lines are faint.
    $lower = new core\chart_series(get_string('doseresponselowerband', 'local_tla'), $lowerseries);
    $lower->set_color('rgba(77, 171, 247, 0.28)');
    $upper = new core\chart_series(get_string('doseresponseupperband', 'local_tla'), $upperseries);
    $upper->set_color('rgba(77, 171, 247, 0.18)');
    $upper->set_fill('-1');
    $median = new core\chart_series(get_string('doseresponsemedianline', 'local_tla'), $medianseries);
    $median->set_color('#1c7ed6');
    $observed = new core\chart_series(get_string('doseresponseobserved', 'local_tla'), $observedseries);
    $observed->set_color('#e8590c');

    // Order matters: the upper band fills down to the lower band beneath it, then.
    // The median and observed lines are drawn on top.
    $chart->add_series($lower);
    $chart->add_series($upper);
    $chart->add_series($median);
    $chart->add_series($observed);

    $xaxis = $chart->get_xaxis(0, true);
    $xaxis->set_label(get_string('doseresponseaxisx', 'local_tla'));
    $yaxis = $chart->get_yaxis(0, true);
    $yaxis->set_label(get_string('doseresponseaxisy', 'local_tla'));
    $yaxis->set_min(0);
    $yaxis->set_max(100);

    $doseresponsechart = $OUTPUT->render($chart);
}

$doseresponse = [
    'analyzed' => $dra['status'] !== 'unknown',
    'observations' => $dr['observations'],
    'quizzes' => $dr['quizzes'],
    'statuslabel' => get_string('doseresponsefit_' . $drfit, 'local_tla'),
    'explanation' => get_string('doseresponsefitexpl_' . $drfit, 'local_tla'),
    'severityclass' => $severityclasses[$dra['severity']] ?? 'bg-secondary',
    'emaxmedian' => $fmtval($dra['emax']['median']),
    'emaxlo' => $fmtval($dra['emax']['lo']),
    'emaxhi' => $fmtval($dra['emax']['hi']),
    'ec50median' => $fmtval($dra['ec50']['median']),
    'ec50lo' => $fmtval($dra['ec50']['lo']),
    'ec50hi' => $fmtval($dra['ec50']['hi']),
    'sigmamedian' => $fmtval($dra['sigma']['median']),
    'sigmalo' => $fmtval($dra['sigma']['lo']),
    'sigmahi' => $fmtval($dra['sigma']['hi']),
    'ppcdetail' => $drppcdetail,
    'explained' => $drexplained,
    'chart' => $doseresponsechart,
    'curvedata' => $drcurvedata,
    'hascurvedata' => !empty($drcurvedata),
    'help' => $OUTPUT->help_icon('doseresponse', 'local_tla'),
];

$assessmentdesign = (new assessment_design_presenter())->export($data['assessmentdesign'], $url, $designpage);

$templatedata = [
    'assessmentdesign' => $assessmentdesign,
    'assessmentdesignurl' => (new moodle_url('/local/tla/assessment_design.php', ['id' => $course->id]))->out(false),
    'coursefullname' => format_string($course->fullname),
    'periodoptions' => $periodoptions,
    'periodstart' => userdate($timestart, get_string('strftimedatetime', 'langconfig')),
    'periodend' => userdate($timeend - 1, get_string('strftimedatetime', 'langconfig')),
    'totalevents' => array_sum(array_column($data['eventsperday'], 'eventcount')),
    'peakactiveusers' => empty($data['activeusersperday']) ? 0 : max(array_column($data['activeusersperday'], 'activeusers')),
    'moduleeventtotal' => array_sum(array_column($data['moduleevents'], 'eventcount')),
    'submitted' => $data['assignmentsubmissions']['submitted'],
    'ontime' => $data['assignmentsubmissions']['ontime'],
    'late' => $data['assignmentsubmissions']['late'],
    'noduedate' => $data['assignmentsubmissions']['noduedate'],
    'extended' => $data['assignmentsubmissions']['extended'],
    'useroverride' => $data['assignmentsubmissions']['useroverride'],
    'groupoverride' => $data['assignmentsubmissions']['groupoverride'],
    'unresolved' => $data['assignmentsubmissions']['unresolved'],
    'trafficlightlabel' => $trafficlightlabel,
    'trafficlightclass' => $trafficlightclass,
    'trafficlightpercentage' => $trafficlightpercentage,
    'hastrafficlightpercentage' => $trafficlightpercentage !== null,
    'trafficlightnumerator' => $trafficlight['numerator'],
    'trafficlightdenominator' => $trafficlight['denominator'],
    'minobservations' => $minobservations,
    'eventsperday' => $eventsperday,
    'haseventsperday' => !empty($eventsperday),
    'eventchart' => empty($eventsperday) ? '' : $OUTPUT->render($eventchart),
    'activeusersperday' => $activeusersperday,
    'hasactiveusersperday' => !empty($activeusersperday),
    'activeuserchart' => empty($activeusersperday) ? '' : $OUTPUT->render($activeuserchart),
    'moduleevents' => $moduleevents,
    'hasmoduleevents' => !empty($moduleevents),
    'scoredistributions' => $scoredistributions,
    'hasscoredistributions' => !empty($scoredistributions),
    'scoredistributionsshowncount' => count($scoredistributions),
    'scoredistributionstotal' => $scoredistributionstotal,
    'scoredistributionstruncated' => $scoredistributionstotal > count($scoredistributions),
    'quizprogress' => $quizprogress,
    'hasquizprogress' => !empty($quizprogress),
    'quizprogresstruncated' => $quizprogresstotal > count($quizprogress),
    'quizprogressshowncount' => count($quizprogress),
    'quizprogresstotal' => $quizprogresstotal,
    'courseprogress' => $courseprogress,
    'hascourseprogress' => $courseprogress['analyzed'],
    'doseresponse' => $doseresponse,
    'hasdoseresponse' => $doseresponse['analyzed'],
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_tla/course_dashboard', $templatedata);
echo $OUTPUT->footer();
