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
 * Language string coverage and parity tests for local_tla.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla;


/**
 * Tests for lang_strings_test.
 */
final class lang_strings_test extends \advanced_testcase {
    /**
     * Every string used by the dashboard must exist for local_tla.
     */
    public function test_dashboard_strings_exist(): void {
        $sm = get_string_manager();
        $keys = [
            // General dashboard.
            'dashboardtitle', 'totalevents', 'peakactiveusers', 'moduleevents',
            'submittedassignments', 'assignmentsummary', 'ontime', 'late', 'noduedate',
            'eventsperday', 'events', 'activeusersperday', 'activeusers', 'moduleusage',
            'component', 'nodata', 'periodselection', 'perioddays',
            // Deadline traffic light + effective deadline.
            'lateindicator', 'laterate', 'norateavailable', 'trafficlightminimum',
            'trafficlight_green', 'trafficlight_yellow', 'trafficlight_red', 'trafficlight_unknown',
            'effectivedeadlinesconsidered', 'extensionsapplied', 'useroverrides',
            'groupoverrides', 'unresolveddeadlines',
            // Score distributions.
            'scoredistributions', 'scorevalidgrades', 'scoremedian', 'scorequartiles',
            'scorelow', 'scoremiddle', 'scorehigh', 'scoredistributionstruncated',
            'scoreinvalidgrades',
            'scorestatus_middle', 'scorestatus_ceiling', 'scorestatus_floor',
            'scorestatus_ushape', 'scorestatus_mixed', 'scorestatus_unknown',
            'scoreexpl_middle', 'scoreexpl_ceiling', 'scoreexpl_floor',
            'scoreexpl_ushape', 'scoreexpl_mixed', 'scoreexpl_unknown',
            // Learning progress.
            'learningprogress', 'courseprogresstitle', 'progresstruncated',
            'progressactivities', 'progressparticipants', 'progressmeanfirst',
            'progressmeanlast', 'progressmeanbest', 'progressmedianchange',
            'progressimproved', 'progressstable', 'progressdeclined', 'progressmeanattempts',
            'progressstatus_improving', 'progressstatus_stable', 'progressstatus_declining',
            'progressstatus_mixed', 'progressstatus_unknown',
            'progressexpl_improving', 'progressexpl_stable', 'progressexpl_declining',
            'progressexpl_mixed', 'progressexpl_unknown',
            'courseprogressexpl', 'courseprogresswarning',
            // Learning dose-response (Bayesian Emax).
            'doseresponse', 'doseresponse_help', 'doseresponseintro', 'doseresponseobservations',
            'doseresponsequizzes', 'doseresponseemax', 'doseresponseec50',
            'doseresponsesigma', 'doseresponsecredible', 'doseresponseppc',
            'doseresponseppcdetail', 'doseresponseaxisx', 'doseresponseaxisy',
            'doseresponsemedianline', 'doseresponselowerband', 'doseresponseupperband',
            'doseresponseobserved', 'doseresponsebandnote', 'doseresponsewarning',
            'doseresponseempty', 'doseresponseexplained',
            'doseresponsedata', 'doseresponseband',
            'doseresponsefit_good', 'doseresponsefit_weak', 'doseresponsefit_flat',
            'doseresponsefit_poor', 'doseresponsefit_unknown',
            'doseresponsefitexpl_good', 'doseresponsefitexpl_weak', 'doseresponsefitexpl_flat',
            'doseresponsefitexpl_poor', 'doseresponsefitexpl_unknown',
            // Empty states.
            'emptynomatchingactivities', 'emptynovalidgrades',
            'emptynotenoughobservations', 'emptynoquizattempts',
            // Privacy.
            'privacy:metadata',
        ];
        foreach ($keys as $key) {
            $this->assertTrue(
                $sm->string_exists($key, 'local_tla'),
                "Missing local_tla string: {$key}"
            );
        }
    }

    /**
     * The English and German string files must define exactly the same keys.
     */
    public function test_en_and_de_are_in_parity(): void {
        $sm = get_string_manager();
        $en = $sm->load_component_strings('local_tla', 'en');
        $de = $sm->load_component_strings('local_tla', 'de');

        foreach (array_keys($en) as $key) {
            $this->assertArrayHasKey($key, $de, "German translation missing for: {$key}");
        }
        foreach (array_keys($de) as $key) {
            $this->assertArrayHasKey($key, $en, "English string missing for: {$key}");
        }
    }
}
