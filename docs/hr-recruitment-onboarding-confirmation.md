# HR Recruitment, Onboarding, Probation, and Confirmation

BIWMS recruitment is designed as a traceable workflow from approved workforce need to employee onboarding. It complements existing HR, payroll, attendance, performance, and employee onboarding modules; it does not replace them.

## Architecture

The Phase 1 recruitment domain is built around these records:

- `RecruitmentRequisition`: approved workforce need and headcount authority.
- `RecruitmentVacancy`: openings created from an approved requisition.
- `RecruitmentJobPosting`: channel-specific publication history.
- `RecruitmentCandidate`: candidate profile, separate from applications.
- `RecruitmentApplication`: candidate application to one vacancy.
- `RecruitmentApplicationStageHistory`: append-only stage transition history.
- `RecruitmentApplicationScreening`: advisory screening result using snapshotted criteria.
- `RecruitmentInterview*`: panel, schedule, scorecard, and score records.
- `RecruitmentSelectionReview`: advisory ranking and final recommendation.
- `RecruitmentOffer`: approved/issued/accepted offer versions.
- `RecruitmentPreEmploymentCheck`: controlled pre-employment checks.
- `RecruitmentOnboardingPlan` and `RecruitmentOnboardingTask`: employee onboarding checklist after conversion.
- `EmployeeConfirmationDecision`: controlled confirmation/probation extension decision.

Existing structures are reused:

- `Employee` remains the HR staff record.
- `EmployeeOnboardingService` creates employee records and optional user accounts.
- `PerformanceProbationReview` remains the probation review record.
- Payroll setup remains separate and controlled by payroll workflows.
- Audit logging uses the existing `AuditTrailService`.

## Lifecycle

1. Create a requisition as `draft`.
2. Submit and approve the requisition.
3. Create vacancies only within approved headcount and salary authority.
4. Open vacancies and publish job postings.
5. Create candidate profiles and applications.
6. Move applications through validated stage transitions.
7. Run screening, assessments, interviews, and selection review.
8. Draft, approve, issue, and accept an offer.
9. Complete required pre-employment checks.
10. Explicitly convert the accepted application to an employee.
11. Generate onboarding plan/tasks.
12. Schedule probation review through performance management.
13. Submit, approve, and implement confirmation decisions through controlled HR action.

## Boundaries

Recruitment automation is advisory. BIWMS does not:

- automatically hire candidates;
- automatically reject candidates based only on scores;
- automatically activate salary or create payroll earning/deduction lines;
- automatically promote or transfer internal candidates;
- automatically confirm or terminate employees;
- automatically expose salary, medical, identity, blacklist, or confidential check details to broad HR/manager roles.

## Candidate Privacy

Candidate profiles and applications are separate so one candidate can apply to multiple vacancies. Candidate documents must use private storage paths. The system rejects absolute or path-traversal document paths and does not expose direct public file URLs.

Confidential candidate documents, blacklist reasons, pre-employment checks, medical summaries, and offer compensation require narrow permissions.

## Reconciliation

Run:

```bash
php artisan biwms:recruitment-reconcile --details
```

Optional JSON export:

```bash
php artisan biwms:recruitment-reconcile --details --export=storage/app/reports/recruitment-reconcile.json
```

The command is diagnostic-only. It reports issues such as invalid headcount, open vacancies linked to unapproved requisitions, application stage-history mismatches, screening without snapshots, offers issued without approval, expired offer acceptance, and completed onboarding plans with incomplete mandatory tasks.

## Phase 1 Limitations

Phase 1 provides the core data model, service boundaries, Filament resources, permissions, audit hooks, and diagnostics. Candidate portal pages, e-signature, agency integrations, background-check integrations, job-board publishing APIs, automated communications, and advanced talent-pool management are intentionally deferred.
