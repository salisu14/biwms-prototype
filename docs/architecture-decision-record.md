# BIWMS Architecture Decision Record (ADR)

## Purpose

This document explains why BIWMS is built the way it is before pilot deployment. It gives future developers a practical map of the key architectural, security, posting, ledger, and business decisions so they do not have to rediscover them from code.

BIWMS is currently entering pilot readiness. This ADR should evolve as the product grows, especially when deployment architecture, tenancy, posting behavior, costing, or module boundaries change.

## 1. System Architecture

BIWMS is a Laravel + Filament monolith for the pilot stage.

The monolith is intentional:

- It keeps business workflows, posting logic, authorization, reports, and operational diagnostics in one deployable application.
- It reduces operational complexity during the client pilot.
- It lets the team harden ERP correctness before splitting services or introducing SaaS tenancy concerns.

Core architecture choices:

- Laravel provides the application framework, queues, jobs, commands, testing, policies, and service layer.
- Filament provides the admin and operational UI.
- PostgreSQL is the source database.
- Redis is used for queue, cache, session, and locking where configured.
- DigitalOcean and Laravel Forge are the pilot deployment target.
- The future direction is SaaS/multi-tenant, but the pilot prioritizes correctness, security, and operational stability first.

## 2. Module Boundaries

Major BIWMS modules include:

- Sales
- Procurement
- Inventory
- Warehouse
- Manufacturing
- Finance
- CRM
- Pricing
- HR/Payroll
- Security/Admin
- Reporting/Dashboard

Modules should communicate through services, posting routines, ledger entries, and events rather than uncontrolled cross-module writes.

Examples:

- Sales posting should call posting services that create customer, item, value, and G/L entries.
- Payments should apply to customer/vendor ledger entries rather than directly mutating invoice totals alone.
- Inventory, manufacturing, and warehouse flows should create item ledger and value entries rather than directly changing stock fields as the source of truth.
- Reporting should read ledgers or query services, not cached master-table balances, unless explicitly displaying a cache/reconciliation indicator.

## 3. Security Model

Security is enforced by default.

Decisions:

- Filament `strictAuthorization()` is enabled.
- Every Filament resource requires permission metadata and policy coverage.
- Standard permission format is `module.resource.action`.
- Password confirmation is required for destructive and sensitive actions.
- Admin sessions have idle and absolute lifetime controls.
- Critical actions are audit logged.
- IDOR regression tests are required because BIWMS currently uses integer primary keys.
- Super Admin users must use MFA for pilot readiness.

Security and readiness commands:

```bash
php artisan biwms:security-audit
php artisan biwms:health-check
php artisan biwms:pilot-check
```

Missing authorization should be fixed through policies and permissions, not hidden by disabling strict authorization.

## 4. Posting Engine Principles

Posting follows this standard sequence:

1. Validate.
2. Permission check.
3. Approval check.
4. Password confirmation.
5. Begin transaction.
6. Row lock.
7. Post document.
8. Create ledger entries.
9. Create audit trail.
10. Commit.
11. Notify.

Rules:

- Posting must be idempotent.
- Posted documents are immutable.
- Corrections must use reversal, return, or credit memo flows.
- Double posting must be blocked.
- Posted business records must not be directly mutated.
- Posting routines must run inside database transactions.
- Posting routines should use row locks where concurrent posting is possible.

## 5. Ledger Architecture

Ledgers are the source of truth.

- G/L entries are the source for financial statements.
- Customer ledger entries drive receivables.
- Vendor ledger entries drive payables.
- Bank ledger entries drive cash and bank balances.
- Item ledger entries drive inventory quantity.
- Value entries drive inventory value.

Master-table balances may exist as cached or helper values for performance and UI convenience, but they are not the source of truth. Reconciliation commands should detect when cached values diverge from ledger sums.

## 6. Inventory Philosophy

Inventory is ledger-driven.

- Every stock movement must create Item Ledger Entries.
- Inventory value is tracked through Value Entries.
- Cached item stock must reconcile to item ledger sums.
- Negative stock is blocked by default unless explicitly configured otherwise.
- Inventory reconciliation is diagnostic-first, not auto-repair.
- Location and warehouse stock movements must remain balanced.
- Production consumption and output must be ledger-driven.

The inventory reconciliation command reports inconsistencies and does not mutate data:

```bash
php artisan biwms:inventory-reconcile --details
```

## 7. Approval Workflow

The standard workflow is:

```text
Draft -> Submitted -> Approved -> Posted
```

Optional states include:

```text
Rejected
Cancelled
Reopened
Reversed
```

Rules:

- Only approved documents may be posted.
- Posted records are immutable.
- Workflow actions require permissions.
- Sensitive workflow actions require password confirmation.
- UI actions should reflect both authorization and document status.
- Backend authorization must still block unauthorized route or API access.

## 8. Deployment Architecture

Pilot deployment choice:

- Laravel Forge.
- DigitalOcean VPS.
- PostgreSQL and Redis may run on the same server for pilot simplicity.
- Managed PostgreSQL and managed Redis are recommended as the system grows.
- DigitalOcean Spaces is optional and recommended for files/backups when file volume grows.
- Nginx + PHP-FPM serve the application.
- Supervisor runs queue workers.
- Cron runs the Laravel scheduler.
- Docker is not required for pilot deployment.

The pilot deployment optimizes for a clear operational path, fast troubleshooting, and low infrastructure complexity.

## 9. Testing Strategy

BIWMS protects business correctness with tests around workflows, security, reconciliation, and posting.

Test categories:

- Feature tests for business workflows.
- Authorization regression tests.
- Security audit tests.
- Reconciliation command tests.
- Posting integrity tests.
- Dashboard service tests.

Commands to run before deployment:

```bash
vendor/bin/pint --dirty --format agent
php artisan test --compact
php artisan biwms:security-audit
php artisan biwms:health-check
php artisan biwms:pilot-check
php artisan biwms:inventory-reconcile --details
php artisan biwms:finance-reconcile --details
```

## 10. Documentation Standards

Current documentation:

- `README.md`
- `docs/pilot-setup.md`
- `docs/pilot-deployment-checklist.md`
- `docs/client-feedback.md`
- `docs/release-notes-template.md`
- `docs/architecture-decision-record.md`

Every major feature should update documentation when behavior changes. This is especially important for posting logic, security rules, deployment steps, reconciliation diagnostics, and pilot operating procedures.

## 11. Coding Conventions

Conventions:

- Prefer services for business logic.
- Keep Filament resources thin.
- Do not put posting logic directly in UI actions.
- Use policies for authorization.
- Use transactions and row locks for posting.
- Use enums or constants for statuses.
- Add tests with every business rule change.
- Avoid client-specific hardcoding; prefer configuration.

The UI may initiate actions, but services should own business behavior.

## 12. Business Central Compatibility Goals

BIWMS intentionally follows Microsoft Business Central style behavior where practical.

Compatibility goals:

- Sales Order Ship + Invoice may directly create a Posted Sales Invoice.
- Posted documents are immutable.
- Corrections use credit memos and reversals.
- Ledgers are the source of truth.
- Posting groups drive accounting.
- Inventory uses item ledger and value entry concepts.

The future goal is increasing BC compatibility without copying unnecessary complexity that does not serve the pilot or target client workflows.

## 13. Known Intentional Deviations From BC

BIWMS may simplify some Business Central flows during the pilot stage.

Deferred or simplified advanced features:

- MRP.
- Capacity planning.
- Advanced costing methods.
- Landed cost and item charge allocation.
- Multi-company consolidation.
- Full SaaS tenant billing.
- Advanced dimensions.
- Advanced warehouse scanning.

Any deviation should be documented with the reason, current behavior, and future plan.

## 14. Future Roadmap

Phase 1:

- Pilot deployment.
- Client onboarding.
- Bug fixes.
- Usability improvements.

Phase 2:

- Advanced reporting.
- Performance optimization.
- Better onboarding and setup wizards.

Phase 3:

- Advanced manufacturing.
- MRP.
- Capacity planning.
- Fixed assets.
- Service management.

Phase 4:

- SaaS multi-tenancy.
- Subscription billing.
- Tenant self-service.
- API integrations.

Phase 5:

- BI/analytics.
- Mobile warehouse/scanning.
- Forecasting and AI-assisted insights.

## 15. Operational Commands

BIWMS maintenance and security commands:

```bash
php artisan biwms:security-audit
php artisan biwms:permissions-cleanup --dry-run
php artisan biwms:health-check
php artisan biwms:pilot-check
php artisan biwms:inventory-reconcile --details
php artisan biwms:finance-reconcile --details
```

These commands are diagnostic and operational guardrails. Cleanup commands must remain non-destructive by default.

## 16. Non-Negotiable Engineering Principles

- Posted documents are immutable.
- Every financial event creates ledger entries.
- Every inventory movement creates item ledger entries.
- Balances are derived from ledgers.
- Security is enforced by default.
- Approval workflow protects critical operations.
- Password confirmation protects destructive and sensitive actions.
- Audit logging records critical actions.
- Report commands diagnose before repair.
- Tests must protect business correctness.
