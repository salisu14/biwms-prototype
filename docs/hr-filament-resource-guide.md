# HR Filament Resource Guide

BIWMS HR resources are UI orchestration only. Business lifecycle behavior belongs in services, models, policies, and audit services.

## Architecture

- Each HR resource must define `permissionModule()` and `permissionResource()`.
- Strict authorization remains enabled; every resource model must have a policy.
- Resources should use Admin and HR panel navigation groups consistently.
- Generated resources must not ship with empty forms, tables, or infolists.

## Forms

- Use sections with responsive columns.
- Use searchable relationship selects for employee, candidate, department, period, and vacancy references.
- Keep status, stage, actor, timestamp, token, and calculated fields guarded or read-only.
- Use private storage for candidate, HR, payroll, and identity documents.
- Do not expose passwords, secrets, tokens, recovery codes, confidential checks, or payroll-sensitive data.

## Tables

- Show identifying numbers/names, organizational context, status/stage badges, and lifecycle dates.
- Use filters for status, employee, candidate, vacancy, department, period, and date where relevant.
- Prefer action groups when resources have multiple workflow actions.
- Avoid per-row database work in callbacks; eager-load common relationships in the resource query when needed.
- Hide secondary columns by default instead of removing them.

## Infolists

- Group record identity, workflow state, organizational context, timestamps, actors, and totals.
- History and ledger views should show immutable event context and avoid edit links.
- Private attachments should be exposed only through authorized temporary/private access.

## Lifecycle Actions

- Filament actions must call existing services.
- Do not mutate document status directly in resource callbacks.
- Use confirmation dialogs and reason forms where business rules require them.
- Sensitive actions such as posting, reversing, regenerating cards, approving offers, and implementing confirmations must keep password confirmation in place.
- Audit logging should remain in the service layer.

## Read-Only Resources

History, verification log, raw event, and ledger resources should generally be read-only:

- no create route;
- no edit route;
- no delete or bulk delete action;
- view/export only where authorized.

Examples include recruitment history, appraisal history, roster history, ID card histories, verification logs, payslip history, and raw attendance events.

## Admin vs HR Panel

- Admin can host setup and administrative views.
- HR panel should expose operational HR workspaces: Human Resources, Employee Identity, Leave Management, Time & Attendance, Attendance Review, Workforce Scheduling, Performance Management, Recruitment & Onboarding, and Payroll.
- Candidate portal and employee self-service pages should not appear in Admin/HR navigation unless explicitly scoped.

## Adding Future HR Resources

1. Create the model, policy, migration, service, and tests first.
2. Add the Filament resource with permission metadata.
3. Build form, table, and infolist schemas.
4. Register the resource explicitly in `HrPanelProvider` if it belongs in the HR panel.
5. Keep lifecycle actions thin and service-driven.
6. Add smoke coverage for route registration, authorization, and read-only behavior.
7. Run `php artisan biwms:security-audit` and the relevant reconcile command.
