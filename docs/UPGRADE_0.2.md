# Upgrade to 0.2.0-rc1

## Before replacing files

Use a staging copy first. Back up the existing plugin directory and follow the
site's standard database/file backup procedure. Confirm that the running Moodle
meets the unchanged requirement `2026042001.00` (Moodle 5.2 target). This release
has not been certified on an actual Moodle installation by its helper tests.

## Clean replacement matters

For a manual installation, move the old `public/local/tla` directory to a backup
**outside the Moodle code/web root**, then put the complete new `tla` directory in
its place with the appropriate ownership and permissions. Do not rename it to
another plugin directory under `local/`, and do not merely overlay the new files.

The following files must no longer be present in the installed plugin:

```text
classes/indicator/late_penalty_simulator.php
classes/indicator/parameterization_advisor.php
classes/local/demo_data.php
tools/create_demo_data.php
tests/late_penalty_simulator_test.php
tests/parameterization_advisor_test.php
tests/demo_data_test.php
```

Do not uninstall the plugin. No schema change, deletion of existing records or
student-data migration is required. Open Site administration / Notifications,
complete the plugin version upgrade and purge caches.

Already-existing demo courses or users are not removed by this upgrade. Any
cleanup is a separate, explicitly authorised maintenance task; do not delete
records just because their names resemble demonstrations.

## Smoke checks in the target Moodle

1. Open `/local/tla/assessment_design.php?id=<courseid>` as an authorised lecturer.
   Check a course with no grades and one with real activities. Verify that the
   displayed attempt counts, methods and dates match the Moodle activity forms.
2. Open the full dashboard. Check that the assessment-design link and panel work,
   including English/German, narrow screens and pagination beyond 20 activities.
3. Verify a repeated quiz using highest, average, first and last attempt rules.
   High final grades should have an informational, contextual explanation, not
   an automatic allegation or a generated parameterisation recommendation.
4. Check an assignment with manual reopening and one with reopening until pass.
   Test visible overrides, extensions/locks and assignment group mode. The new
   check must state its limits rather than claim per-student effective rules.
5. Confirm access rejection for a student/guest and course isolation for a
   lecturer without access to another course. Exercise the external API as well.
6. Verify the complete Moodle PHPUnit suite on its **isolated test database** and
   run the site's coding checks. Check fresh install and upgrade separately.
7. Measure the settings-only page and full dashboard on a realistic large course;
   two settings queries are not a benchmark of the existing full dashboard.

Keep the release as a candidate until these checks pass. No downgrade procedure
or production deployment is performed by the ZIP itself.
