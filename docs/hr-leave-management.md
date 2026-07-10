# HR Leave Management

BIWMS Leave Management is a Phase 1 HR module for leave setup, requests, approvals, balances, and diagnostics.

## Architecture

- `leave_types` define configurable leave categories such as annual, sick, maternity, paternity, compassionate, study, and unpaid leave.
- `leave_policies` and `leave_policy_rules` define entitlement rules without hardcoding statutory values in application logic.
- `employee_leave_entitlements` store entitlement setup by employee, leave type, and leave year.
- `leave_requests` track employee requests and approval status.
- `employee_leave_ledger_entries` are the source of truth for balances.
- `leave_holidays` provide HR public-holiday exclusions for leave duration calculations.

## Request Flow

Phase 1 status flow:

```text
Draft -> Submitted -> Manager Approved -> Approved -> Posted
```

Rejected and cancelled requests do not reduce leave balance. Cancelling a posted request creates a reversal ledger entry.

## Balance Ledger Principle

Leave balance is derived from ledger sums:

- positive entries increase balance.
- negative entries reduce balance.
- approved leave posts once as `approved_leave`.
- cancellations create `reversal`.
- adjustments must be audited and should not overwrite history.

## Duration Calculation

`LeaveDurationService` calculates leave quantity consistently for UI/service/posting flows. It excludes weekends and active HR leave holidays, supports half-day requests, validates date order, and blocks overlaps with submitted or approved requests.

## Payroll Boundary

Paid leave does not alter payroll automatically. Unpaid leave is flagged for payroll review with `payroll_review_required`. Future payroll integration may convert approved unpaid leave into payroll deduction lines after payroll rules are explicitly designed and tested.

## Security And Privacy

- Attachments use private storage.
- Employees may access only their own leave requests and balances.
- Department managers are limited to their team scope.
- HR access is permission-based.
- Approval, rejection, cancellation, posting, reversal, and adjustments are audited.
- Shared calendar views do not expose medical details or attachment contents.

## Reconciliation

Run the report-only diagnostic command:

```bash
php artisan biwms:leave-reconcile --details
```

Export JSON:

```bash
php artisan biwms:leave-reconcile --details --export=storage/app/reports/leave-reconcile.json
```

The command reports approved requests without ledger entries, duplicate postings, ledger quantity mismatches, negative balances, and overlapping approved leave. It does not repair or mutate data.

## Future Integration

Future phases may add advanced holiday calendars, shift/work schedules, accrual jobs, employee notifications, attendance integration, payroll deduction automation for unpaid leave, and richer calendar UI.
