# Lecturer guide

TLA reads real course data and settings. It does not change activities, grades,
question banks or student records. It does not identify AI use or certify
individual competence.

## Open a course

Use `/local/tla/dashboard.php?id=<courseid>` with `local/tla:view` in that course.
The full dashboard links to the lighter
`/local/tla/assessment_design.php?id=<courseid>` page. That page reads settings,
not student histories, and can be useful before the course starts.

The 7/28/90-day filter applies to time-filtered activity/deadline views. The
assessment-design panel explicitly shows **current settings**, not historic
rules. Its own timestamp is displayed. Existing grade/attempt analyses cover
their documented course-wide scope.

## Assessment design

Open an activity card to see the values read and individual findings. Statuses
mean observed, review, unknown/not evaluated, or not applicable. There is no
fairness score. A single-attempt assessment is not automatically bad; multiple
attempts are not automatically good. Their purpose matters and is not inferred.

For a repeated quiz, the average or first-attempt rule can leave early lower
results grade-relevant after later improvement. Highest-attempt and last-attempt
rules behave differently. These are whole-quiz rules, not a determination of
question-level correction penalties.

Assignment reopening until passing does not guarantee improvement to maximum
points. Manual reopening does not mean a retry has already been offered. Feedback
permissions/planned dates are not proof of useful feedback being available,
read or understood. Overrides and availability constraints can make effective
rules differ across students; the new check reports that boundary explicitly.

Read the scope panel before drawing conclusions. It lists unverified gradebook
weights, historical settings, external assessments and individual authorship.
Existing published assessment rules should not be changed retrospectively merely
because TLA raises a review question.

## Score distributions

Ceiling, floor, U-shaped and other labels describe observed grade shapes. A
ceiling is informational. With highest/last-of-multiple-attempt settings, high
final grades may be compatible with successful revision, but the current settings
do not establish the cause of historical results. Other causes and different
past rules remain possible.

For repeated quizzes, compare the existing first/last/best-attempt aggregates
with the final-grade distribution. Those progress aggregates include only people
with multiple completed attempts, not necessarily everyone represented in the
final grades. Neither apparent progress nor high scores proves independent
learning. High scores alone do not justify randomising hundreds of tasks.

Low or split grades invite review of prerequisites, instructions, marking and
missing-work treatment. These are possible explanations, not causal diagnoses.
A middle-centred distribution is not a universal quality target for all types
of assessment, particularly practice-to-completion activities.

## Other existing analyses

Activity counts describe recorded events, not attention or understanding.
The deadline indicator uses the existing effective-deadline resolver for
individual submissions. Group submissions remain excluded from that analysis.

The Bayesian Emax section fits an association between completed quiz attempts
and achieved performance. Its Emax and EC50 are fitted model parameters, not
causal effects or recommended numbers of attempts. EC50 is the model's exposure
at half its asymptotic response, not the point where practice stops helping.
A flat fit does not prove that practice is ineffective. This statistical model
uses real aggregates; it does not generate demonstration courses.

## Privacy and minimum observations

The configured minimum protects outcome summaries for small groups. Settings
are not student observations and remain inspectable without student data. The
plugin stores no personal records. The external report enforces at least the
configured site minimum for outcome analyses.
