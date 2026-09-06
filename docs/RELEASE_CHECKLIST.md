# Release checklist: 0.2.0-rc1

This is a checklist to execute, not a claim that its items have passed. The
supplied validation report records the checks actually run in the build
container. Keep MATURITY_BETA until target-environment checks are complete.

## Package and upgrade
- [ ] Verify the new version (`2026090500`) is above the installed version.
- [ ] Verify Moodle requirement `2026042001.00` and the intended PHP version.
- [ ] Fresh installation on a dedicated Moodle 5.2 test installation.
- [ ] Clean upgrade from the previous plugin without uninstalling it.
- [ ] Removed files absent after upgrade; no overlay leftovers.
- [ ] No unexpected migration or deletion of existing data.
- [ ] Notices/version upgrade and cache purge completed.

## Code and tests
- [ ] All PHP files pass lint in the target PHP version.
- [ ] Database-free `tests/standalone/assessment_design_check.php` passes.
- [ ] Full Moodle PHPUnit suite passes on an isolated test DB/dataroot.
- [ ] Moodle coding standard checks pass (not provided by syntax checking).
- [ ] EN/DE translations and dynamic rule keys resolve without placeholders.
- [ ] Actual quiz/assignment settings agree with displayed readings.
- [ ] Core grading/review constants match the rules.

## Security and privacy
- [ ] Both pages reject guests/students without the capability.
- [ ] Permission in one course does not grant access to another.
- [ ] External report validates context and enforces the capability.
- [ ] API cannot lower the site minimum-observation threshold.
- [ ] No personal records/override reasons/passwords are exported.
- [ ] No runtime course, grade, question or user writes.
- [ ] Small-group outcome suppression remains intact.

## Functional cases
- [ ] Empty course and supported activities without grades work.
- [ ] Hidden and deletion-in-progress modules are treated correctly.
- [ ] All four quiz grade methods, unlimited and single attempts checked.
- [ ] Quiz-review phase gates and no-close-date case checked.
- [ ] Assignment single/manual/automatic/untilpass cases checked.
- [ ] Overrides/extension/lock flags agree with the course configuration.
- [ ] Question-specific/CodeRunner rules remain explicitly unverified.
- [ ] A ceiling is informational in both dashboard and API.
- [ ] No auto-randomisation payload, penalty simulator or demo generator in runtime.

## Deployment quality
- [ ] MariaDB/MySQL integration checked.
- [ ] PostgreSQL integration checked.
- [ ] Settings-only page measured on a realistic large course.
- [ ] Full dashboard measured separately, including its existing model analysis.
- [ ] Pagination beyond 20 activities works in both views.
- [ ] Desktop/mobile rendering and keyboard access verified in Moodle EN/DE.
- [ ] Any Moodle site-specific extensions documented as tested or unsupported.
