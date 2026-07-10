<?php

declare(strict_types=1);

use App\Models\Department;
use App\Models\Employee;
use App\Models\EmployeeLeaveLedgerEntry;
use App\Models\LeaveHoliday;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\User;
use App\Services\Hr\LeaveDurationService;
use App\Services\Hr\LeaveRequestService;
use Database\Seeders\PermissionsTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(PermissionsTableSeeder::class);
});

it('calculates working leave days excluding weekends and holidays with half days', function (): void {
    LeaveHoliday::query()->create([
        'name' => 'Company Holiday',
        'holiday_date' => '2026-07-13',
        'is_active' => true,
    ]);

    $service = app(LeaveDurationService::class);

    expect($service->calculate('2026-07-10', '2026-07-14'))->toBe(2.0)
        ->and($service->calculate('2026-07-14', '2026-07-14', 'morning', 'morning'))->toBe(0.5)
        ->and(fn () => $service->calculate('2026-07-15', '2026-07-14'))->toThrow(Exception::class);
});

it('submits only when balance is sufficient unless negative balance is allowed', function (): void {
    $user = leaveUserWithPermissions([]);
    $employee = Employee::factory()->create(['employee_number' => 'LV-EMP-01']);
    $paidType = leaveType('ANNUAL', allowNegative: false);
    $negativeType = leaveType('UNPAID', paid: false, allowNegative: true);

    $request = app(LeaveRequestService::class)->create($employee, leaveRequestData($paidType, '2026-07-14', '2026-07-16'));
    expect(fn () => app(LeaveRequestService::class)->submit($request, $user))
        ->toThrow(Exception::class, 'Insufficient leave balance');

    EmployeeLeaveLedgerEntry::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $paidType->id,
        'leave_year' => 2026,
        'entry_type' => EmployeeLeaveLedgerEntry::TYPE_ENTITLEMENT,
        'quantity' => 5,
        'posting_date' => '2026-01-01',
    ]);

    $submitted = app(LeaveRequestService::class)->submit($request->fresh(), $user);
    expect($submitted->status)->toBe(LeaveRequest::STATUS_SUBMITTED);

    $negativeRequest = app(LeaveRequestService::class)->create($employee, leaveRequestData($negativeType, '2026-08-03', '2026-08-04'));
    $negativeSubmitted = app(LeaveRequestService::class)->submit($negativeRequest, $user);
    expect($negativeSubmitted->status)->toBe(LeaveRequest::STATUS_SUBMITTED);
});

it('prevents overlapping submitted leave requests', function (): void {
    $user = leaveUserWithPermissions([]);
    $employee = Employee::factory()->create();
    $leaveType = leaveType('ANNUAL');
    grantLeaveBalance($employee, $leaveType, 10);

    $request = app(LeaveRequestService::class)->create($employee, leaveRequestData($leaveType, '2026-07-14', '2026-07-16'));
    app(LeaveRequestService::class)->submit($request, $user);

    expect(fn () => app(LeaveRequestService::class)->create($employee, leaveRequestData($leaveType, '2026-07-15', '2026-07-17')))
        ->toThrow(Exception::class, 'overlaps');
});

it('runs manager and HR approval, posts once, and blocks self approval', function (): void {
    [$managerUser, $employee] = managedEmployeeFixture();
    $hrUser = leaveUserWithPermissions(['hr.leave_approval.approve']);
    $leaveType = leaveType('ANNUAL');
    grantLeaveBalance($employee, $leaveType, 10);

    $request = app(LeaveRequestService::class)->create($employee, leaveRequestData($leaveType, '2026-07-14', '2026-07-16'));
    $employeeUser = User::factory()->create(['employee_id' => $employee->id]);
    app(LeaveRequestService::class)->submit($request, $employeeUser);

    expect(fn () => app(LeaveRequestService::class)->managerApprove($request->fresh(), $employeeUser))
        ->toThrow(Exception::class, 'own leave');

    $managerApproved = app(LeaveRequestService::class)->managerApprove($request->fresh(), $managerUser);
    expect($managerApproved->status)->toBe(LeaveRequest::STATUS_MANAGER_APPROVED);

    $posted = app(LeaveRequestService::class)->hrApprove($request->fresh(), $hrUser);
    expect($posted->status)->toBe(LeaveRequest::STATUS_POSTED)
        ->and(EmployeeLeaveLedgerEntry::query()->where('leave_request_id', $request->id)->where('entry_type', EmployeeLeaveLedgerEntry::TYPE_APPROVED_LEAVE)->count())->toBe(1)
        ->and(app(LeaveRequestService::class)->balance($employee->id, $leaveType->id, 2026))->toBe(7.0);

    app(LeaveRequestService::class)->hrApprove($request->fresh(), $hrUser);
    expect(EmployeeLeaveLedgerEntry::query()->where('leave_request_id', $request->id)->where('entry_type', EmployeeLeaveLedgerEntry::TYPE_APPROVED_LEAVE)->count())->toBe(1);
});

it('rejects without reducing balance and cancellation reverses posted leave', function (): void {
    $user = leaveUserWithPermissions(['hr.leave_approval.approve']);
    $employee = Employee::factory()->create();
    $leaveType = leaveType('ANNUAL');
    grantLeaveBalance($employee, $leaveType, 10);

    $rejected = app(LeaveRequestService::class)->create($employee, leaveRequestData($leaveType, '2026-07-14', '2026-07-14'));
    app(LeaveRequestService::class)->submit($rejected, $user);
    app(LeaveRequestService::class)->reject($rejected->fresh(), $user, 'Not enough notice.');
    expect(app(LeaveRequestService::class)->balance($employee->id, $leaveType->id, 2026))->toBe(10.0);

    $posted = app(LeaveRequestService::class)->create($employee, leaveRequestData($leaveType, '2026-07-15', '2026-07-15'));
    app(LeaveRequestService::class)->submit($posted, $user);
    app(LeaveRequestService::class)->hrApprove($posted->fresh(), $user);
    expect(app(LeaveRequestService::class)->balance($employee->id, $leaveType->id, 2026))->toBe(9.0);

    app(LeaveRequestService::class)->cancel($posted->fresh(), $user, 'Cancelled by employee.');
    expect(app(LeaveRequestService::class)->balance($employee->id, $leaveType->id, 2026))->toBe(10.0)
        ->and(EmployeeLeaveLedgerEntry::query()->where('leave_request_id', $posted->id)->where('entry_type', EmployeeLeaveLedgerEntry::TYPE_REVERSAL)->exists())->toBeTrue();
});

it('flags unpaid leave for payroll review and keeps attachments private', function (): void {
    Storage::fake('local');

    $user = leaveUserWithPermissions(['hr.leave_approval.approve']);
    $employee = Employee::factory()->create();
    $leaveType = leaveType('UNPAID', paid: false, allowNegative: true);

    $request = app(LeaveRequestService::class)->create($employee, [
        ...leaveRequestData($leaveType, '2026-07-14', '2026-07-14'),
        'attachment_path' => 'leave-attachments/private-note.pdf',
    ]);
    app(LeaveRequestService::class)->submit($request, $user);
    $posted = app(LeaveRequestService::class)->hrApprove($request->fresh(), $user);

    expect($posted->payroll_review_required)->toBeTrue()
        ->and($posted->payroll_impact_status)->toBe('review_required')
        ->and($posted->attachment_path)->toStartWith('leave-attachments/');
});

it('exports leave reconciliation findings', function (): void {
    $employee = Employee::factory()->create();
    $leaveType = leaveType('ANNUAL');
    $request = app(LeaveRequestService::class)->create($employee, leaveRequestData($leaveType, '2026-07-14', '2026-07-14'));
    $request->forceFill([
        'status' => LeaveRequest::STATUS_APPROVED,
        'approved_quantity' => 1,
    ])->save();

    $this->artisan('biwms:leave-reconcile --details --export=storage/app/reports/leave-reconcile-test.json')
        ->assertSuccessful();

    Storage::disk('local')->assertExists('reports/leave-reconcile-test.json');
});

function leaveType(string $code, bool $paid = true, bool $allowNegative = false): LeaveType
{
    return LeaveType::query()->create([
        'code' => $code.'-'.str()->upper(str()->random(4)),
        'name' => $code.' Leave',
        'unit' => 'days',
        'paid' => $paid,
        'allow_negative_balance' => $allowNegative,
        'requires_manager_approval' => true,
        'requires_hr_approval' => true,
        'is_active' => true,
    ]);
}

function leaveRequestData(LeaveType $leaveType, string $startDate, string $endDate): array
{
    return [
        'leave_type_id' => $leaveType->id,
        'start_date' => $startDate,
        'end_date' => $endDate,
        'start_part' => 'full_day',
        'end_part' => 'full_day',
        'reason' => 'Family event',
    ];
}

function leaveUserWithPermissions(array $permissions): User
{
    $role = Role::query()->create([
        'name' => 'leave-test-role-'.str()->random(8),
        'guard_name' => 'web',
    ]);
    $role->givePermissionTo($permissions);

    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function grantLeaveBalance(Employee $employee, LeaveType $leaveType, float $quantity): void
{
    EmployeeLeaveLedgerEntry::query()->create([
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'leave_year' => 2026,
        'entry_type' => EmployeeLeaveLedgerEntry::TYPE_ENTITLEMENT,
        'quantity' => $quantity,
        'posting_date' => '2026-01-01',
    ]);
}

function managedEmployeeFixture(): array
{
    $manager = Employee::factory()->create(['employee_number' => 'MGR-001']);
    $managerUser = User::factory()->create(['employee_id' => $manager->id]);
    $department = Department::query()->create([
        'department_code' => 'LEAVE',
        'name' => 'Leave Department',
        'manager_id' => $manager->id,
    ]);
    $employee = Employee::factory()->create([
        'employee_number' => 'EMP-LEAVE',
        'department_id' => $department->id,
        'department_code' => 'LEAVE',
    ]);

    return [$managerUser, $employee];
}
