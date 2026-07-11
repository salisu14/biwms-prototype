# HR Workforce Scheduling and Rostering

BIWMS Workforce Scheduling Phase 1 adds controlled roster planning on top of the existing attendance schedule hierarchy. It does not replace raw attendance events, attendance daily summaries, attendance review, or payroll review.

## Architecture

Workforce scheduling uses these primary records:

- `WorkforceRosterPeriod`: planning window for a department, work center, business, or whole workforce.
- `WorkforceRosterAssignment`: the dated employee shift that attendance should use after the roster is published.
- `WorkforceRotationTemplate` and `WorkforceRotationTemplateDay`: reusable shift/rest-day cycles.
- `WorkforceRotationAssignment`: employee assignment to a rotation template.
- `WorkforceRosterRole` and `WorkforceStaffingRequirement`: coverage targets and critical-role checks.
- `EmployeeWorkAvailability`: employee availability, unavailability, preferences, and confidential constraints.
- `WorkforceShiftSwapRequest` and `WorkforceShiftReplacement`: controlled changes after publishing.
- `WorkforceRosterHistory`: immutable operational history for generation, publishing, replacement, and swap events.

The existing attendance objects remain responsible for attendance truth:

- `EmployeeAttendanceEvent` remains the immutable raw event source.
- `EmployeeAttendanceDay` remains the calculated daily attendance summary.
- `AttendanceLedgerEntry` remains the attendance review/payroll boundary record.
- Payroll review decides payroll impact; rosters do not create earning or deduction lines.

## Schedule Resolution Priority

Attendance calculation resolves the expected schedule in this order:

1. Published workforce roster assignment for the employee/date.
2. Active employee work schedule assignment.
3. Default shift/work-center calendar where available.
4. No schedule.

When a workforce roster assignment is used, `employee_attendance_days` stores:

- `workforce_roster_assignment_id`
- `schedule_source`
- `schedule_version`

Locked attendance summaries are not silently recalculated.

## Roster Flow

Recommended Phase 1 flow:

1. Configure shifts and existing work schedule assignments.
2. Configure roster roles and staffing requirements.
3. Configure rotation templates and rotation assignments.
4. Create a roster period.
5. Preview generation and review conflicts.
6. Generate draft assignments.
7. Resolve blocking conflicts.
8. Publish the roster.
9. Use controlled swap/replacement workflows for changes.
10. Run reconciliation before payroll review.

Publishing uses database transactions and row locks. Re-running publish for an already-published roster is idempotent and does not duplicate effects.

## Leave, Availability, And Coverage

Roster validation checks:

- inactive employees or inactive shifts;
- approved full-day leave;
- approved unavailability;
- overlapping active assignments;
- configured minimum rest rules;
- critical staffing coverage.

Warnings can be acknowledged during publishing. Blocking conflicts must be corrected before publishing.

## Payroll Boundary

Workforce scheduling may forecast overtime and flag roster risks, but it does not:

- approve overtime;
- create payroll lines;
- create deductions;
- post payroll;
- mutate attendance ledger payroll impact automatically.

Approved overtime, unpaid absence, lateness, and early departure continue through attendance review and controlled payroll review.

## Security

Standard permission names use `hr.resource.action`, including:

- `hr.workforce_roster_period.generate`
- `hr.workforce_roster_period.publish`
- `hr.workforce_roster_assignment.cancel`
- `hr.workforce_roster_assignment.replace`
- `hr.employee_work_availability.approve`
- `hr.shift_swap_request.approve`
- `hr.shift_replacement.approve`
- `hr.workforce_coverage_report.view`
- `hr.my_roster.view`
- `hr.my_availability.create`
- `hr.my_shift_swap.create`

Strict Filament authorization remains enabled. Employee self-service access is limited to the employee's own roster, availability, and shift swap records unless HR permissions grant wider access.

## Reconciliation

Run the report-only workforce roster reconcile command:

```bash
php artisan biwms:workforce-roster-reconcile --details
```

Export JSON when needed:

```bash
php artisan biwms:workforce-roster-reconcile --details --export=storage/app/reports/workforce-roster-reconcile.json
```

The command reports:

- inactive employees or shifts on published assignments;
- overlapping published assignments;
- assignments outside period dates;
- published periods without assignments;
- missing critical coverage;
- replacement link issues;
- attendance summaries using inactive roster assignments;
- published rosters without attendance trace;
- overlapping primary rotation assignments.

It never repairs or mutates data.
