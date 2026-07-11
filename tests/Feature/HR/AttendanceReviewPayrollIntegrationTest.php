<?php

declare(strict_types=1);

use App\Enums\CalculationMethod;
use App\Enums\PayCodeType;
use App\Enums\PayrollPeriodStatus;
use App\Enums\PayrollStatus;
use App\Models\AttendancePayrollReviewBatch;
use App\Models\AttendancePayrollReviewBatchLine;
use App\Models\AttendancePayrollRule;
use App\Models\AttendanceReviewItem;
use App\Models\AttendanceReviewPeriod;
use App\Models\AuditTrail;
use App\Models\ChartOfAccount;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAttendanceDay;
use App\Models\EmployeeAttendanceEvent;
use App\Models\OvertimeApproval;
use App\Models\PayCode;
use App\Models\PayrollDocument;
use App\Models\PayrollLine;
use App\Models\PayrollPeriod;
use App\Models\User;
use App\Services\Hr\AttendanceCalculationService;
use App\Services\Hr\AttendanceExceptionReviewService;
use App\Services\Hr\AttendancePayrollPostingService;
use App\Services\Hr\AttendancePayrollReviewBatchService;
use App\Services\Hr\AttendanceReviewPeriodService;
use Database\Seeders\PermissionsTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(PermissionsTableSeeder::class);
});

it('creates review periods transactionally and rejects overlapping periods', function (): void {
    $user = User::factory()->create();
    $service = app(AttendanceReviewPeriodService::class);

    $period = $service->create([
        'code' => 'ATT-2026-07-A',
        'date_from' => '2026-07-01',
        'date_to' => '2026-07-15',
    ], $user);

    expect($period->status)->toBe(AttendanceReviewPeriod::STATUS_OPEN)
        ->and(AuditTrail::query()->where('action', 'period_created')->exists())->toBeTrue()
        ->and(fn () => $service->create([
            'code' => 'ATT-2026-07-B',
            'date_from' => '2026-07-10',
            'date_to' => '2026-07-31',
        ], $user))->toThrow(RuntimeException::class, 'overlaps');
});

it('generates deterministic exceptions, preserves resolved decisions, and blocks approval while critical items are unresolved', function (): void {
    $user = User::factory()->create();
    $employee = Employee::factory()->create();
    $day = attendanceReviewDay($employee, [
        'attendance_date' => '2026-07-10',
        'missing_clock_out' => true,
        'status' => EmployeeAttendanceDay::STATUS_MISSING_CLOCK_OUT,
    ]);
    $period = attendanceReviewPeriod('ATT-EXC-1', '2026-07-01', '2026-07-31');

    $exceptionService = app(AttendanceExceptionReviewService::class);
    $first = $exceptionService->generateForPeriod($period);
    $second = $exceptionService->generateForPeriod($period);

    expect($first)->toHaveCount(1)
        ->and($second)->toHaveCount(1)
        ->and(AttendanceReviewItem::query()->count())->toBe(1);

    expect(fn () => app(AttendanceReviewPeriodService::class)->approve($period, $user))
        ->toThrow(RuntimeException::class, 'unresolved blocking');

    $item = AttendanceReviewItem::query()->firstOrFail();
    $exceptionService->resolve($item, $user, 'corrected', 'Manager confirmed manual correction.');
    $exceptionService->generateForPeriod($period);

    expect($item->fresh()->review_status)->toBe(AttendanceReviewItem::STATUS_RESOLVED)
        ->and(app(AttendanceReviewPeriodService::class)->approve($period, $user)->status)->toBe(AttendanceReviewPeriod::STATUS_APPROVED);

    expect($day->fresh()->missing_clock_out)->toBeTrue();
});

it('locks attendance days, prevents silent recalculation, creates post-lock exceptions, and supports controlled reopen', function (): void {
    $user = User::factory()->create();
    $employee = Employee::factory()->create();
    $day = attendanceReviewDay($employee, [
        'attendance_date' => '2026-07-12',
        'status' => EmployeeAttendanceDay::STATUS_PRESENT,
        'worked_minutes' => 480,
    ]);
    $period = attendanceReviewPeriod('ATT-LOCK-1', '2026-07-01', '2026-07-31', AttendanceReviewPeriod::STATUS_APPROVED);

    $periodService = app(AttendanceReviewPeriodService::class);
    $locked = $periodService->lock($period, $user);

    EmployeeAttendanceEvent::query()->create([
        'employee_id' => $employee->id,
        'event_type' => EmployeeAttendanceEvent::TYPE_CLOCK_IN,
        'occurred_at' => '2026-07-12 09:10:00',
        'attendance_date' => '2026-07-12',
        'source' => 'late_import',
    ]);

    $recalculated = app(AttendanceCalculationService::class)->recalculate($employee, '2026-07-12');

    expect($locked->status)->toBe(AttendanceReviewPeriod::STATUS_LOCKED)
        ->and($recalculated->worked_minutes)->toBe(480)
        ->and(AttendanceReviewItem::query()->where('issue_type', AttendanceReviewItem::ISSUE_MANUAL_OVERRIDE)->exists())->toBeTrue();

    $reopened = $periodService->reopen($locked, $user, 'Late device upload reviewed.');

    expect($reopened->status)->toBe(AttendanceReviewPeriod::STATUS_REOPENED)
        ->and($day->fresh()->locked_by_review_period_id)->toBeNull();
});

it('generates idempotent payroll review batches for approved overtime and resolved unpaid absence only', function (): void {
    $user = User::factory()->create();
    [$period, $payrollPeriod] = approvedAttendancePeriodWithPayrollPeriod();
    $employee = Employee::factory()->create();
    attendanceReviewDay($employee, [
        'attendance_date' => '2026-07-10',
        'overtime_minutes' => 120,
        'status' => EmployeeAttendanceDay::STATUS_PRESENT,
    ]);
    attendanceReviewDay($employee, [
        'attendance_date' => '2026-07-11',
        'status' => 'unpaid_absence',
        'scheduled_start_at' => '2026-07-11 09:00:00',
        'scheduled_end_at' => '2026-07-11 17:00:00',
    ]);
    attendanceReviewDay($employee, [
        'attendance_date' => '2026-07-12',
        'missing_clock_out' => true,
        'status' => EmployeeAttendanceDay::STATUS_MISSING_CLOCK_OUT,
    ]);
    OvertimeApproval::query()->create([
        'employee_id' => $employee->id,
        'attendance_date' => '2026-07-10',
        'requested_minutes' => 120,
        'approved_minutes' => 90,
        'status' => OvertimeApproval::STATUS_APPROVED,
        'requested_by' => $user->id,
        'approved_by' => $user->id,
        'approved_at' => now(),
    ]);
    EmployeeAttendanceDay::query()
        ->where('employee_id', $employee->id)
        ->whereDate('attendance_date', '2026-07-10')
        ->update([
            'status' => EmployeeAttendanceDay::STATUS_PRESENT,
            'overtime_minutes' => 120,
            'payroll_review_required' => true,
        ]);
    attendancePayrollRules();

    $exceptionService = app(AttendanceExceptionReviewService::class);
    $exceptionService->generateForPeriod($period);
    AttendanceReviewItem::query()
        ->whereIn('issue_type', [AttendanceReviewItem::ISSUE_APPROVED_OVERTIME, AttendanceReviewItem::ISSUE_UNPAID_ABSENCE])
        ->get()
        ->each(fn (AttendanceReviewItem $item) => $exceptionService->resolve($item, $user, 'payroll_adjustment'));

    $service = app(AttendancePayrollReviewBatchService::class);
    $batch = $service->generate($period, $payrollPeriod, $user);
    $again = $service->generate($period, $payrollPeriod, $user);

    expect($batch->id)->toBe($again->id)
        ->and($batch->lines)->toHaveCount(2)
        ->and($batch->lines->pluck('line_type')->all())->toContain(AttendanceReviewItem::ISSUE_APPROVED_OVERTIME, AttendanceReviewItem::ISSUE_UNPAID_ABSENCE)
        ->and($batch->lines->pluck('line_type')->all())->not->toContain(AttendanceReviewItem::ISSUE_MISSING_CLOCK_OUT)
        ->and((float) $batch->lines->firstWhere('line_type', AttendanceReviewItem::ISSUE_APPROVED_OVERTIME)->suggested_amount)->toBe(7500.0)
        ->and((float) $batch->lines->firstWhere('line_type', AttendanceReviewItem::ISSUE_UNPAID_ABSENCE)->suggested_amount)->toBe(12000.0);
});

it('requires override permission and reason for payroll adjustment line overrides', function (): void {
    $user = User::factory()->create();
    $line = AttendancePayrollReviewBatchLine::query()->create([
        'attendance_payroll_review_batch_id' => AttendancePayrollReviewBatch::query()->create([
            'attendance_review_period_id' => attendanceReviewPeriod('ATT-OVR-1', '2026-07-01', '2026-07-31')->id,
            'batch_number' => 'ATB-OVR-1',
        ])->id,
        'employee_id' => Employee::factory()->create()->id,
        'line_type' => AttendanceReviewItem::ISSUE_APPROVED_OVERTIME,
        'quantity_minutes' => 60,
        'suggested_amount' => 1000,
    ]);

    expect(fn () => app(AttendancePayrollReviewBatchService::class)->overrideLineAmount($line, 1500, $user, 'Approved premium rate.'))
        ->toThrow(RuntimeException::class, 'not authorized');

    $user->givePermissionTo('payroll.attendance_adjustment.override');

    expect(fn () => app(AttendancePayrollReviewBatchService::class)->overrideLineAmount($line, 1500, $user, ''))
        ->toThrow(RuntimeException::class, 'reason');

    $updated = app(AttendancePayrollReviewBatchService::class)->overrideLineAmount($line, 1500, $user, 'Approved premium rate.');

    expect((float) $updated->approved_amount)->toBe(1500.0)
        ->and($updated->status)->toBe(AttendancePayrollReviewBatchLine::STATUS_APPROVED);
});

it('posts approved attendance payroll batches explicitly and prevents duplicate posting', function (): void {
    $user = User::factory()->create();
    [$period, $payrollPeriod] = approvedAttendancePeriodWithPayrollPeriod();
    $employee = Employee::factory()->create();
    $payCode = PayCode::query()->create([
        'code' => 'OT-POST',
        'name' => 'Overtime Posting',
        'type' => PayCodeType::EARNING,
        'calculation_method' => CalculationMethod::HOURLY,
        'gl_account_id' => ChartOfAccount::factory()->create()->id,
    ]);
    $rule = AttendancePayrollRule::query()->create([
        'code' => 'OT-POST',
        'name' => 'Overtime Posting',
        'attendance_issue_type' => AttendanceReviewItem::ISSUE_APPROVED_OVERTIME,
        'impact_type' => AttendancePayrollRule::IMPACT_EARNING,
        'calculation_method' => 'hourly_rate',
        'rate' => 1000,
        'earning_component_id' => $payCode->id,
        'effective_from' => '2026-01-01',
        'is_active' => true,
    ]);
    $batch = AttendancePayrollReviewBatch::query()->create([
        'attendance_review_period_id' => $period->id,
        'payroll_period_id' => $payrollPeriod->id,
        'batch_number' => 'ATB-POST-1',
        'status' => AttendancePayrollReviewBatch::STATUS_APPROVED,
    ]);
    $line = AttendancePayrollReviewBatchLine::query()->create([
        'attendance_payroll_review_batch_id' => $batch->id,
        'employee_id' => $employee->id,
        'attendance_payroll_rule_id' => $rule->id,
        'line_type' => AttendanceReviewItem::ISSUE_APPROVED_OVERTIME,
        'quantity_minutes' => 60,
        'rate' => 1000,
        'suggested_amount' => 1000,
        'status' => AttendancePayrollReviewBatchLine::STATUS_APPROVED,
    ]);
    $document = PayrollDocument::query()->create([
        'document_number' => 'PAY-ATT-001',
        'payroll_period_id' => $payrollPeriod->id,
        'period_start' => '2026-07-01',
        'period_end' => '2026-07-31',
        'status' => PayrollStatus::OPEN,
    ]);

    $result = app(AttendancePayrollPostingService::class)->post($batch, $document, $user);

    expect($result['posted_lines'])->toBe(1)
        ->and(PayrollLine::query()->where('attendance_payroll_review_batch_line_id', $line->id)->count())->toBe(1)
        ->and($line->fresh()->status)->toBe(AttendancePayrollReviewBatchLine::STATUS_POSTED)
        ->and(fn () => app(AttendancePayrollPostingService::class)->post($batch->fresh(), $document, $user))->toThrow(RuntimeException::class);
});

it('allows manager-scoped review item visibility while denying cross-department access', function (): void {
    $managerEmployee = Employee::factory()->create();
    $managedEmployee = Employee::factory()->create();
    $outsideEmployee = Employee::factory()->create();
    $managerUser = User::factory()->create(['employee_id' => $managerEmployee->id]);
    $managerUser->givePermissionTo('hr.attendance_review_item.view_team');
    $department = Department::query()->create([
        'department_code' => 'ATT-MGR',
        'name' => 'Attendance Managed',
        'manager_id' => $managerEmployee->id,
    ]);
    $managedEmployee->forceFill(['department_id' => $department->id, 'department_code' => $department->department_code])->saveQuietly();
    $managedItem = attendanceReviewItem($managedEmployee);
    $outsideItem = attendanceReviewItem($outsideEmployee);

    expect(Gate::forUser($managerUser)->allows('view', $managedItem))->toBeTrue()
        ->and(Gate::forUser($managerUser)->allows('view', $outsideItem))->toBeFalse();
});

function attendanceReviewDay(Employee $employee, array $attributes = []): EmployeeAttendanceDay
{
    return EmployeeAttendanceDay::query()->create([
        'employee_id' => $employee->id,
        'attendance_date' => $attributes['attendance_date'] ?? '2026-07-10',
        'scheduled_start_at' => $attributes['scheduled_start_at'] ?? '2026-07-10 09:00:00',
        'scheduled_end_at' => $attributes['scheduled_end_at'] ?? '2026-07-10 17:00:00',
        'worked_minutes' => $attributes['worked_minutes'] ?? 0,
        'late_minutes' => $attributes['late_minutes'] ?? 0,
        'early_departure_minutes' => $attributes['early_departure_minutes'] ?? 0,
        'overtime_minutes' => $attributes['overtime_minutes'] ?? 0,
        'status' => $attributes['status'] ?? EmployeeAttendanceDay::STATUS_ABSENT,
        'missing_clock_out' => $attributes['missing_clock_out'] ?? false,
        'payroll_review_required' => $attributes['payroll_review_required'] ?? true,
        'calculation_notes' => $attributes['calculation_notes'] ?? [],
    ]);
}

function attendanceReviewPeriod(string $code, string $from, string $to, string $status = AttendanceReviewPeriod::STATUS_UNDER_REVIEW): AttendanceReviewPeriod
{
    return AttendanceReviewPeriod::query()->create([
        'code' => $code,
        'name' => $code,
        'date_from' => $from,
        'date_to' => $to,
        'status' => $status,
    ]);
}

/**
 * @return array{0: AttendanceReviewPeriod, 1: PayrollPeriod}
 */
function approvedAttendancePeriodWithPayrollPeriod(): array
{
    return [
        attendanceReviewPeriod('ATT-PAY-'.str()->random(4), '2026-07-01', '2026-07-31', AttendanceReviewPeriod::STATUS_APPROVED),
        PayrollPeriod::query()->create([
            'start_date' => '2026-07-01',
            'end_date' => '2026-07-31',
            'payment_date' => '2026-08-05',
            'status' => PayrollPeriodStatus::OPEN,
        ]),
    ];
}

function attendancePayrollRules(): void
{
    $overtime = PayCode::query()->create([
        'code' => 'OT-ATT',
        'name' => 'Attendance Overtime',
        'type' => PayCodeType::EARNING,
        'calculation_method' => CalculationMethod::HOURLY,
        'gl_account_id' => ChartOfAccount::factory()->create()->id,
    ]);
    $absence = PayCode::query()->create([
        'code' => 'UA-ATT',
        'name' => 'Unpaid Absence',
        'type' => PayCodeType::DEDUCTION,
        'calculation_method' => CalculationMethod::HOURLY,
        'gl_account_id' => ChartOfAccount::factory()->create()->id,
    ]);

    AttendancePayrollRule::query()->create([
        'code' => 'ATT-OT-HR',
        'name' => 'Approved Overtime Hourly',
        'attendance_issue_type' => AttendanceReviewItem::ISSUE_APPROVED_OVERTIME,
        'impact_type' => AttendancePayrollRule::IMPACT_EARNING,
        'calculation_method' => 'hourly_rate',
        'rate' => 5000,
        'earning_component_id' => $overtime->id,
        'effective_from' => '2026-01-01',
        'is_active' => true,
    ]);

    AttendancePayrollRule::query()->create([
        'code' => 'ATT-UA-DAY',
        'name' => 'Unpaid Absence Daily',
        'attendance_issue_type' => AttendanceReviewItem::ISSUE_UNPAID_ABSENCE,
        'impact_type' => AttendancePayrollRule::IMPACT_DEDUCTION,
        'calculation_method' => 'daily_rate',
        'rate' => 12000,
        'deduction_component_id' => $absence->id,
        'effective_from' => '2026-01-01',
        'is_active' => true,
    ]);
}

function attendanceReviewItem(Employee $employee): AttendanceReviewItem
{
    return AttendanceReviewItem::query()->create([
        'attendance_review_period_id' => attendanceReviewPeriod('ATT-AUTH-'.str()->random(4), '2026-07-01', '2026-07-31')->id,
        'employee_attendance_day_id' => attendanceReviewDay($employee)->id,
        'employee_id' => $employee->id,
        'attendance_date' => '2026-07-10',
        'issue_type' => AttendanceReviewItem::ISSUE_LATE,
        'severity' => 'warning',
        'review_status' => AttendanceReviewItem::STATUS_PENDING,
        'source_hash' => str()->random(40),
    ]);
}
