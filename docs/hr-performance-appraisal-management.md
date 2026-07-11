# HR Performance, Goals, And Appraisal Management

BIWMS Performance Management Phase 1 provides controlled performance cycles, goals, appraisal snapshots, scoring, moderation, finalization, acknowledgement, development plans, PIPs, probation reviews, recommendations, history, and diagnostics.

## Architecture

The module reuses existing BIWMS HR structures:

- `Employee` remains the employee source of truth.
- `Department.manager_id` provides the first manager-scope boundary.
- Attendance and workforce data remain separate context sources.
- `AuditTrailService` records lifecycle actions.
- Filament strict authorization and `hr.*` permissions remain the access-control standard.

Performance records do not automatically alter payroll, salary, grade, position, employment status, promotions, termination, confirmation, or disciplinary sanctions.

## Rating Scales

`performance_rating_scales` define score boundaries and defaults. `performance_rating_scale_levels` map score ranges to named outcomes such as Exceptional, Meets Expectations, or Does Not Meet Expectations.

Rules enforced in Phase 1:

- scale minimum cannot exceed maximum;
- level ranges cannot overlap;
- levels must stay inside the parent scale range;
- only one active default scale can apply to the same business/date range.

Completed appraisals store rating-scale snapshots so historical outcomes remain stable.

## Appraisal Cycles

`performance_appraisal_cycles` define the review period and stage dates. `PerformanceAppraisalCycleService` generates deterministic employee assignments and appraisals from the selected template.

Cycle assignments snapshot:

- employment status;
- position/job title;
- department;
- manager.

Later transfers do not silently rewrite historical appraisal ownership.

## Templates And Snapshots

Templates define component weights, sections, and items. Component weights must total 100%. Generated appraisals copy template sections and items into appraisal snapshot tables, so later template edits do not mutate active or historical appraisals.

## Goals And KPIs

Goal plans group employee goals for an appraisal cycle. Active goal weights must total 100% before approval. Goal progress updates are append-only history; submitting a new update does not overwrite prior submissions.

Material changes to an approved goal move it back to proposed status for reapproval.

## Self And Manager Assessment

Employees can submit their own self-assessment when authorized. Managers can assess assigned reports. Submitted assessments are locked unless reopened by an authorized HR workflow.

Employee ratings, manager ratings, secondary ratings, moderated ratings, and final ratings are stored separately for traceability.

## Scoring

`PerformanceAppraisalScoringService` calculates weighted scores from appraisal snapshot items and returns an explainable section breakdown. It does not finalize appraisals.

`PerformanceAppraisalFinalizationService` validates manager submission, persists the calculated score, maps the final score to the configured rating level, and finalizes the appraisal.

## Moderation

Moderation preserves original scores. Any score adjustment requires a reason and is audited. Phase 1 does not implement forced ranking or target rating distributions.

## Acknowledgement And Disputes

Employee acknowledgement records whether the employee acknowledged, commented, disputed, or refused acknowledgement. Acknowledgement does not mean agreement. Disputes do not delete or silently rewrite finalized appraisals.

## Development Plans

Development plans and actions may originate from an appraisal. Completion evidence should be treated as private. Completing a plan does not alter appraisal scores.

## Performance Improvement Plans

PIP records are confidential HR records. Activation and completion require explicit permission. Unsuccessful completion flags HR review only; it does not terminate employment or affect payroll.

## Probation Reviews

Probation review recommendations remain advisory. Confirmation, extension, or termination must be performed through a separate authorized HR action. Attendance context can support the review but must not determine the outcome automatically.

## Attendance Context

`PerformanceContextService` can summarize attendance days for a date range. Context is informational:

- approved leave is reported separately;
- missing clock-out is not converted directly into a negative score;
- payroll amounts are not exposed;
- raw attendance events and roster history are not mutated.

## Recommendations

Appraisal recommendations can propose recognition, training, development, PIP, probation, promotion consideration, salary-review consideration, transfer consideration, or similar HR follow-up. Recommendations are advisory and must link to the controlled workflow that implements them.

## Security

Access follows `hr.*` permissions:

- HR access is permission-based.
- Employees see their own performance records through `hr.my_*` permissions.
- Managers see assigned/team records through `hr.team_performance.*`.
- Payroll access does not imply performance access.
- Performance access does not imply payroll access.
- Confidential PIP, dispute, reviewer, manager, and moderation comments must not be exposed to unauthorized users.

Sensitive actions such as finalization, reopening, amendment, PIP activation, probation decisions, and exports should use the existing sensitive-action confirmation pattern when surfaced in UI actions.

## Reconciliation

Run:

```bash
php artisan biwms:performance-reconcile --details
```

Export JSON:

```bash
php artisan biwms:performance-reconcile --details --export=storage/app/reports/performance-reconcile.json
```

The command is report-only and detects common performance inconsistencies such as missing appraisals, missing snapshots, invalid weights, finalized appraisals without scores, moderation changes without reasons, invalid PIP/probation data, and recommendations marked implemented without references.

## Phase 1 Limitations

Phase 1 does not include compensation review, succession planning, recruitment integration, disciplinary case management, forced ranking, drag-and-drop template design, automatic training enrollment, or automatic HR/payroll actions.
