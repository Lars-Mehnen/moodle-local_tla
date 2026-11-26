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
 * Origin analysis of a two-group (U-shaped) course result.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tla\indicator;

use local_tla\statistics\descriptive;

/**
 * Describe WHEN a two-group split of course results appears.
 *
 * Database-free and side-effect-free. It receives the chronologically ordered
 * graded activities of one course, per-participant normalised grades and an
 * optional per-participant daily event count, and checks four transparent
 * patterns that can each contribute to a U-shaped course result:
 *
 * 1. fromstart  - the later upper and lower group are already separated in the
 *                 first graded activities (prior knowledge / interest).
 * 2. divergence - the groups are close at first and separate from one
 *                 activity on (students lost at a certain point; possibly a
 *                 dependency on earlier material).
 * 3. motivation - after the pass threshold is reached arithmetically, submission
 *                 rate and/or course activity drop compared with participants
 *                 who still need points at the same moment.
 * 4. givingup   - the same, after passing has become arithmetically impossible.
 *                 Reported descriptively only (status "descriptive"): in a placebo
 *                 check with permuted activity order it fired equally often.
 *
 * Thresholds were calibrated on 1490 real courses against such a placebo
 * (permuted order): divergence 25 % vs 16 % at a jump of 0.15, hence 0.30
 * (12 % vs 6 %); activity drop 13 % vs 9 % at -30 %, hence -50 % (6 % vs 3 %).
 * The submission-rate rule (-15 points) fired in 6 % vs 2 %.
 *
 * The patterns are not mutually exclusive and are reported as "observed",
 * "notobserved", "unknown" or - for patterns 1 and 2 when the course result
 * shows no pronounced two-group split - "notapplicable". They describe co-occurrence in the data, never a
 * proven cause. Separation is measured with a rank statistic (AUC) per activity
 * so that differing activity difficulty does not by itself create a pattern.
 */
final class ushape_origin_analyzer {
    /** @var int Minimum number of due graded activities. */
    public const MIN_ITEMS = 3;
    /** @var int Minimum absolute size of each final group. */
    public const MIN_GROUP = 3;
    /** @var float Minimum relative size of each final group. */
    public const MIN_GROUP_SHARE = 0.10;
    /** @var float Explained variance of the two-group split from which two groups are reported. */
    public const TWO_GROUPS_ETA2 = 0.80;
    /** @var float Minimum distance of the group medians in percentage points. */
    public const TWO_GROUPS_MIN_GAP = 25.0;
    /** @var int Minimum graded submissions per group and activity for an AUC. */
    public const MIN_AUC_GROUP = 3;

    /** @var float Share of the due activities that counts as "early". */
    public const EARLY_SHARE = 0.25;
    /** @var int Maximum number of early activities. */
    public const EARLY_MAX_ITEMS = 3;
    /** @var float Early separation (AUC) from which "fromstart" is observed. */
    public const FROMSTART_AUC = 0.80;

    /** @var int Minimum activities with an AUC for the divergence search. */
    public const MIN_AUC_ITEMS = 4;
    /** @var float Minimum AUC increase at the divergence point. */
    public const DIVERGENCE_JUMP = 0.30;
    /** @var float The AUC before the divergence point must be below this. */
    public const DIVERGENCE_BEFORE_MAX = 0.80;
    /** @var float The AUC after the divergence point must reach this. */
    public const DIVERGENCE_AFTER_MIN = 0.75;
    /** @var int Minimum "below median" participants for a recovery rate. */
    public const MIN_RECOVERY_BASE = 3;

    /** @var int Minimum participants with the event (pass/fail) and a comparison. */
    public const EVENT_MIN_STUDENTS = 5;
    /** @var float Submission-rate change (difference-in-differences) that counts. */
    public const EVENT_SUBMISSION_DROP = -0.15;
    /** @var float Relative activity change in percent that counts. */
    public const EVENT_ACTIVITY_DROP = -50.0;
    /** @var float Comparison participants must have a submission rate before the cut within this distance. */
    public const EVENT_MATCH_TOLERANCE = 0.10;
    /** @var int Days before the first due activity included in activity windows. */
    public const ACTIVITY_LEAD_DAYS = 28;

    /**
     * Analyse the origin of a two-group course result.
     *
     * @param array $items Graded activities: list of [itemid:int, ordertime:int,
     *     maxpoints:float, name:string, section:string, hasdeadline:bool].
     * @param array $grades Normalised grades: [userid => [itemid => percentage]].
     * @param array $activity Daily event counts: [userid => [daystart => count]].
     * @param float $passshare Pass threshold as share of all points, in (0, 1].
     * @param int $now Reference time; activities with a later order time are pending.
     * @param int $minobservations Minimum participants for any classification.
     * @return array Result structure, see the class documentation and empty_result().
     * @throws \invalid_parameter_exception If input is invalid.
     */
    public function analyze(
        array $items,
        array $grades,
        array $activity,
        float $passshare,
        int $now,
        int $minobservations
    ): array {
        $this->validate($items, $grades, $passshare, $minobservations);

        usort($items, static fn(array $a, array $b): int =>
            [(int) $a['ordertime'], (int) $a['itemid']] <=> [(int) $b['ordertime'], (int) $b['itemid']]);

        // An activity is "due" when its order time has passed AND at least one.
        // Grade exists; due but completely ungraded activities are treated like.
        // Pending ones (not yet marked), never as "nobody submitted".
        $graded = [];
        foreach ($grades as $usergrades) {
            foreach (array_keys($usergrades) as $itemid) {
                $graded[(int) $itemid] = true;
            }
        }
        $due = [];
        $totalmax = 0.0;
        $nodeadline = 0;
        $ungraded = 0;
        foreach ($items as $item) {
            $totalmax += (float) $item['maxpoints'];
            if ((int) $item['ordertime'] <= $now) {
                if (!isset($graded[(int) $item['itemid']])) {
                    $ungraded++;
                    continue;
                }
                $due[] = $item;
                if (empty($item['hasdeadline'])) {
                    $nodeadline++;
                }
            }
        }
        $pending = count($items) - count($due) - $ungraded;

        // Participants: everyone with at least one grade on a due activity.
        $dueids = array_map(static fn(array $i): int => (int) $i['itemid'], $due);
        $users = [];
        foreach ($grades as $userid => $usergrades) {
            foreach ($dueids as $itemid) {
                if (isset($usergrades[$itemid])) {
                    $users[] = (int) $userid;
                    break;
                }
            }
        }

        $result = self::empty_result(count($users), count($due), $pending, $nodeadline, $passshare);
        $result['ungradeditems'] = $ungraded;

        if (count($due) < self::MIN_ITEMS) {
            $result['reason'] = 'toofewitems';
            return $result;
        }
        if (count($users) < max($minobservations, 2 * self::MIN_GROUP)) {
            $result['reason'] = 'toofewparticipants';
            return $result;
        }

        // Per user: score matrix over due activities (null = no grade).
        $n = count($due);
        $duemax = 0.0;
        foreach ($due as $item) {
            $duemax += (float) $item['maxpoints'];
        }
        $matrix = [];
        $final = [];
        foreach ($users as $userid) {
            $row = [];
            $points = 0.0;
            foreach ($due as $j => $item) {
                $value = $grades[$userid][(int) $item['itemid']] ?? null;
                $row[$j] = $value === null ? null : (float) $value;
                if ($value !== null) {
                    $points += (float) $value / 100.0 * (float) $item['maxpoints'];
                }
            }
            $matrix[$userid] = $row;
            $final[$userid] = $duemax > 0.0 ? 100.0 * $points / $duemax : 0.0;
        }

        $shape = (new score_distribution_analyzer())->analyze(array_values($final), $minobservations);
        $result['finalshape'] = $shape['status'];
        $result['finalushape'] = $shape['status'] === 'ushape';

        // Split into the later upper / lower group (Otsu threshold on final results).
        $threshold = self::otsu_threshold(array_values($final));
        $upper = [];
        $lower = [];
        if ($threshold !== null) {
            foreach ($final as $userid => $value) {
                if ($value > $threshold) {
                    $upper[] = $userid;
                } else {
                    $lower[] = $userid;
                }
            }
        }
        $mingroup = max(self::MIN_GROUP, (int) ceil(self::MIN_GROUP_SHARE * count($users)));
        $groupsok = count($upper) >= $mingroup && count($lower) >= $mingroup;
        $eta2 = $threshold === null ? null : self::eta_squared(array_values($final), $threshold);
        $result['separation'] = $eta2;
        $gap = $groupsok
            ? (float) descriptive::median(array_map(static fn($u) => $final[$u], $upper))
                - (float) descriptive::median(array_map(static fn($u) => $final[$u], $lower))
            : 0.0;
        $result['twogroups'] = $groupsok && $eta2 !== null && $eta2 >= self::TWO_GROUPS_ETA2
            && $gap >= self::TWO_GROUPS_MIN_GAP;

        $result['status'] = 'analysed';
        $result['severity'] = ($result['finalushape'] || $result['twogroups']) ? 'yellow' : 'info';
        $result['threshold'] = $threshold;
        $result['uppercount'] = count($upper);
        $result['lowercount'] = count($lower);
        $result['uppermedian'] = $groupsok
            ? (float) descriptive::median(array_map(static fn($u) => $final[$u], $upper)) : null;
        $result['lowermedian'] = $groupsok
            ? (float) descriptive::median(array_map(static fn($u) => $final[$u], $lower)) : null;

        // Pass / fail moments per user (index of the due activity after which.
        // The state is reached; null when never reached within the due activities).
        $needed = $passshare * $totalmax;
        $passindex = [];
        $failindex = [];
        foreach ($users as $userid) {
            $cum = 0.0;
            $cummax = 0.0;
            $passindex[$userid] = null;
            $failindex[$userid] = null;
            foreach ($due as $j => $item) {
                $max = (float) $item['maxpoints'];
                $cummax += $max;
                if ($matrix[$userid][$j] !== null) {
                    $cum += $matrix[$userid][$j] / 100.0 * $max;
                }
                if ($passindex[$userid] === null && $failindex[$userid] === null) {
                    if ($cum >= $needed - 1e-9) {
                        $passindex[$userid] = $j;
                    } else if ($cum + ($totalmax - $cummax) < $needed - 1e-9) {
                        $failindex[$userid] = $j;
                    }
                }
            }
        }

        // Per-activity rows.
        $aucs = [];
        $rows = [];
        foreach ($due as $j => $item) {
            $uscores = self::column($matrix, $upper, $j);
            $lscores = self::column($matrix, $lower, $j);
            $auc = $groupsok ? self::leave_one_out_auc($matrix, $users, $due, $j, $mingroup) : null;
            if ($auc !== null) {
                $aucs[$j] = $auc;
            }
            $passedbefore = 0;
            $failedbefore = 0;
            foreach ($users as $userid) {
                if ($passindex[$userid] !== null && $passindex[$userid] < $j) {
                    $passedbefore++;
                }
                if ($failindex[$userid] !== null && $failindex[$userid] < $j) {
                    $failedbefore++;
                }
            }
            $rows[] = [
                'itemid' => (int) $item['itemid'],
                'name' => (string) $item['name'],
                'section' => (string) ($item['section'] ?? ''),
                'ordertime' => (int) $item['ordertime'],
                'hasdeadline' => !empty($item['hasdeadline']),
                'submitted' => count(self::column($matrix, $users, $j)),
                'uppermedian' => $groupsok && $uscores ? (float) descriptive::median($uscores) : null,
                'lowermedian' => $groupsok && $lscores ? (float) descriptive::median($lscores) : null,
                'uppersubmission' => $groupsok ? count($uscores) / count($upper) : null,
                'lowersubmission' => $groupsok ? count($lscores) / count($lower) : null,
                'auc' => $auc,
                'passedshare' => $passedbefore / count($users),
                'failedshare' => $failedbefore / count($users),
                'divergence' => false,
            ];
        }

        if ($groupsok) {
            $result['fromstart'] = self::fromstart($aucs, $n);
            $result['divergence'] = self::divergence($aucs, $matrix, $users, $n);
            // Without a pronounced split the timing of a (small) separation is.
            // Not interpretable: keep the figures, but do not report a pattern.
            if (!$result['twogroups'] && !$result['finalushape']) {
                $result['fromstart']['status'] = 'notapplicable';
                $result['divergence']['status'] = 'notapplicable';
                $result['divergence']['itemindex'] = null;
            }
            if ($result['divergence']['itemindex'] !== null) {
                $rows[$result['divergence']['itemindex']]['divergence'] = true;
                $item = $rows[$result['divergence']['itemindex']];
                $result['divergence']['name'] = $item['name'];
                $result['divergence']['section'] = $item['section'];
                $result['divergence']['ordertime'] = $item['ordertime'];
            }
        } else {
            $result['reason'] = 'nogroups';
        }

        $windows = self::activity_windows($due, $activity);
        $result['motivation'] = self::event_effect($passindex, $failindex, $matrix, $due, $windows);
        $result['givingup'] = self::event_effect($failindex, $passindex, $matrix, $due, $windows);
        // Calibration on 1490 real courses: with randomly permuted activity order.
        // The giving-up rule fired as often as with the real order (the fail.
        // Moment is itself triggered by missing submissions). Report figures only.
        if ($result['givingup']['status'] !== 'unknown') {
            $result['givingup']['status'] = 'descriptive';
        }
        $result['hasactivity'] = !empty($activity);
        $result['items'] = $rows;

        return $result;
    }

    /**
     * Result skeleton (also the "not enough data" answer).
     *
     * @param int $participants Participants.
     * @param int $dueitems Due activities.
     * @param int $pending Pending activities.
     * @param int $nodeadline Due activities ordered without a deadline.
     * @param float $passshare Pass share.
     * @return array
     */
    private static function empty_result(
        int $participants,
        int $dueitems,
        int $pending,
        int $nodeadline,
        float $passshare
    ): array {
        $event = self::empty_event();
        return [
            'status' => 'unknown',
            'severity' => 'unknown',
            'reason' => null,
            'participants' => $participants,
            'dueitems' => $dueitems,
            'pendingitems' => $pending,
            'nodeadlineitems' => $nodeadline,
            'ungradeditems' => 0,
            'passshare' => $passshare,
            'finalshape' => 'unknown',
            'finalushape' => false,
            'twogroups' => false,
            'separation' => null,
            'threshold' => null,
            'uppercount' => 0,
            'lowercount' => 0,
            'uppermedian' => null,
            'lowermedian' => null,
            'fromstart' => ['status' => 'unknown', 'earlyauc' => null, 'lateauc' => null, 'earlyitems' => 0],
            'divergence' => [
                'status' => 'unknown', 'itemindex' => null, 'name' => null, 'section' => null,
                'ordertime' => null, 'beforeauc' => null, 'afterauc' => null, 'jump' => null,
                'recoveryearly' => null, 'recoverylate' => null,
            ],
            'motivation' => $event,
            'givingup' => $event,
            'hasactivity' => false,
            'items' => [],
        ];
    }

    /**
     * Empty pass/fail event result.
     *
     * @return array
     */
    private static function empty_event(): array {
        return [
            'status' => 'unknown',
            'students' => 0,
            'eventsharetotal' => null,
            'submissionbefore' => null,
            'submissionafter' => null,
            'controlsubmissionbefore' => null,
            'controlsubmissionafter' => null,
            'submissiondid' => null,
            'scoredid' => null,
            'activitychange' => null,
            'controlactivitychange' => null,
            'activitydid' => null,
        ];
    }

    /**
     * Pattern 1: separation already in the first activities.
     *
     * @param array $aucs AUC by due-activity index.
     * @param int $n Number of due activities.
     * @return array
     */
    private static function fromstart(array $aucs, int $n): array {
        $earlycount = min(self::EARLY_MAX_ITEMS, max(1, (int) ceil(self::EARLY_SHARE * $n)));
        $early = [];
        $late = [];
        foreach ($aucs as $j => $auc) {
            if ($j < $earlycount) {
                $early[] = $auc;
            } else {
                $late[] = $auc;
            }
        }
        if (!$early) {
            return ['status' => 'unknown', 'earlyauc' => null, 'lateauc' => null, 'earlyitems' => 0];
        }
        $earlyauc = (float) descriptive::mean($early);
        return [
            'status' => $earlyauc >= self::FROMSTART_AUC ? 'observed' : 'notobserved',
            'earlyauc' => $earlyauc,
            'lateauc' => $late ? (float) descriptive::mean($late) : null,
            'earlyitems' => count($early),
        ];
    }

    /**
     * Pattern 2: single change point in the separation curve, plus recovery rates.
     *
     * @param array $aucs AUC by due-activity index.
     * @param array $matrix Score matrix.
     * @param array $users Participants.
     * @param int $n Number of due activities.
     * @return array
     */
    private static function divergence(array $aucs, array $matrix, array $users, int $n): array {
        $out = self::empty_result(0, 0, 0, 0, 1.0)['divergence'];
        [$out['recoveryearly'], $out['recoverylate']] = self::recovery($matrix, $users, $n);

        $indices = array_keys($aucs);
        $values = array_values($aucs);
        $m = count($values);
        if ($m < self::MIN_AUC_ITEMS) {
            return $out;
        }
        $best = null;
        for ($k = 1; $k <= $m - 2; $k++) {
            $before = (float) descriptive::mean(array_slice($values, 0, $k));
            $after = (float) descriptive::mean(array_slice($values, $k));
            $jump = $after - $before;
            if ($best === null || $jump > $best['jump']) {
                $best = ['k' => $k, 'before' => $before, 'after' => $after, 'jump' => $jump];
            }
        }
        $out['beforeauc'] = $best['before'];
        $out['afterauc'] = $best['after'];
        $out['jump'] = $best['jump'];
        $observed = $best['jump'] >= self::DIVERGENCE_JUMP
            && $best['before'] < self::DIVERGENCE_BEFORE_MAX
            && $best['after'] >= self::DIVERGENCE_AFTER_MIN;
        $out['status'] = $observed ? 'observed' : 'notobserved';
        if ($observed) {
            $out['itemindex'] = $indices[$best['k']];
        }
        return $out;
    }

    /**
     * Share of below-median participants that reach the median in the next activity.
     *
     * @param array $matrix Score matrix.
     * @param array $users Participants.
     * @param int $n Number of due activities.
     * @return array{0: float|null, 1: float|null} Early-half and late-half rate.
     */
    private static function recovery(array $matrix, array $users, int $n): array {
        $rates = [];
        for ($j = 0; $j < $n - 1; $j++) {
            $both = [];
            foreach ($users as $userid) {
                if ($matrix[$userid][$j] !== null && $matrix[$userid][$j + 1] !== null) {
                    $both[] = $userid;
                }
            }
            if (count($both) < 2 * self::MIN_RECOVERY_BASE) {
                continue;
            }
            $med0 = descriptive::median(array_map(static fn($u) => $matrix[$u][$j], $both));
            $med1 = descriptive::median(array_map(static fn($u) => $matrix[$u][$j + 1], $both));
            $below = 0;
            $recovered = 0;
            foreach ($both as $userid) {
                if ($matrix[$userid][$j] < $med0) {
                    $below++;
                    if ($matrix[$userid][$j + 1] >= $med1) {
                        $recovered++;
                    }
                }
            }
            if ($below >= self::MIN_RECOVERY_BASE) {
                $rates[$j] = $recovered / $below;
            }
        }
        $early = [];
        $late = [];
        foreach ($rates as $j => $rate) {
            if ($j < ($n - 1) / 2) {
                $early[] = $rate;
            } else {
                $late[] = $rate;
            }
        }
        return [
            $early ? (float) descriptive::mean($early) : null,
            $late ? (float) descriptive::mean($late) : null,
        ];
    }

    /**
     * Patterns 3/4: behaviour after a pass (or fail) moment versus open participants.
     *
     * For every participant whose event happens at due activity p (with at least
     * one due activity after it), the change between activities 0..p and p+1..n-1
     * is compared with the same change of participants for whom the outcome was
     * still open at p and whose submission rate up to p was similar
     * (difference-in-differences with matching). The median is reported.
     *
     * @param array $eventindex Event index per user (the analysed event).
     * @param array $otherindex Index of the opposite event per user.
     * @param array $matrix Score matrix.
     * @param array $due Due activities.
     * @param array $windows Activity rates per user and cut, see activity_windows().
     * @return array
     */
    private static function event_effect(
        array $eventindex,
        array $otherindex,
        array $matrix,
        array $due,
        array $windows
    ): array {
        $out = self::empty_event();
        $n = count($due);
        $users = array_keys($matrix);

        $subdid = [];
        $scoredid = [];
        $actdid = [];
        $actown = [];
        $actctl = [];
        $subb = [];
        $suba = [];
        $csubb = [];
        $csuba = [];
        $eventusers = 0;
        foreach ($users as $userid) {
            if ($eventindex[$userid] !== null) {
                $eventusers++;
            }
        }
        $out['eventsharetotal'] = $users ? $eventusers / count($users) : null;

        foreach ($users as $userid) {
            $p = $eventindex[$userid];
            if ($p === null || $p >= $n - 1) {
                continue;
            }
            // Matched comparison: still open at p AND a similar submission rate.
            // Before p. Without matching, early passers (who by construction.
            // Submitted almost everything) could only lose and open students.
            // Only gain (regression to the mean).
            [$sb, $sa, $scb, $sca] = self::split_stats($matrix[$userid], $p, $n);
            $controls = [];
            foreach ($users as $other) {
                $open = ($eventindex[$other] === null || $eventindex[$other] > $p)
                    && ($otherindex[$other] === null || $otherindex[$other] > $p);
                if ($open && abs(self::split_stats($matrix[$other], $p, $n)[0] - $sb) <= self::EVENT_MATCH_TOLERANCE + 1e-9) {
                    $controls[] = $other;
                }
            }
            if (!$controls) {
                continue;
            }
            $ctl = [];
            $ctlscore = [];
            $ctlsb = [];
            $ctlsa = [];
            foreach ($controls as $other) {
                [$ob, $oa, $ocb, $oca] = self::split_stats($matrix[$other], $p, $n);
                $ctl[] = $oa - $ob;
                $ctlsb[] = $ob;
                $ctlsa[] = $oa;
                if ($ocb !== null && $oca !== null) {
                    $ctlscore[] = $oca - $ocb;
                }
            }
            $subdid[] = ($sa - $sb) - (float) descriptive::mean($ctl);
            $subb[] = $sb;
            $suba[] = $sa;
            $csubb[] = (float) descriptive::mean($ctlsb);
            $csuba[] = (float) descriptive::mean($ctlsa);
            if ($scb !== null && $sca !== null && $ctlscore) {
                $scoredid[] = ($sca - $scb) - (float) descriptive::mean($ctlscore);
            }
            if (isset($windows[$p][$userid])) {
                $own = $windows[$p][$userid];
                $ctlact = [];
                foreach ($controls as $other) {
                    if (isset($windows[$p][$other])) {
                        $ctlact[] = $windows[$p][$other];
                    }
                }
                if ($ctlact) {
                    $ctlmean = (float) descriptive::mean($ctlact);
                    $actown[] = $own;
                    $actctl[] = $ctlmean;
                    $actdid[] = $own - $ctlmean;
                }
            }
        }

        $out['students'] = count($subdid);
        if (count($subdid) < self::EVENT_MIN_STUDENTS) {
            return $out;
        }
        $out['submissionbefore'] = (float) descriptive::mean($subb);
        $out['submissionafter'] = (float) descriptive::mean($suba);
        $out['controlsubmissionbefore'] = (float) descriptive::mean($csubb);
        $out['controlsubmissionafter'] = (float) descriptive::mean($csuba);
        $out['submissiondid'] = (float) descriptive::median($subdid);
        $out['scoredid'] = count($scoredid) >= self::EVENT_MIN_STUDENTS
            ? (float) descriptive::median($scoredid) : null;
        if (count($actdid) >= self::EVENT_MIN_STUDENTS) {
            $out['activitychange'] = (exp((float) descriptive::median($actown)) - 1.0) * 100.0;
            $out['controlactivitychange'] = (exp((float) descriptive::median($actctl)) - 1.0) * 100.0;
            $out['activitydid'] = (exp((float) descriptive::median($actdid)) - 1.0) * 100.0;
        }
        $observed = $out['submissiondid'] <= self::EVENT_SUBMISSION_DROP
            || ($out['activitydid'] !== null && $out['activitydid'] <= self::EVENT_ACTIVITY_DROP);
        $out['status'] = $observed ? 'observed' : 'notobserved';
        return $out;
    }

    /**
     * Submission rate and mean score before (0..p) and after (p+1..n-1).
     *
     * @param array $row Scores of one user (null = not graded).
     * @param int $p Cut index.
     * @param int $n Number of due activities.
     * @return array{0: float, 1: float, 2: float|null, 3: float|null}
     */
    private static function split_stats(array $row, int $p, int $n): array {
        $before = array_slice($row, 0, $p + 1);
        $after = array_slice($row, $p + 1, $n - $p - 1);
        $gb = array_values(array_filter($before, static fn($v) => $v !== null));
        $ga = array_values(array_filter($after, static fn($v) => $v !== null));
        return [
            count($gb) / count($before),
            count($ga) / count($after),
            $gb ? (float) descriptive::mean($gb) : null,
            $ga ? (float) descriptive::mean($ga) : null,
        ];
    }

    /**
     * Log activity ratio (after / before each possible cut) per user.
     *
     * Before = [first due time - lead days, cut deadline), after = [cut deadline,
     * last due time). Rates are events per day; ln((after + 0.1) / (before + 0.1)).
     *
     * @param array $due Due activities.
     * @param array $activity [userid => [daystart => count]].
     * @return array [cut index => [userid => log ratio]].
     */
    private static function activity_windows(array $due, array $activity): array {
        if (!$activity) {
            return [];
        }
        $n = count($due);
        $start = (int) $due[0]['ordertime'] - self::ACTIVITY_LEAD_DAYS * 86400;
        $end = (int) $due[$n - 1]['ordertime'];
        $windows = [];
        for ($p = 0; $p < $n - 1; $p++) {
            $cut = (int) $due[$p]['ordertime'];
            $daysbefore = max(1.0, ($cut - $start) / 86400);
            $daysafter = max(1.0, ($end - $cut) / 86400);
            foreach ($activity as $userid => $days) {
                $before = 0;
                $after = 0;
                foreach ($days as $daystart => $count) {
                    if ($daystart >= $start && $daystart < $cut) {
                        $before += (int) $count;
                    } else if ($daystart >= $cut && $daystart < $end) {
                        $after += (int) $count;
                    }
                }
                $windows[$p][(int) $userid] = log(($after / $daysafter + 0.1) / ($before / $daysbefore + 0.1));
            }
        }
        return $windows;
    }

    /**
     * Separation at activity j with groups formed WITHOUT activity j.
     *
     * Otherwise an activity would partly define the groups it is compared by,
     * which inflates the separation when there are only few activities.
     *
     * @param array $matrix Score matrix.
     * @param array $users Participants.
     * @param array $due Due activities.
     * @param int $j Activity index.
     * @param int $mingroup Minimum group size.
     * @return float|null AUC, or null if the groups or graded values are too small.
     */
    private static function leave_one_out_auc(array $matrix, array $users, array $due, int $j, int $mingroup): ?float {
        $max = 0.0;
        foreach ($due as $k => $item) {
            if ($k !== $j) {
                $max += (float) $item['maxpoints'];
            }
        }
        if ($max <= 0.0) {
            return null;
        }
        $totals = [];
        foreach ($users as $userid) {
            $points = 0.0;
            foreach ($due as $k => $item) {
                if ($k !== $j && $matrix[$userid][$k] !== null) {
                    $points += $matrix[$userid][$k] / 100.0 * (float) $item['maxpoints'];
                }
            }
            $totals[$userid] = 100.0 * $points / $max;
        }
        $threshold = self::otsu_threshold(array_values($totals));
        if ($threshold === null) {
            return null;
        }
        $upper = [];
        $lower = [];
        foreach ($totals as $userid => $value) {
            if ($value > $threshold) {
                $upper[] = $userid;
            } else {
                $lower[] = $userid;
            }
        }
        if (count($upper) < $mingroup || count($lower) < $mingroup) {
            return null;
        }
        $uscores = self::column($matrix, $upper, $j);
        $lscores = self::column($matrix, $lower, $j);
        if (count($uscores) < self::MIN_AUC_GROUP || count($lscores) < self::MIN_AUC_GROUP) {
            return null;
        }
        return self::auc($uscores, $lscores);
    }

    /**
     * Graded values of the given users at one activity.
     *
     * @param array $matrix Score matrix.
     * @param array $users User ids.
     * @param int $j Activity index.
     * @return float[]
     */
    private static function column(array $matrix, array $users, int $j): array {
        $values = [];
        foreach ($users as $userid) {
            if ($matrix[$userid][$j] !== null) {
                $values[] = $matrix[$userid][$j];
            }
        }
        return $values;
    }

    /**
     * Probability that a random upper value exceeds a random lower value (ties 1/2).
     *
     * @param float[] $upper Upper-group values.
     * @param float[] $lower Lower-group values.
     * @return float AUC in [0, 1].
     */
    public static function auc(array $upper, array $lower): float {
        $wins = 0.0;
        foreach ($upper as $u) {
            foreach ($lower as $l) {
                if ($u > $l) {
                    $wins += 1.0;
                } else if ($u == $l) {
                    $wins += 0.5;
                }
            }
        }
        return $wins / (count($upper) * count($lower));
    }

    /**
     * Otsu threshold: split maximising the between-group variance.
     *
     * @param float[] $values Values.
     * @return float|null Threshold (values above belong to the upper group), or
     *     null when all values are equal.
     */
    public static function otsu_threshold(array $values): ?float {
        sort($values);
        $count = count($values);
        if ($count < 2 || $values[0] == $values[$count - 1]) {
            return null;
        }
        $total = array_sum($values);
        $sum0 = 0.0;
        $best = -1.0;
        $threshold = null;
        for ($i = 0; $i < $count - 1; $i++) {
            $sum0 += $values[$i];
            if ($values[$i] == $values[$i + 1]) {
                continue;
            }
            $w0 = $i + 1;
            $w1 = $count - $w0;
            $m0 = $sum0 / $w0;
            $m1 = ($total - $sum0) / $w1;
            $between = $w0 * $w1 * ($m0 - $m1) ** 2;
            if ($between > $best) {
                $best = $between;
                $threshold = ($values[$i] + $values[$i + 1]) / 2.0;
            }
        }
        return $threshold;
    }

    /**
     * Share of the total variance explained by splitting at the threshold.
     *
     * About 0.64 for a normal and 0.75 for a uniform distribution; clearly
     * separated groups give values close to 1.
     *
     * @param float[] $values Values.
     * @param float $threshold Split threshold.
     * @return float|null Eta squared, or null without variance.
     */
    public static function eta_squared(array $values, float $threshold): ?float {
        $mean = array_sum($values) / count($values);
        $total = 0.0;
        foreach ($values as $v) {
            $total += ($v - $mean) ** 2;
        }
        if ($total <= 0.0) {
            return null;
        }
        $groups = [[], []];
        foreach ($values as $v) {
            $groups[$v > $threshold ? 1 : 0][] = $v;
        }
        $between = 0.0;
        foreach ($groups as $group) {
            if ($group) {
                $between += count($group) * (array_sum($group) / count($group) - $mean) ** 2;
            }
        }
        return $between / $total;
    }

    /**
     * Validate input.
     *
     * @param array $items Activities.
     * @param array $grades Grades.
     * @param float $passshare Pass share.
     * @param int $minobservations Minimum observations.
     * @throws \invalid_parameter_exception If input is invalid.
     */
    private function validate(array $items, array $grades, float $passshare, int $minobservations): void {
        if ($minobservations < 1) {
            throw new \invalid_parameter_exception('Minimum observations must be positive.');
        }
        if (!is_finite($passshare) || $passshare <= 0.0 || $passshare > 1.0) {
            throw new \invalid_parameter_exception('Pass share must be within (0, 1].');
        }
        foreach ($items as $item) {
            if (!isset($item['itemid'], $item['ordertime'], $item['maxpoints'])) {
                throw new \invalid_parameter_exception('Activities need itemid, ordertime and maxpoints.');
            }
            if (!is_finite((float) $item['maxpoints']) || (float) $item['maxpoints'] <= 0.0) {
                throw new \invalid_parameter_exception('Activity points must be positive.');
            }
        }
        foreach ($grades as $usergrades) {
            foreach ($usergrades as $value) {
                if (!is_numeric($value) || !is_finite((float) $value) || $value < 0 || $value > 100) {
                    throw new \invalid_parameter_exception('Grades must be percentages within [0, 100].');
                }
            }
        }
    }
}
