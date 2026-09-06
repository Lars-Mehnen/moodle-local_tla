# Assessment design: implementation and evidence contract

## Principle

Read configuration, explain its implications, state unverified context. Do not
simulate students, infer misconduct, change grades or assign a fairness score.
Every result is about the **current base configuration** of a live activity.
It is not a reconstruction of the rules in force when historical grades arose.

## Implementation

`assessment_design_repository` issues two course-scoped SELECT statements, one
for quizzes and one for assignments. Live course-module joins exclude modules
being deleted and orphaned instances; hidden modules remain visible to authorised
course viewers. Correlated EXISTS checks return only presence flags, never
individual override records. Explicit field whitelists restrict exported data.

`assessment_design_analyzer` is deterministic and database-free. It returns
stable rule codes, statuses, translation keys, context and review actions.
`assessment_design_service` supplies a fresh snapshot and shared limitations.
`assessment_design_presenter` renders paginated configuration/finding cards for
both the dashboard and the standalone settings-only page.

`score_distribution_interpreter` joins settings to outcome rows by course-module
id (not potentially overlapping module instance ids). Both dashboard and report
use it. Metrics remain unchanged; the interpretation is qualified by current
settings. The underlying descriptive analyser also marks a ceiling as `info`,
so a direct caller does not receive a stand-alone red ceiling flag.

## Main rules

| Configuration | Finding, not an overall verdict |
|---|---|
| Quiz: multiple complete attempts, highest grade | Other lower attempt grades are not averaged into the result. Question penalties/manual changes are unverified. |
| Quiz: multiple attempts, average | Earlier lower outcomes remain relevant after a later successful attempt. |
| Quiz: multiple attempts, first | Later improvements do not replace the first grade through this rule. |
| Quiz: multiple attempts, last | Earlier results are replaced, but later deterioration can also replace a better result. |
| Quiz: one attempt | One complete attempt in base settings; no inference about question-level correction or a different assessment. |
| Quiz: review only immediately | A transient two-minute standard review phase; content unverified. |
| Quiz: standard review only after close | No standard post-attempt review window between complete attempts under those base permissions. During-question/external feedback may exist. |
| Quiz: after-close review without a closing date | That phase is not reached under the base schedule. |
| Quiz: maximum marks but no earned marks | Not treated as feedback on the student's response. |
| Assignment: manual reopening | Additional attempts require a grading/reopening action; not automatically available to everyone. |
| Assignment: automatically until pass | Passing does not imply entitlement to improve to full marks. The main grade-to-pass value is checked for presence. |
| Assignment: automatic reopening | Reopening configured subject to the limit/access rules; no inference about the marking rubric. |
| Assignment: no submissions | External/offline assessment may exist; not classified as missing assessment. |
| Assignment: marking workflow | Actual feedback release cannot be established from settings alone. |
| Assignment: planned grading on/after cut-off | A scheduling review question, not proof that feedback was late. |
| Assignment: group submissions | Shared product is not individual evidence; the existing individual deadline analysis is not extended. |
| Assignment: Moodle grade penalties enabled | Presence noted; exact penalty rules unverified. TLA does not apply penalties. |
| Overrides, extensions, locks or availability restrictions | Base observations must not be generalised to everyone. |

Quiz review decoding respects phases separately: an attempt-permission bit in
one phase cannot enable question feedback configured for another. Earned-mark
visibility also requires maximum-mark visibility. Overall feedback is independent
of the attempt-view gate. An enabled category can still be empty; no feedback
quality, reading or comprehension is inferred.

## Scope intentionally left unknown

Activity purpose, effective total-grade contribution, manually overridden grades,
individual effective access, historical settings, external evidence and
independent competence are not calculated. No course-level metadata editor or
self-declaration database has been added. CodeRunner and other per-question
penalty schemes are explicitly marked unverified; merely reading the quiz's
preferred behaviour is not sufficient.

Only the main assignment grade item is used for its grade-to-pass setting.
Subgrades, gradebook formulas and compensating course-category rules are not
reconstructed. Numbers shown are activity settings, not final-course weights.

## APIs consulted for the target schema

- Moodle 5.2 quiz schema: `public/mod/quiz/db/install.xml` in `MOODLE_502_STABLE`.
- Moodle 5.2 assignment schema: `public/mod/assign/db/install.xml` in the same branch.
- Moodle quiz grading constants: `public/mod/quiz/lib.php`.
- Review phases: `mod_quiz\question\display_options`.
- Assignment reopening constants: `public/mod/assign/locallib.php`.

The PHPUnit constant-parity test checks the deployed core values. Schema review
is not a replacement for running the suite on the intended Moodle database.
