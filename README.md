# Teaching and Learning Analytics (local_tla)

Read-only, course-scoped analytics for real Moodle courses. Version **0.2.0-rc1**
adds a configuration-based assessment-design check and contextual interpretation
of high final grades. No demo data, grade simulation or generated task variants
are used by the plugin runtime.

## Status and target environment

Target inherited from the uploaded plugin: Moodle 5.2, PHP 8.3;
`$plugin->requires = 2026042001.00`. The plugin is marked **MATURITY_BETA** while
release-candidate installation, upgrade and full Moodle integration checks remain
outstanding. Do not mistake syntax/helper tests for a production certification.
Intended databases are MariaDB/MySQL and PostgreSQL through Moodle DML.
All runtime computation is PHP; no external AI service or external libraries.

## Install / upgrade

Install the `tla` folder as `public/local/tla` in a Moodle 5.2 installation.
Complete the Moodle administrator Notifications upgrade and purge caches.

**When upgrading manually, replace the old plugin directory with the clean new
one; do not merely copy over it.** Overlay copying would leave the removed demo
generator and experimental classes on disk. Back up the old directory outside
the Moodle code/web root first. Do not uninstall the plugin, and do not delete
real courses, grades or the plugin database table. See `docs/UPGRADE_0.2.md`.
No schema change or student-data migration is included in this release.
Existing demo courses, if any, are not automatically identified or deleted.

## Access

The full dashboard is `/local/tla/dashboard.php?id=<courseid>`.
The lightweight settings-only check is
`/local/tla/assessment_design.php?id=<courseid>`, also linked from the dashboard.

Both pages enforce logged-in course context and `local/tla:view`. Capabilities,
not role names/ids, control access. The existing capability defaults are retained.
`local/tla:viewall` remains reserved for a future cross-course view. The external
report validates course context and requires `local/tla:view` too.

## Features

- Activity overview: events per day, active users and module usage.
- Individual-submission deadline indicator using effective deadlines, including
  existing override/extension handling. Group submissions remain excluded there.
- Descriptive score distributions from numeric gradebook values (assign/quiz).
  A ceiling is informational, never by itself a misconduct or quality alarm.
- Quiz attempt progress and conservative across-activity progress; these describe
  performance patterns, not independent learning or teaching quality.
- Existing Bayesian Emax practice-performance model; an association, not a causal
  experiment. Statistical model computation is not artificial course data.
- **Assessment design**: actual current quiz/assignment base configuration, even
  before any grades exist. Checks complete-attempt grading, standard quiz-review
  display windows, assignment reopening, planned feedback/cut-off conflicts and
  the presence of overrides, extensions/locks and availability restrictions.
- The same assessment-design and score-interpretation services serve the
  dashboard and external report. Recommendations carry their scope and limits.

## What the assessment-design check does NOT establish

It does not certify fairness, individual authorship or competence. Activity
purpose is unknown; there is no implicit inference from activity names.
The effective contribution to the course total, manual grade overrides, external
assessments and historical settings are not reconstructed. Per-person effective
access and override combinations are not evaluated by this new design check;
when such conditions exist, their presence is flagged. The established deadline
resolver has its own, separate scope.

Quiz review permissions are not evidence of feedback content being present,
read or understood. Question-specific penalties, interactive retries and
CodeRunner grading settings are explicitly **not evaluated**. Whole-quiz retries
must not be confused with retries within one question. Assignment feedback
release is not inferred from a planned grading date.

Findings use `observed`, `review`, `unknown`, or `notapplicable` instead of a new
quality traffic light. The settings-only page reads configuration in two bundled
SELECT statements, with presence checks for special cases. It does not scan
student answers, grade histories or event logs. Pagination exposes all activities
in groups of 20. This architecture avoids one query per student; real-course load
testing is still required before production release.

## Data and privacy

Runtime data sources remain Moodle logs, assignments, quiz attempts and the
gradebook. The new check reads quiz/assignment settings, course-module metadata,
a main assignment grade-to-pass value, and presence flags from override/extension
tables. It does not export user ids, group ids, override reasons, passwords,
availability JSON, individual grades, answers or submissions.

The plugin stores no personal records. Its existing `null_provider` and reserved
aggregate-only `local_tla_course_daily` table are unchanged. `db/tasks.php`
registers no task: calculations run on request. No automatic changes to grades,
courses, questions, deadlines or user records are made.

The configured `min_observations` threshold protects existing outcome analyses.
It does not suppress configuration-only facts. The web service can no longer
lower the site's configured minimum via its request parameter.

## Removed / separated

The unused time-penalty simulator and automatic question-parameterisation advisor
are removed, together with their dedicated tests. The demo generator and its
own test have been separated into a **developer-only archive**, not included in
the installable plugin. Ordinary software tests remain. The original upload is
the archival source for the removed experiments.

## Tests

Database-free developer checks (does not load Moodle or create records):

```bash
php public/local/tla/tests/standalone/assessment_design_check.php
```

In a dedicated Moodle PHPUnit environment (project root containing `vendor/`):

```bash
vendor/bin/phpunit --testsuite local_tla_testsuite
```

The suite includes repository/course isolation, core constant parity, language
coverage and design rules. Full Moodle tests require a correctly configured,
isolated PHPUnit database/dataroot; never initialise them against production.
See `docs/RELEASE_CHECKLIST.md` and the supplied validation report for exactly
what was and was not executed.

## Documentation

- `docs/ASSESSMENT_DESIGN.md`: rules, evidence and exclusions.
- `docs/UPGRADE_0.2.md`: clean upgrade and smoke checks.
- `docs/LECTURER_GUIDE.md`: interpreting the dashboard.
- `docs/AI_ADVISOR_API.md`: updated read-only API contract.
- `CHANGES.md`: version changes.

License: GNU GPL v3 or later; full text in `LICENSE`.
