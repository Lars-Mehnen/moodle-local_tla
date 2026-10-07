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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Strings for component 'local_tla', language 'en'.
 *
 * @package    local_tla
 * @copyright  2026 Lars Mehnen <lars.mehnen@technikum-wien.at>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['activeusers'] = 'Active users';
$string['activeusersperday'] = 'Active users per day';
$string['ad_action_assignmentfeedback'] = 'Check when feedback is released and whether a reopened submission remains accessible afterwards. The planned grading date is not proof of actual release.';
$string['ad_action_effective'] = 'Inspect the relevant overrides, extensions and availability conditions before applying a base-setting observation to all students.';
$string['ad_action_feedbackcontent'] = 'Does the enabled review actually contain useful guidance? A permitted display option can be empty and does not establish reading or understanding.';
$string['ad_action_feedbackwindow'] = 'For an exercise, can students review useful feedback before the next permitted attempt? Also consider feedback during questions, access conditions and communication outside Moodle.';
$string['ad_action_gradepenalty'] = 'Review the configured Moodle penalty policy and its published assessment purpose. This is independent of TLA, which does not apply penalties.';
$string['ad_action_passgrade'] = 'Check the intended passing grade and maximum attempts in the activity and gradebook.';
$string['ad_action_purpose'] = 'Is this rule appropriate for an exercise, a final assessment or a mixed format? Decide against the published learning and grading objectives; do not change rules retrospectively merely because a flag appears.';
$string['ad_action_questionrules'] = 'Review the actual question types and their retry/penalty settings separately, including CodeRunner where used. Whole-quiz retries and retries within a question are different levels.';
$string['ad_action_team'] = 'Where individual competence must be assessed, clarify what separate individual evidence is available; the shared submission alone cannot establish it.';
$string['ad_action_timing'] = 'Check the nominal opening/closing dates, retry delays and applicable overrides. No working-speed estimate or student-level access decision has been made.';
$string['ad_activitylink'] = 'Open activity';
$string['ad_back'] = 'Back to the full dashboard';
$string['ad_criterion_feedback'] = 'Feedback and review';
$string['ad_criterion_grading'] = 'Attempts and grading';
$string['ad_criterion_questionrules'] = 'Question-level rules';
$string['ad_criterion_scope'] = 'Scope';
$string['ad_criterion_timing'] = 'Time windows';
$string['ad_currentsettings'] = 'Read configuration';
$string['ad_empty'] = 'No live quiz or assignment activities were found. Other activity types and assessments outside Moodle are not evaluated.';
$string['ad_hidden'] = 'Hidden in module settings';
$string['ad_interpret_ceiling_best'] = 'Grades cluster near the maximum. The current base settings allow multiple attempts and retain the best grade; a ceiling is compatible with that rule. This does not establish why these grades arose, that the same settings applied then, or independent competence.';
$string['ad_interpret_ceiling_contextunknown'] = 'Grades cluster near the maximum. Without a verified activity purpose and the applicable grading/revision context, this is a descriptive observation, not a defect or evidence of shared solutions, AI use or independent mastery.';
$string['ad_interpret_ceiling_last'] = 'Grades cluster near the maximum. Current settings use the last of multiple attempts; successful revision is one possible explanation. Historical settings, actual learning and independent authorship are not established.';
$string['ad_interpret_ceiling_retained'] = 'Grades cluster near the maximum, while the current attempt rule retains earlier results. Review both the grading rule and the activity purpose. The score shape alone is not evidence of copied solutions or AI use.';
$string['ad_interpret_ceiling_unrandomised'] = 'Grades cluster near the maximum on a quiz whose settings provide no retry mechanism (single attempt or unknown context) to explain it. This does not prove shared solutions or AI use, but per-student randomisation would make a copied, unadapted answer fail on the copier\'s own variant.';
$string['ad_interpret_ceiling_untilpass'] = 'Grades cluster near the maximum. The assignment currently allows reopening until passing, not necessarily improvement to full marks. Interpret the distribution against the rubric and actual revision opportunities; it is not misconduct evidence.';
$string['ad_intro'] = 'Current base settings of quizzes and assignments, including activities without submissions. These are criterion findings, not a fairness score or a teaching-quality traffic light.';
$string['ad_limits'] = 'The teaching purpose, effective course-grade weight, external assessments, actual feedback content and independent authorship cannot be established here. Current settings do not reconstruct historical settings. Manual gradebook overrides and individual effective access are not evaluated. A review item is a question for the lecturer, not a finding of unfairness. Settings are never changed by TLA.';
$string['ad_limits_title'] = 'Scope and unverified aspects';
$string['ad_method_1'] = 'Highest attempt';
$string['ad_method_2'] = 'Average of attempts';
$string['ad_method_3'] = 'First attempt';
$string['ad_method_4'] = 'Last attempt';
$string['ad_navigation'] = 'Assessment-design pages';
$string['ad_next'] = 'Next activities';
$string['ad_none'] = 'Not set';
$string['ad_pagination'] = 'Activities {$a->first}–{$a->last} of {$a->total}';
$string['ad_previous'] = 'Previous activities';
$string['ad_recommend_parameterize_quiz'] = 'Consider randomising this quiz per student (CodeRunner "Twig all") so a shared solution does not fit another student\'s variant. A suggested variant space and snippet is provided; verify it fits the task before applying.';
$string['ad_recommend_review_assessment_design'] = 'Clarify the activity purpose and applicable grading/revision rules before treating high scores as a problem. Neither cheating nor the need for individual task variants follows from this distribution.';
$string['ad_recommend_review_difficulty'] = 'Review instructions, prerequisites, marking and missing-work handling against the learning objectives. Low grades alone do not identify their cause.';
$string['ad_recommend_review_first_and_final'] = 'For quizzes, examine the existing first/last/best-attempt aggregates alongside the final grade distribution. They include only participants with repeated completed attempts and do not prove independent learning. Do not randomise tasks merely because final grades are high.';
$string['ad_recommend_review_two_groups'] = 'Review prerequisites, the rubric and possible binary marking effects before interpreting the two clusters. The distribution cannot attribute causes or identify misconduct.';
$string['ad_reopen_automatic'] = 'Automatic';
$string['ad_reopen_manual'] = 'Manual';
$string['ad_reopen_none'] = 'No reopening (legacy value)';
$string['ad_reopen_untilpass'] = 'Automatic until passing';
$string['ad_reviewaction'] = 'Review question';
$string['ad_rule_access'] = 'The module is hidden or has an availability condition. The effective access window and conditions for individual students are not evaluated.';
$string['ad_rule_assign_automatic'] = 'Further assignment attempts are configured to reopen automatically after grading, subject to the attempt limit and access rules. The grading rubric and treatment of earlier errors are not inferred.';
$string['ad_rule_assign_feedback_schedule'] = 'The planned grading date is on or after the submission cut-off, although retries are permitted. Planned dates are not actual feedback dates; check whether revision after feedback is practically possible.';
$string['ad_rule_assign_feedback_unknown'] = 'Assignment settings alone do not establish when meaningful feedback is available. Actual marking/release times and feedback content are not evaluated by this check.';
$string['ad_rule_assign_feedback_workflow'] = 'A marking workflow is enabled. Feedback release depends on workflow state and grading actions; the settings alone do not establish that feedback precedes a retry.';
$string['ad_rule_assign_gradepenalty'] = 'The assignment\'s Moodle grade-penalty feature is enabled. Its detailed penalty configuration is not evaluated. TLA observes this setting; it does not calculate or apply penalty grades.';
$string['ad_rule_assign_manual'] = 'Further assignment attempts require manual reopening. The configured limit permits retries, but it does not establish that all students will actually receive one.';
$string['ad_rule_assign_offline'] = 'This assignment has no enabled submission workflow according to its module setting. An assessment or revision outside the Moodle submission workflow may exist; TLA cannot classify it here.';
$string['ad_rule_assign_pass_missing'] = 'Automatic reopening until passing is selected, but no positive grade-to-pass value was found in the main grade item. The intended passing/reopening rule needs checking.';
$string['ad_rule_assign_single'] = 'The base configuration does not provide a further reopened assignment attempt. This does not rule out editing a draft/current submission or a separate follow-up activity.';
$string['ad_rule_assign_team'] = 'Team submission is enabled. A shared product/grade is not, by itself, evidence of each member\'s individual contribution or competence. This settings check does not extend the existing individual-submission timing analysis to groups.';
$string['ad_rule_assign_untilpass'] = 'Reopening is configured automatically until a passing grade is reached, subject to the attempt limit. Passing is not the same as reaching full marks; further improvement after passing is not guaranteed.';
$string['ad_rule_feedback_after_close'] = 'Standard post-attempt results/feedback are enabled only after the configured quiz close. This does not provide a standard review window between complete attempts. Feedback during questions or outside Moodle is not evaluated.';
$string['ad_rule_feedback_immediate'] = 'Standard post-attempt results/feedback are enabled only immediately after submission, not for later review while open. This standard phase lasts two minutes; content and actual use are unverified.';
$string['ad_rule_feedback_no_close'] = 'Post-attempt results/feedback are enabled only for the after-close phase, but no base closing date is set. That phase is not reached under the base schedule. Overrides may differ.';
$string['ad_rule_feedback_no_postattempt'] = 'No standard post-attempt result or feedback category is enabled. TLA has not checked feedback during questions, question-type-specific feedback or communication outside Moodle.';
$string['ad_rule_feedback_no_retry'] = 'Feedback before another complete attempt is not assessed when the base attempt setting does not establish a repeat opportunity. The value of feedback after a final attempt is not being judged.';
$string['ad_rule_feedback_open'] = 'At least one standard result or feedback category is enabled for review while the quiz remains open. This may be only marks or correctness; neither substantive feedback nor another accessible attempt is guaranteed.';
$string['ad_rule_feedback_unknown'] = 'The standard quiz-review settings are incomplete in the supplied configuration. Feedback timing cannot be inferred.';
$string['ad_rule_grading_unknown'] = 'The available settings do not support a reliable interpretation of this attempt/grading rule.';
$string['ad_rule_overrides'] = 'Overrides or individual extensions/locks exist. Only their presence is checked; effective rules per person/group are not reconstructed in this design check. Base findings must not be generalised to everyone.';
$string['ad_rule_question_rules'] = 'Question-specific penalties, hints, interactive retries and CodeRunner rules are not evaluated. The quiz-level preferred behaviour alone cannot establish whether all corrections are penalty-free.';
$string['ad_rule_quiz_average'] = 'Several complete quiz attempts are permitted; their average counts. Earlier lower results continue to affect the activity grade even after a later successful attempt.';
$string['ad_rule_quiz_best'] = 'Several complete quiz attempts are permitted in the base settings; the highest attempt grade counts. Lower grades in other attempts do not reduce it through this aggregation rule. This says nothing about question-level penalties or manual grade changes.';
$string['ad_rule_quiz_continuation'] = 'New attempts build on the previous attempt. This is a continuation opportunity, not automatically an independent new performance measurement.';
$string['ad_rule_quiz_first'] = 'Several complete quiz attempts are permitted, but only the first counts towards the activity grade. Later improvements do not replace that grade through this rule.';
$string['ad_rule_quiz_last'] = 'Several complete quiz attempts are permitted; the last counts. Earlier lower grades are not averaged in, but a later worse result can replace an earlier better one.';
$string['ad_rule_quiz_retry_window'] = 'The configured opening/closing interval and first retry delay leave no positive window for a second complete attempt under the base rules, even before allowing time to answer questions.';
$string['ad_rule_quiz_single'] = 'The base setting permits one complete quiz attempt. This does not establish whether corrections within questions, overrides or follow-up activities offer other opportunities.';
$string['ad_rule_ungraded'] = 'No activity grade is configured. This observation does not evaluate completion conditions or indirect effects on other activities.';
$string['ad_rule_unsupported'] = 'This activity type is not supported by the configuration check.';
$string['ad_rulecount'] = '{$a} criterion findings';
$string['ad_setting_allowsubmissionsfromdate'] = 'Submissions from';
$string['ad_setting_attemptonlast'] = 'Each attempt builds on the last';
$string['ad_setting_attemptreopenmethod'] = 'Reopening method';
$string['ad_setting_attempts'] = 'Complete quiz attempts';
$string['ad_setting_cutoffdate'] = 'Submission cut-off date';
$string['ad_setting_delay1'] = 'First retry delay (seconds)';
$string['ad_setting_delay2'] = 'Later retry delay (seconds)';
$string['ad_setting_duedate'] = 'Due date (not the final cut-off)';
$string['ad_setting_grademethod'] = 'Quiz grading method';
$string['ad_setting_gradepass'] = 'Grade to pass (main grade item)';
$string['ad_setting_gradepenalty'] = 'Moodle grade penalties enabled';
$string['ad_setting_gradingduedate'] = 'Planned grading date';
$string['ad_setting_markingworkflow'] = 'Marking workflow';
$string['ad_setting_maxattempts'] = 'Maximum assignment attempts';
$string['ad_setting_preferredbehaviour'] = 'Preferred question behaviour (not verified per question)';
$string['ad_setting_submissiondrafts'] = 'Submission button required';
$string['ad_setting_timeclose'] = 'Base closing date';
$string['ad_setting_timeopen'] = 'Base opening date';
$string['ad_snapshot'] = 'Configuration checked at {$a}. Independent of the activity-log date filter.';
$string['ad_standalone'] = 'Open settings check without activity-history analysis';
$string['ad_status_notapplicable'] = 'Not applicable';
$string['ad_status_observed'] = 'Observed';
$string['ad_status_review'] = 'Review';
$string['ad_status_unknown'] = 'Unknown / not evaluated';
$string['ad_title'] = 'Assessment design';
$string['ad_unlimited'] = 'Unlimited';
$string['assignmentsummary'] = 'Assignment summary';
$string['component'] = 'Component';
$string['courseid'] = 'Course ID';
$string['courseprogressexpl'] = 'This compares normalised results between the first and the last available graded activity. Differences in the difficulty and learning objective of the activities can influence the result.';
$string['courseprogresstitle'] = 'Course development';
$string['courseprogresswarning'] = 'A computed improvement between the first and last graded activity is a possible learning hint, but not evidence of teaching quality. The activities can differ in difficulty, learning objective and assessment format.';
$string['dashboard'] = 'Dashboard';
$string['dashboardtitle'] = 'Teaching and Learning Analytics';
$string['deadline_red'] = 'Deadline red threshold';
$string['deadline_yellow'] = 'Deadline yellow threshold';
$string['doseresponse'] = 'Learning dose-response (Bayesian)';
$string['doseresponse_help'] = 'This estimates how the amount of practice relates to the performance students reach, using a Bayesian Emax (saturating) curve. The horizontal axis is the number of completed quiz attempts; the vertical axis is the normalised score reached.

- **Ceiling (Emax)**: the performance the curve approaches with a lot of practice. If it is well below 100%, something other than practice is capping results (difficulty, prerequisites, prior knowledge).
- **EC50**: the number of attempts that reaches half of the ceiling — roughly where extra practice starts to add little.
- **Shaded band**: the 80% credible interval, i.e. the uncertainty of the curve.
- **Variance explained by practice**: how much of the differences in performance the curve accounts for. If it is very low the verdict is "No clear dose-response" — in this course practice volume does not predict performance, so focus on quality and alignment rather than "do more".

Because your quizzes draw fresh questions each attempt, the attempt count reflects genuine repeated practice rather than repeating the same questions.

Important: this is an observed association, not proof of cause. Students who practise more may differ from those who practise less. Read the curve as a hypothesis to check (for example by adjusting recommended practice and comparing the next cohort), not as evidence that more practice will raise grades.';
$string['doseresponseaxisx'] = 'Completed attempts';
$string['doseresponseaxisy'] = 'Performance (%)';
$string['doseresponseband'] = '80% band';
$string['doseresponsebandnote'] = 'Blue: posterior median of the mean curve with its shaded 80% credible band (10th–90th percentile). Grey: average performance per number of completed attempts (only shown for groups of at least three students).';
$string['doseresponsecredible'] = '90% credible interval';
$string['doseresponsedata'] = 'Chart data';
$string['doseresponseec50'] = 'Attempts for half of the ceiling (EC50)';
$string['doseresponseemax'] = 'Ceiling performance (Emax)';
$string['doseresponseempty'] = 'Not enough quiz practice data yet to estimate a dose-response curve.';
$string['doseresponseexplained'] = 'Variance explained by practice';
$string['doseresponsefit_flat'] = 'No clear dose–response';
$string['doseresponsefit_good'] = 'Reliable fit';
$string['doseresponsefit_poor'] = 'Model does not fit';
$string['doseresponsefit_unknown'] = 'Not enough data';
$string['doseresponsefit_weak'] = 'Tentative fit';
$string['doseresponsefitexpl_flat'] = 'Performance barely changes with the amount of practice: activity does not clearly predict the result in this course, so the fitted curve is essentially flat.';
$string['doseresponsefitexpl_good'] = 'The data support a clear saturating curve: the parameters are estimated with reasonable precision and the model reproduces the spread of results.';
$string['doseresponsefitexpl_poor'] = 'The spread of results is not consistent with the Emax model here, so the fitted curve is unreliable. Interpret it with caution.';
$string['doseresponsefitexpl_unknown'] = 'There are not yet enough students with quiz practice for a reliable estimate.';
$string['doseresponsefitexpl_weak'] = 'A saturating curve is estimated, but with wide uncertainty. Treat the exact values with caution; more practice data would sharpen them.';
$string['doseresponseintro'] = 'Estimated relationship between how much students practise (number of completed quiz attempts) and the performance level they reach, modelled as a saturating Emax curve and fitted with a grid-based Bayesian posterior.';
$string['doseresponselowerband'] = 'Lower 10%';
$string['doseresponsemedianline'] = 'Posterior median';
$string['doseresponseobservations'] = 'Observations';
$string['doseresponseobserved'] = 'Observed average';
$string['doseresponseppc'] = 'Model check (result spread)';
$string['doseresponseppcdetail'] = 'Observed spread {$a->obs}, model spread {$a->rep}';
$string['doseresponsequizzes'] = 'Quizzes';
$string['doseresponsesigma'] = 'Spread around the curve (σ)';
$string['doseresponseupperband'] = 'Upper 90%';
$string['doseresponsewarning'] = 'This is an observed association, not proof of cause: students who practise more may differ from those who practise less. The curve summarises the current cohort and is not a prediction for individuals.';
$string['effectivedeadlinesconsidered'] = 'Effective deadlines considered (overrides and extensions)';
$string['emptynomatchingactivities'] = 'No matching graded activities found.';
$string['emptynoquizattempts'] = 'This course has no quizzes with several completed attempts.';
$string['emptynotenoughobservations'] = 'Not enough observations for this analysis.';
$string['emptynovalidgrades'] = 'No valid grades yet.';
$string['events'] = 'Events';
$string['eventsperday'] = 'Events per day';
$string['extensionsapplied'] = 'Extensions applied';
$string['forecast'] = 'Forecast';
$string['groupoverrides'] = 'Group overrides';
$string['late'] = 'Late';
$string['lateindicator'] = 'Traffic light: late submissions';
$string['laterate'] = 'Late submission rate';
$string['learningprogress'] = 'Learning progress';
$string['min_observations'] = 'Minimum observations for reliable forecasts';
$string['moduleevents'] = 'Module events';
$string['moduleusage'] = 'Module usage';
$string['noaccess'] = 'You do not have permission to access this page.';
$string['nodata'] = 'No data is available for this period.';
$string['noduedate'] = 'No due date';
$string['norateavailable'] = 'No rate is available yet.';
$string['ontime'] = 'On time';
$string['peakactiveusers'] = 'Peak active users';
$string['perioddays'] = '{$a} days';
$string['periodselection'] = 'Analysis period';
$string['pluginname'] = 'Teaching and Learning Analytics';
$string['privacy:metadata'] = 'The Teaching and Learning Analytics plugin does not store any personal data.';
$string['progressactivities'] = 'Activities';
$string['progressdeclined'] = 'Declined';
$string['progressexpl_declining'] = 'For many students the result of the last completed attempt is lower than the first. Check attempt settings, question variation, time pressure and the comparability of attempts.';
$string['progressexpl_improving'] = 'For a large share of students the result rises between the first and the last completed attempt. This can indicate learning through repetition, feedback or additional engagement with the material.';
$string['progressexpl_mixed'] = 'The development is mixed: some students improve while others stay stable or achieve lower results.';
$string['progressexpl_stable'] = 'Results change little between attempts. This can mean many students already reach their performance level on the first attempt, or that further attempts show little additional learning gain.';
$string['progressexpl_unknown'] = 'There are not yet enough students with several completed attempts for a reliable assessment.';
$string['progressimproved'] = 'Improved';
$string['progressmeanattempts'] = 'Avg attempts';
$string['progressmeanbest'] = 'Avg best';
$string['progressmeanfirst'] = 'Avg first';
$string['progressmeanlast'] = 'Avg last';
$string['progressmedianchange'] = 'Median change';
$string['progressparticipants'] = 'Participants';
$string['progressstable'] = 'Stable';
$string['progressstatus_declining'] = 'Declining';
$string['progressstatus_improving'] = 'Improving';
$string['progressstatus_mixed'] = 'Mixed';
$string['progressstatus_stable'] = 'Stable';
$string['progressstatus_unknown'] = 'Not enough data';
$string['progresstruncated'] = 'Showing {$a->shown} of {$a->total} quizzes';
$string['scoredistributions'] = 'Score distributions';
$string['scoredistributionstruncated'] = 'Showing {$a->shown} of {$a->total} activities';
$string['scoreexpl_ceiling'] = 'Grades cluster near the maximum. Interpret this observation against the assessment design; it does not, by itself, establish a problem or independent competence.';
$string['scoreexpl_floor'] = 'A large share of grades is in the very low range. The activity may be too hard, unclearly worded or marked inappropriately.';
$string['scoreexpl_middle'] = 'Many grades lie in the middle range. Under the chosen rules the activity shows no marked floor, ceiling or U-shaped effect.';
$string['scoreexpl_mixed'] = 'The distribution shows no clearly classifiable pattern. Review the histogram, median and quartiles in the context of the activity.';
$string['scoreexpl_unknown'] = 'There are not yet enough grades for a reliable assessment.';
$string['scoreexpl_ushape'] = 'Many grades cluster at both extremes while the middle range is sparsely represented. This can indicate two distinct performance groups, ambiguous tasks or very binary marking.';
$string['scorehigh'] = 'Above 80%';
$string['scoreinvalidgrades'] = 'Excluded invalid grades: {$a}';
$string['scorelow'] = 'Below 20%';
$string['scoremedian'] = 'Median';
$string['scoremiddle'] = '40–60%';
$string['scorequartiles'] = 'Q1–Q3';
$string['scorestatus_ceiling'] = 'Ceiling effect';
$string['scorestatus_floor'] = 'Floor effect';
$string['scorestatus_middle'] = 'Middle-centred';
$string['scorestatus_mixed'] = 'Inconclusive';
$string['scorestatus_unknown'] = 'Not enough data';
$string['scorestatus_ushape'] = 'U-shaped';
$string['scorevalidgrades'] = 'Valid grades';
$string['settings'] = 'Settings';
$string['statistics'] = 'Statistics';
$string['submittedassignments'] = 'Submitted assignments';
$string['tla:view'] = 'View the Teaching and Learning Analytics course dashboard';
$string['tla:viewall'] = 'View Teaching and Learning Analytics across all courses';
$string['totalevents'] = 'Total events';
$string['trafficlight_green'] = 'Green';
$string['trafficlight_red'] = 'Red';
$string['trafficlight_unknown'] = 'Not enough data';
$string['trafficlight_yellow'] = 'Yellow';
$string['trafficlightminimum'] = 'At least {$a} submitted assignments are required for an assessment.';
$string['unresolveddeadlines'] = 'Unresolved deadlines';
$string['useroverrides'] = 'User overrides';
$string['view'] = 'View analytics';
