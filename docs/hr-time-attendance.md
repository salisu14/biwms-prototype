# HR Time & Attendance

BIWMS Time & Attendance builds on the existing attendance ledger instead of replacing it.

## Architecture

- `employee_attendance_events` stores immutable raw clock events.
- `employee_attendance_days` stores recalculated daily attendance summaries.
- `attendance_ledger_entries` remains the existing payroll-facing attendance ledger.
- Payroll is not changed automatically by attendance. Attendance only raises payroll review flags.

## Setup Order

1. Create attendance locations.
2. Create attendance devices where kiosk/mobile/biometric devices are used.
3. Create employee shifts with grace, break, overnight, and overtime settings.
4. Assign employee work schedules.
5. Issue active employee ID cards.
6. Use Clock In / Clock Out or My Attendance to record events.
7. Review Attendance Register, Corrections, Overtime, and Reports.

## QR Clocking

Employee ID cards already contain signed QR payloads. The attendance clock page verifies the card through the ID-card service, records a raw event, and recalculates the daily summary.

The raw event stores a token hash for traceability. It does not store the raw QR token or expose sensitive HR/payroll data.

## Corrections

Raw attendance events are immutable. Corrections are submitted as `attendance_correction_requests`. Approval creates correction events and recalculates the daily attendance summary.

Do not edit or delete raw events to fix attendance mistakes.

## Leave Integration

Approved, posted, or completed leave requests are considered during daily attendance calculation. If an employee is on leave and has no clock event, the day is marked `on_leave`.

Leave changes should be followed by attendance recalculation for the affected employee/date range.

## Overtime

The shift determines calculated overtime. Approved overtime records are compared to calculated overtime. If calculated overtime exceeds approval, the day is flagged for payroll review.

## Payroll Boundary

Attendance does not directly mutate payroll documents, payslips, or posted payroll entries. It only updates attendance summaries and the existing attendance ledger used by payroll calculations.

Payroll teams should review attendance flags before running payroll.

## Reconciliation

Run the report-only diagnostic command:

```bash
php artisan biwms:attendance-reconcile --details
```

Optional JSON export:

```bash
php artisan biwms:attendance-reconcile --details --export=storage/app/reports/attendance-reconcile.json
```

The command reports:

- raw events without daily summaries
- missing clock-out days
- duplicate rapid scans
- overlapping shift assignments
- approved leave marked absent
- invalid card verification attempts
- overtime approval mismatches
- payroll review flag mismatches

It does not repair or mutate data.

## Security Notes

- Strict authorization remains enabled.
- Clocking requires `hr.attendance_clock.use`.
- Attendance setup and reports require HR attendance permissions.
- Raw QR tokens, passwords, recovery codes, and payroll-sensitive data must never be logged.
