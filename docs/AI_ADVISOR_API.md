# Read-only TLA course report API (0.2.0-rc1)

Function `local_tla_get_course_quality_report`, service `local_tla_ws`, requires
`local/tla:view` in the validated course context. It returns aggregate outcomes
and current configuration findings, not individual student records. No external
AI service is required. A consuming advisor must preserve caveats and cannot
use this report as evidence of cheating or permission to change assessment.

## Parameters

`courseid` is required. `minobservations` defaults to 8; a caller may increase it
but cannot lower the configured site minimum (`min_observations`). Configuration
findings are not suppressed by a student-observation threshold.

In an administrator-configured Moodle REST service, a client can use:

```bash
curl --fail --silent --show-error 'https://moodle.example/webservice/rest/server.php' \
  --data-urlencode "wstoken=$TLA_TOKEN" \
  --data-urlencode 'wsfunction=local_tla_get_course_quality_report' \
  --data-urlencode 'moodlewsrestformat=json' \
  --data-urlencode "courseid=$COURSE_ID"
```

Treat the token as a credential; prefer POST rather than placing it in URLs.

## Return envelope and changes

The top-level keys remain `courseid`, `generated` (finding count), `summary` and
`findings`. `summary` now includes `info` as well as red/yellow/green/unknown.
Clients must handle the additional severity `info`. It is an informational
observation, not another teaching-quality traffic light.

Each finding keeps `area`, `target`, `severity`, `confidence`, `status`, `summary`,
`evidence` and `recommendation`. The last two remain JSON-encoded strings;
`recommendation` is an empty string if no action is suggested. New human-readable
texts use the Moodle response language; use stable codes for automation.

| Area | Status | Notes |
|---|---|---|
| assessment_design | observed, review, unknown, notapplicable | Current base configuration; always informational severity. |
| score_distribution | ceiling, floor, ushape, middle, mixed | Descriptive shapes; ceiling now informational with contextual explanation. |
| dose_response | good, weak, flat, poor | Association from the existing model, not causation. |
| course_progress | improving, declining, stable, mixed | Conservative across-activity context. |

## Configuration evidence

The evidence JSON contains `cmid`, `module`, `criterion`, `rule`, `source`,
`checkedat`, `scope`, whitelisted `settings`, and presence flags
`hasoverrides`, `hasexceptions`, `hasrestrictions`, `visible`. The `limits` object
states what was not established: purpose, course-grade weight, historical
settings, individual access, feedback content, question penalties, manual grade
overrides and independent competence. It contains no user/group ids, override
reasons, availability JSON, passwords, answers or per-student grades.

`source=current_configuration` and `scope=base_settings` are essential: an
observation of today's average-attempt rule must not be described as proof of
how each historical individual grade was calculated.

For configuration findings, `confidence` concerns the observation of a setting,
not certainty of a pedagogical judgment. Outcome confidence remains a sample-size
heuristic, not a calibrated causal probability. Evidence documents that basis.

## Design-aware score evidence and recommendations

Score evidence retains its summary statistics and includes `assessmentcontext`:
retry context, interpretation code, current-config source/time and
`historicalmatchverified=false`. Matching uses course-module id.

Recommendation types are: `review_assessment_design`, `review_first_and_final`,
`review_difficulty`, `review_two_groups`, `do_not_reward_volume` (all review
suggestions with `params: null`), and — **gated** — `parameterize_quiz`.

`parameterize_quiz` is emitted **only** for a quiz ceiling whose settings provide
no retry mechanism to explain it (single attempt or unknown grading context); a
ceiling that is already explained by the grading rule (e.g. `highest`/`last` of
multiple attempts, `until pass`) is informational and gets a review suggestion
instead. When emitted, its `params` carry a concrete plan: `cohortn`,
`targetvariants`, `variants`, `params`, `valuesperparam`, `ranges[]` and a
paste-ready CodeRunner (Twig) `snippet` sized to the cohort (≥ ~20× N variants).
It is still a **draft suggestion, not a change command** — TLA never edits
questions. A flat model fit is explicitly not evidence that practice has no
benefit. Consumers should tolerate future types and never invent an action.

## Operational limits

The report has no event-log scan but still performs the existing outcome/model
analyses; it is not the same workload as the two-query standalone settings page.
Do not infer scalability from the number of API calls. Verify installation,
capabilities, return-schema cleaning and realistic course load in the target
Moodle before production use.
