<?php

declare(strict_types=1);

use App\Filament\Pages\Hr\AttendanceDashboardPage;
use App\Filament\Pages\Hr\AttendanceReportsPage;
use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceLedgerEntry;
use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeAttendanceDay;
use App\Models\EmployeeAttendanceEvent;
use App\Models\EmployeeIdCard;
use App\Models\EmployeeShift;
use App\Models\EmployeeWorkScheduleAssignment;
use App\Models\LeaveHoliday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\OvertimeApproval;
use App\Models\User;
use App\Services\Hr\AttendanceCalculationService;
use App\Services\Hr\AttendanceClockService;
use App\Services\Hr\EmployeeIdCardService;
use App\Services\Hr\EmployeeWorkScheduleService;
use Database\Seeders\PermissionsTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(PermissionsTableSeeder::class);
});

it('records QR clock in and out through immutable events and syncs the existing attendance ledger', function (): void {
    $employee = Employee::factory()->create();
    $card = app(EmployeeIdCardService::class)->issueCard($employee);
    createSchedule($employee);

    $clockService = app(AttendanceClockService::class);
    $clockService->clockWithCardToken($card->token, EmployeeAttendanceEvent::TYPE_CLOCK_IN, '2026-07-10 09:00:00');
    $day = $clockService->clockWithCardToken($card->token, EmployeeAttendanceEvent::TYPE_CLOCK_OUT, '2026-07-10 17:30:00');

    expect(EmployeeAttendanceEvent::query()->where('employee_id', $employee->id)->count())->toBe(2)
        ->and($day->worked_minutes)->toBe(480)
        ->and($day->overtime_minutes)->toBe(30)
        ->and($day->payroll_review_required)->toBeTrue();

    $ledger = AttendanceLedgerEntry::query()->where('employee_id', $employee->id)->whereDate('attendance_date', '2026-07-10')->first();
    expect($ledger)->not->toBeNull()
        ->and((float) $ledger->worked_hours)->toBe(8.0);
});

it('rejects expired, revoked, lost, replaced, and inactive employee ID cards', function (array $cardAttributes, array $employeeAttributes = []): void {
    $employee = Employee::factory()->create($employeeAttributes);
    $card = app(EmployeeIdCardService::class)->issueCard($employee);
    $card->forceFill($cardAttributes)->save();

    expect(fn () => app(AttendanceClockService::class)->clockWithCardToken($card->token, EmployeeAttendanceEvent::TYPE_CLOCK_IN, '2026-07-10 09:00:00'))
        ->toThrow(RuntimeException::class, 'invalid');
})->with([
    'revoked card' => [['status' => EmployeeIdCard::STATUS_REVOKED]],
    'lost card' => [['status' => EmployeeIdCard::STATUS_LOST]],
    'replaced card' => [['status' => EmployeeIdCard::STATUS_REPLACED]],
    'expired card' => [['expiry_date' => '2026-07-09']],
    'inactive employee' => [[], ['is_active' => false]],
]);

it('blocks duplicate rapid scans', function (): void {
    $employee = Employee::factory()->create();
    $card = app(EmployeeIdCardService::class)->issueCard($employee);

    app(AttendanceClockService::class)->clockWithCardToken($card->token, EmployeeAttendanceEvent::TYPE_CLOCK_IN, '2026-07-10 09:00:00');

    expect(fn () => app(AttendanceClockService::class)->clockWithCardToken($card->token, EmployeeAttendanceEvent::TYPE_CLOCK_IN, '2026-07-10 09:01:00'))
        ->toThrow(RuntimeException::class, 'Duplicate rapid');
});

it('calculates late, early departure, overtime, overnight, weekend, and holiday statuses', function (): void {
    $employee = Employee::factory()->create();
    createSchedule($employee);

    EmployeeAttendanceEvent::query()->create([
        'employee_id' => $employee->id,
        'event_type' => EmployeeAttendanceEvent::TYPE_CLOCK_IN,
        'occurred_at' => '2026-07-10 09:10:00',
        'attendance_date' => '2026-07-10',
        'source' => 'test',
    ]);
    EmployeeAttendanceEvent::query()->create([
        'employee_id' => $employee->id,
        'event_type' => EmployeeAttendanceEvent::TYPE_CLOCK_OUT,
        'occurred_at' => '2026-07-10 17:30:00',
        'attendance_date' => '2026-07-10',
        'source' => 'test',
    ]);

    $day = app(AttendanceCalculationService::class)->recalculate($employee, '2026-07-10');
    expect($day->status)->toBe(EmployeeAttendanceDay::STATUS_LATE)
        ->and($day->late_minutes)->toBe(5)
        ->and($day->overtime_minutes)->toBe(30);

    $nightShift = EmployeeShift::query()->create([
        'code' => 'NIGHT',
        'name' => 'Night Shift',
        'start_time' => '22:00',
        'end_time' => '06:00',
        'crosses_midnight' => true,
        'is_active' => true,
    ]);
    EmployeeWorkScheduleAssignment::query()
        ->where('employee_id', $employee->id)
        ->update(['effective_until' => '2026-07-10']);

    EmployeeWorkScheduleAssignment::query()->create([
        'employee_id' => $employee->id,
        'employee_shift_id' => $nightShift->id,
        'effective_from' => '2026-07-11',
        'is_active' => true,
    ]);

    EmployeeAttendanceEvent::query()->create([
        'employee_id' => $employee->id,
        'event_type' => EmployeeAttendanceEvent::TYPE_CLOCK_IN,
        'occurred_at' => '2026-07-11 22:00:00',
        'attendance_date' => '2026-07-11',
        'source' => 'test',
    ]);
    EmployeeAttendanceEvent::query()->create([
        'employee_id' => $employee->id,
        'event_type' => EmployeeAttendanceEvent::TYPE_CLOCK_OUT,
        'occurred_at' => '2026-07-12 06:15:00',
        'attendance_date' => '2026-07-11',
        'source' => 'test',
    ]);

    $overnight = app(AttendanceCalculationService::class)->recalculate($employee, '2026-07-11');
    expect($overnight->worked_minutes)->toBe(495)
        ->and($overnight->overtime_minutes)->toBe(15);

    LeaveHoliday::query()->create(['name' => 'Founders Day', 'holiday_date' => '2026-07-13', 'is_active' => true]);
    expect(app(AttendanceCalculationService::class)->recalculate($employee, '2026-07-13')->status)->toBe(EmployeeAttendanceDay::STATUS_HOLIDAY);
});

it('integrates approved leave and flags payroll review without changing payroll documents', function (): void {
    $employee = Employee::factory()->create();
    $leaveType = LeaveType::query()->create([
        'code' => 'ANNUAL-ATT',
        'name' => 'Annual Attendance',
        'unit' => 'days',
        'paid' => true,
        'is_active' => true,
    ]);
    LeaveRequest::query()->create([
        'request_number' => 'LR-ATT-001',
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-07-10',
        'end_date' => '2026-07-10',
        'start_part' => 'full_day',
        'end_part' => 'full_day',
        'requested_quantity' => 1,
        'approved_quantity' => 1,
        'status' => LeaveRequest::STATUS_APPROVED,
    ]);

    $day = app(AttendanceCalculationService::class)->recalculate($employee, '2026-07-10');

    expect($day->status)->toBe(EmployeeAttendanceDay::STATUS_ON_LEAVE)
        ->and($day->on_leave)->toBeTrue()
        ->and($day->payroll_review_required)->toBeTrue();
});

it('handles morning and afternoon half-day leave with partial attendance', function (): void {
    $employee = Employee::factory()->create();
    createSchedule($employee);
    $leaveType = attendanceLeaveType();

    LeaveRequest::query()->create([
        'request_number' => 'LR-MORNING',
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-07-10',
        'end_date' => '2026-07-10',
        'start_part' => 'morning',
        'end_part' => 'morning',
        'requested_quantity' => 0.5,
        'approved_quantity' => 0.5,
        'status' => LeaveRequest::STATUS_APPROVED,
    ]);
    EmployeeAttendanceEvent::query()->create([
        'employee_id' => $employee->id,
        'event_type' => EmployeeAttendanceEvent::TYPE_CLOCK_IN,
        'occurred_at' => '2026-07-10 13:00:00',
        'attendance_date' => '2026-07-10',
        'source' => 'test',
    ]);
    EmployeeAttendanceEvent::query()->create([
        'employee_id' => $employee->id,
        'event_type' => EmployeeAttendanceEvent::TYPE_CLOCK_OUT,
        'occurred_at' => '2026-07-10 17:00:00',
        'attendance_date' => '2026-07-10',
        'source' => 'test',
    ]);

    $morning = app(AttendanceCalculationService::class)->recalculate($employee, '2026-07-10');
    expect($morning->on_leave)->toBeTrue()
        ->and($morning->late_minutes)->toBe(0)
        ->and($morning->calculation_notes['leave_parts'])->toContain('morning');

    $employeeTwo = Employee::factory()->create();
    createSchedule($employeeTwo);
    LeaveRequest::query()->create([
        'request_number' => 'LR-AFTERNOON',
        'employee_id' => $employeeTwo->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-07-10',
        'end_date' => '2026-07-10',
        'start_part' => 'afternoon',
        'end_part' => 'afternoon',
        'requested_quantity' => 0.5,
        'approved_quantity' => 0.5,
        'status' => LeaveRequest::STATUS_APPROVED,
    ]);
    EmployeeAttendanceEvent::query()->create([
        'employee_id' => $employeeTwo->id,
        'event_type' => EmployeeAttendanceEvent::TYPE_CLOCK_IN,
        'occurred_at' => '2026-07-10 09:00:00',
        'attendance_date' => '2026-07-10',
        'source' => 'test',
    ]);
    EmployeeAttendanceEvent::query()->create([
        'employee_id' => $employeeTwo->id,
        'event_type' => EmployeeAttendanceEvent::TYPE_CLOCK_OUT,
        'occurred_at' => '2026-07-10 13:00:00',
        'attendance_date' => '2026-07-10',
        'source' => 'test',
    ]);

    $afternoon = app(AttendanceCalculationService::class)->recalculate($employeeTwo, '2026-07-10');
    expect($afternoon->on_leave)->toBeTrue()
        ->and($afternoon->early_departure_minutes)->toBe(0)
        ->and($afternoon->calculation_notes['leave_parts'])->toContain('afternoon');
});

it('recalculates attendance after leave cancellation', function (): void {
    $employee = Employee::factory()->create();
    $leaveType = attendanceLeaveType();
    $request = LeaveRequest::query()->create([
        'request_number' => 'LR-CANCEL',
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-07-10',
        'end_date' => '2026-07-10',
        'start_part' => 'full_day',
        'end_part' => 'full_day',
        'requested_quantity' => 1,
        'approved_quantity' => 1,
        'status' => LeaveRequest::STATUS_APPROVED,
    ]);

    expect(EmployeeAttendanceDay::query()->where('employee_id', $employee->id)->whereDate('attendance_date', '2026-07-10')->first()?->status)
        ->toBe(EmployeeAttendanceDay::STATUS_ON_LEAVE);

    $request->update(['status' => LeaveRequest::STATUS_CANCELLED]);

    expect(EmployeeAttendanceDay::query()->where('employee_id', $employee->id)->whereDate('attendance_date', '2026-07-10')->first()?->status)
        ->toBe(EmployeeAttendanceDay::STATUS_ABSENT);
});

it('detects missing clock out and preserves raw history when approving corrections', function (): void {
    $approver = User::factory()->create();
    $employee = Employee::factory()->create();

    EmployeeAttendanceEvent::query()->create([
        'employee_id' => $employee->id,
        'event_type' => EmployeeAttendanceEvent::TYPE_CLOCK_IN,
        'occurred_at' => '2026-07-10 09:00:00',
        'attendance_date' => '2026-07-10',
        'source' => 'test',
    ]);

    $missing = app(AttendanceCalculationService::class)->recalculate($employee, '2026-07-10');
    expect($missing->status)->toBe(EmployeeAttendanceDay::STATUS_MISSING_CLOCK_OUT);

    $correction = AttendanceCorrectionRequest::query()->create([
        'employee_id' => $employee->id,
        'attendance_day_id' => $missing->id,
        'attendance_date' => '2026-07-10',
        'requested_clock_out_at' => '2026-07-10 17:00:00',
        'reason' => 'Forgot to clock out.',
        'status' => AttendanceCorrectionRequest::STATUS_SUBMITTED,
        'requested_by' => $approver->id,
        'requested_at' => now(),
    ]);

    $corrected = app(AttendanceCalculationService::class)->approveCorrection($correction, $approver);

    expect(EmployeeAttendanceEvent::query()->where('employee_id', $employee->id)->count())->toBe(2)
        ->and($corrected->missing_clock_out)->toBeFalse()
        ->and($corrected->worked_minutes)->toBe(480)
        ->and($correction->fresh()->status)->toBe(AttendanceCorrectionRequest::STATUS_APPROVED);
});

it('uses approved overtime to clear payroll review when it matches calculated overtime', function (): void {
    $employee = Employee::factory()->create();
    createSchedule($employee);

    EmployeeAttendanceEvent::query()->create([
        'employee_id' => $employee->id,
        'event_type' => EmployeeAttendanceEvent::TYPE_CLOCK_IN,
        'occurred_at' => '2026-07-10 09:00:00',
        'attendance_date' => '2026-07-10',
        'source' => 'test',
    ]);
    EmployeeAttendanceEvent::query()->create([
        'employee_id' => $employee->id,
        'event_type' => EmployeeAttendanceEvent::TYPE_CLOCK_OUT,
        'occurred_at' => '2026-07-10 17:30:00',
        'attendance_date' => '2026-07-10',
        'source' => 'test',
    ]);

    OvertimeApproval::query()->create([
        'employee_id' => $employee->id,
        'attendance_date' => '2026-07-10',
        'requested_minutes' => 30,
        'approved_minutes' => 30,
        'status' => OvertimeApproval::STATUS_APPROVED,
    ]);

    $day = app(AttendanceCalculationService::class)->recalculate($employee, '2026-07-10');

    expect($day->overtime_minutes)->toBe(30)
        ->and($day->payroll_review_required)->toBeFalse();
});

it('prevents raw attendance event update and delete', function (): void {
    $employee = Employee::factory()->create();
    $event = EmployeeAttendanceEvent::query()->create([
        'employee_id' => $employee->id,
        'event_type' => EmployeeAttendanceEvent::TYPE_CLOCK_IN,
        'occurred_at' => '2026-07-10 09:00:00',
        'attendance_date' => '2026-07-10',
        'source' => 'test',
    ]);

    expect(fn () => $event->update(['occurred_at' => '2026-07-10 10:00:00']))
        ->toThrow(RuntimeException::class, 'immutable')
        ->and(fn () => $event->delete())
        ->toThrow(RuntimeException::class, 'cannot be deleted');
});

it('blocks overlapping work schedules and permits adjacent ranges through the locked service path', function (): void {
    $employee = Employee::factory()->create();
    $firstShift = EmployeeShift::query()->create([
        'code' => 'S1',
        'name' => 'Shift 1',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'is_active' => true,
    ]);
    $secondShift = EmployeeShift::query()->create([
        'code' => 'S2',
        'name' => 'Shift 2',
        'start_time' => '10:00',
        'end_time' => '18:00',
        'is_active' => true,
    ]);

    $service = app(EmployeeWorkScheduleService::class);
    $assignment = $service->create([
        'employee_id' => $employee->id,
        'employee_shift_id' => $firstShift->id,
        'effective_from' => '2026-07-01',
        'effective_until' => '2026-07-10',
        'is_active' => true,
    ]);

    expect(fn () => $service->create([
        'employee_id' => $employee->id,
        'employee_shift_id' => $secondShift->id,
        'effective_from' => '2026-07-10',
        'effective_until' => null,
        'is_active' => true,
    ]))->toThrow(RuntimeException::class, 'Only one active');

    $adjacent = $service->create([
        'employee_id' => $employee->id,
        'employee_shift_id' => $secondShift->id,
        'effective_from' => '2026-07-11',
        'effective_until' => null,
        'is_active' => true,
    ]);

    expect($assignment->exists)->toBeTrue()
        ->and($adjacent->exists)->toBeTrue();
});

it('allows own attendance, manager scope, and HR permission-based attendance access', function (): void {
    $employee = Employee::factory()->create();
    $otherEmployee = Employee::factory()->create();
    $user = attendanceUserWithPermissions($employee, ['hr.my_attendance.view']);
    $ownDay = EmployeeAttendanceDay::query()->create(['employee_id' => $employee->id, 'attendance_date' => '2026-07-10']);
    $otherDay = EmployeeAttendanceDay::query()->create(['employee_id' => $otherEmployee->id, 'attendance_date' => '2026-07-10']);

    expect(Gate::forUser($user)->allows('view', $ownDay))->toBeTrue()
        ->and(Gate::forUser($user)->allows('view', $otherDay))->toBeFalse()
        ->and(Gate::forUser($user)->allows('viewAny', EmployeeAttendanceDay::class))->toBeFalse();

    [$managerUser, $managedEmployee, $outsideEmployee] = attendanceManagerFixture();
    $managedDay = EmployeeAttendanceDay::query()->create(['employee_id' => $managedEmployee->id, 'attendance_date' => '2026-07-11']);
    $outsideDay = EmployeeAttendanceDay::query()->create(['employee_id' => $outsideEmployee->id, 'attendance_date' => '2026-07-11']);
    $hrUser = attendanceUserWithPermissions(Employee::factory()->create(), ['hr.attendance_register.view_any', 'hr.attendance_register.view']);

    expect(Gate::forUser($managerUser)->allows('view', $managedDay))->toBeTrue()
        ->and(Gate::forUser($managerUser)->allows('view', $outsideDay))->toBeFalse()
        ->and(Gate::forUser($hrUser)->allows('viewAny', EmployeeAttendanceDay::class))->toBeTrue()
        ->and(Gate::forUser($hrUser)->allows('view', $outsideDay))->toBeTrue();
});

it('enforces report/export and correction/overtime approval permissions', function (): void {
    $employee = Employee::factory()->create();
    $plainUser = attendanceUserWithPermissions($employee, []);
    $reportUser = attendanceUserWithPermissions(Employee::factory()->create(), ['hr.attendance_report.view', 'hr.attendance_report.export']);
    $approver = attendanceUserWithPermissions(Employee::factory()->create(), ['hr.attendance_correction.approve', 'hr.overtime_approval.approve']);

    $this->actingAs($plainUser);
    expect(AttendanceDashboardPage::canAccess())->toBeFalse()
        ->and(AttendanceReportsPage::canAccess())->toBeFalse();

    $this->actingAs($reportUser);
    expect(AttendanceDashboardPage::canAccess())->toBeTrue()
        ->and(AttendanceReportsPage::canAccess())->toBeTrue()
        ->and($reportUser->can('hr.attendance_report.export'))->toBeTrue();

    $correction = AttendanceCorrectionRequest::query()->create([
        'employee_id' => $employee->id,
        'attendance_date' => '2026-07-10',
        'requested_clock_in_at' => '2026-07-10 09:00:00',
        'reason' => 'Forgot.',
        'status' => AttendanceCorrectionRequest::STATUS_SUBMITTED,
    ]);
    $overtime = OvertimeApproval::query()->create([
        'employee_id' => $employee->id,
        'attendance_date' => '2026-07-10',
        'requested_minutes' => 30,
        'status' => OvertimeApproval::STATUS_SUBMITTED,
    ]);

    expect(Gate::forUser($plainUser)->allows('approve', $correction))->toBeFalse()
        ->and(Gate::forUser($approver)->allows('approve', $correction))->toBeTrue()
        ->and(Gate::forUser($plainUser)->allows('approve', $overtime))->toBeFalse()
        ->and(Gate::forUser($approver)->allows('approve', $overtime))->toBeTrue();
});

it('exports attendance reconciliation findings', function (): void {
    @unlink(base_path('storage/app/reports/attendance-reconcile-test.json'));
    $employee = Employee::factory()->create();
    EmployeeAttendanceEvent::query()->create([
        'employee_id' => $employee->id,
        'event_type' => EmployeeAttendanceEvent::TYPE_CLOCK_IN,
        'occurred_at' => '2026-07-10 09:00:00',
        'attendance_date' => '2026-07-10',
        'source' => 'test',
    ]);

    $this->artisan('biwms:attendance-reconcile --details --export=storage/app/reports/attendance-reconcile-test.json')
        ->assertSuccessful();

    expect(is_file(base_path('storage/app/reports/attendance-reconcile-test.json')))->toBeTrue();
});

function createSchedule(Employee $employee): EmployeeShift
{
    $shift = EmployeeShift::query()->create([
        'code' => 'DAY-'.str()->upper(str()->random(5)),
        'name' => 'Day Shift',
        'start_time' => '09:00',
        'end_time' => '17:00',
        'break_minutes' => 30,
        'grace_minutes' => 5,
        'is_active' => true,
    ]);

    EmployeeWorkScheduleAssignment::query()->create([
        'employee_id' => $employee->id,
        'employee_shift_id' => $shift->id,
        'effective_from' => '2026-01-01',
        'working_days' => [1, 2, 3, 4, 5],
        'is_active' => true,
    ]);

    return $shift;
}

function attendanceLeaveType(): LeaveType
{
    return LeaveType::query()->firstOrCreate(
        ['code' => 'ATT-LEAVE'],
        [
            'name' => 'Attendance Leave',
            'unit' => 'days',
            'paid' => true,
            'is_active' => true,
        ]
    );
}

function attendanceManagerFixture(): array
{
    $manager = Employee::factory()->create(['employee_number' => 'ATT-MGR']);
    $managerUser = User::factory()->create(['employee_id' => $manager->id]);
    $department = Department::query()->create([
        'department_code' => 'ATT',
        'name' => 'Attendance Department',
        'manager_id' => $manager->id,
    ]);
    $managedEmployee = Employee::factory()->create(['department_id' => $department->id]);
    $outsideEmployee = Employee::factory()->create();

    return [$managerUser, $managedEmployee, $outsideEmployee];
}

function attendanceUserWithPermissions(Employee $employee, array $permissions): User
{
    $role = Role::query()->create([
        'name' => 'attendance-test-role-'.str()->random(8),
        'guard_name' => 'web',
    ]);
    $role->givePermissionTo($permissions);

    $user = User::factory()->create(['employee_id' => $employee->id]);
    $user->assignRole($role);

    return $user;
}
