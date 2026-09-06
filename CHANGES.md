# 0.2.0-rc1 (2026090500)

- Add read-only assessment-design repository, pure analyser and shared service.
- Inspect current quiz/assignment attempt/grading/review settings, independently
  of student outcome thresholds. Flag unverified effective overrides/access.
- Add shared paginated UI and a lightweight configuration-only page.
- Reuse the same design-aware score interpretation in dashboard and external API.
- Make ceiling observations informational, including the base classifier.
- Remove automatic inference from high marks to circulating solutions and remove
  the automatic parameterisation advisor and variant-space recommendation.
- Remove the unused time-penalty simulator and its tests.
- Separate the database-writing demo generator and its dedicated test from the
  installable plugin. Keep ordinary software tests.
- Add EN/DE translations; fix previously missing German period-selector strings.
- Enforce at least the configured outcome minimum in the external report.
- Extend API summary/severity with `info`; document evidence, rule codes and limits.
- Update guidance, including non-causal interpretation of the existing Emax model.
- Add shared standalone rule fixtures, helper checks and Moodle integration tests.
- Preserve the Moodle requirement and database schema. No migration, deletions
  of existing courses or automatic grade/configuration changes.
- Mark this build as a beta release candidate pending real Moodle integration,
  installation/upgrade, database and realistic-load verification.

## Archived previous changelog

# Changelog

All notable changes to local_tla (Teaching and Learning Analytics) are documented here.

## 2026-08-20

### Added
- **Learning dose-response (Bayesian)** dashboard section: fits a saturating Emax
  model E = Emax·C/(EC50+C) + N(0,σ) relating practice (completed quiz attempts)
  to the performance level students reach, using a pure-PHP grid-approximated
  Bayesian posterior (no external dependencies). Shows the posterior median curve
  with an 80% credible band, credible intervals for each parameter, the share of
  variance explained by practice, and a posterior-predictive spread check.
- Robust fit classification: `good` / `weak` / `flat` (no clear dose-response) /
  `poor` (spread mismatch) / `unknown` (not enough data).
- `styles.css` to keep dashboard charts within their cards.

### Notes
- Renders with Moodle's core Chart.js wrapper, consistent with the other
  dashboard graphs.
- Stores no personal data (aggregate results only); privacy null provider.
