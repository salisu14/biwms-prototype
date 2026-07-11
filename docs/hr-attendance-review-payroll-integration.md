# HR Attendance Review, Period Locking, and Controlled Payroll Integration

## Purpose

This phase adds a controlled bridge between Time & Attendance and Payroll.

Attendance remains review-driven. Attendance summaries, exceptions, and overtime facts can suggest payroll adjustments, but they do not automatically create earnings or deductions. Payroll officers must explicitly approve and post attendance payroll review batches into payroll documents.

## Data Flow

1. `EmployeeAttendanceEvent` stores immutable raw clocking facts.
2. `EmployeeAttendanceDay` stores the calculated daily attendance summary.
3. `AttendanceReviewPeriod` groups days into a review window.
4. `AttendanceReviewItem` stores review exceptions generated from attendance days, corrections, and overtime approvals.
5. Approved or locked review periods can generate `AttendancePayrollReviewBatch` records.
6. `AttendancePayrollReviewBatchLine` stores proposed attendance payroll adjustments.
7. `AttendancePayrollPostingService` explicitly posts approved batch lines into `PayrollLine`.

`AttendanceLedgerEntry` remains an attendance/payroll-review ledger. It does not automatically change pay.

## Review Period Lifecycle

Review periods use this lifecycle:

```text
draft/open -> under_review -> approved -> locked -> exported
```

Periods may be reopened with a reason when late corrections or late device uploads require controlled review.

Overlap rules:

- An employee/date should belong to one applicable active attendance review period for a business.
- New period creation rejects overlapping date ranges for the same business scope.

## Exception Review

Exception generation is deterministic and idempotent. Re-running generation for the same facts does not duplicate items.

Resolved or waived decisions are preserved when the source facts have not changed. If source facts change, the item returns to pending review.

Blocking critical exceptions include missing clock-out, absence, unpaid absence, and post-lock manual override exceptions.

Missing clock-out never becomes an automatic deduction. It must be corrected, waived, or reviewed before any payroll impact is considered.

## Locking

Locking a review period stores a snapshot hash on each covered `EmployeeAttendanceDay`.

Locked attendance days are not silently recalculated. If a late event appears after the lock, the recalculation service leaves the locked summary unchanged and creates a critical post-lock review exception.

Reopening a period unlocks the days and cancels draft, pending, or approved attendance payroll batches for that period.

## Payroll Review Batches

Payroll batches can be generated only from approved or locked attendance review periods.

Supported Phase 2 adjustment sources:

- resolved or waived approved overtime;
- resolved or waived unpaid absence.

Excluded from automatic payroll batches:

- missing clock-out;
- unapproved overtime;
- lateness and early departure unless a future payroll rule explicitly supports them;
- raw attendance events.

Batch generation is idempotent while the batch remains draft or pending. Approved and posted batches cannot be regenerated.

## Payroll Posting

`AttendancePayrollPostingService` posts approved attendance payroll review batch lines into an editable payroll document.

Posting rules:

- batch must be approved;
- payroll document must be editable;
- payroll period must be open;
- each batch line posts at most once;
- posted batch lines link to the created `PayrollLine`;
- no raw attendance event or attendance day is modified during payroll posting;
- no base salary or employee compensation record is modified.

Reversal creates a linked negative payroll line and marks the attendance batch line reversed.

## Permissions

Attendance review permissions use the `hr` module:

- `hr.attendance_review_period.*`
- `hr.attendance_review_item.*`

Payroll integration permissions use the `payroll` module:

- `payroll.attendance_batch.*`
- `payroll.attendance_adjustment.*`
- `payroll.attendance_rule.*`

Sensitive actions such as reopen, post, reverse, and override should require password confirmation through the global Filament sensitive-action layer.

## Reconciliation

Run:

```bash
php artisan biwms:attendance-reconcile --details
```

The command reports:

- locked attendance summary drift;
- attendance events created after lock;
- unresolved critical exceptions in approved or locked periods;
- approved periods without payroll batches;
- duplicate active attendance payroll batches;
- approved overtime omitted from a batch;
- unpaid absence batch lines linked to unresolved items;
- posted batch lines without linked payroll lines.

The command is diagnostic only. It does not repair attendance or payroll data.
