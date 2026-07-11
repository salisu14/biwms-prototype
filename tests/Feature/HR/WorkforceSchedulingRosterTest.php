<?php

declare(strict_types=1);

use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\EmployeeWorkAvailability;
use App\Models\EmployeeWorkScheduleAssignment;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\PayrollLine;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Models\WorkforceRosterAssignment;
use App\Models\WorkforceRosterHistory;
use App\Models\WorkforceRosterPeriod;
use App\Models\WorkforceRotationAssignment;
use App\Models\WorkforceRotationTemplate;
use App\Models\WorkforceRotationTemplateDay;
use App\Services\Hr\AttendanceCalculationService;
use App\Services\Hr\WorkforceRosterGenerationService;
use App\Services\Hr\WorkforceRosterPublishingService;
use App\Services\Hr\WorkforceScheduleResolverService;
use App\Services\Hr\WorkforceScheduleValidationService;
use App\Services\Hr\WorkforceShiftReplacementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

it('generates rotation roster assignments without creating payroll lines', function (): void {
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['employee_number' => 'WF-EMP-001']);
    $shift = workforceTestShift('DAY', '08:00:00', '16:00:00');
    $template = WorkforceRotationTemplate::query()->create([
        'code' => 'ROT-2D',
        'name' => 'Two day rotation',
        'cycle_length_days' => 2,
        'is_active' => true,
        'effective_from' => '2026-07-01',
    ]);

    WorkforceRotationTemplateDay::query()->create([
        'workforce_rotation_template_id' => $template->id,
        'sequence_day' => 1,
        'employee_shift_id' => $shift->id,
        'is_rest_day' => false,
    ]);
    WorkforceRotationTemplateDay::query()->create([
        'workforce_rotation_template_id' => $template->id,
        'sequence_day' => 2,
        'is_rest_day' => true,
    ]);
    WorkforceRotationAssignment::query()->create([
        'workforce_rotation_template_id' => $template->id,
        'employee_id' => $employee->id,
        'effective_from' => '2026-07-01',
        'cycle_start_date' => '2026-07-01',
        'starting_sequence_day' => 1,
        'is_primary' => true,
        'is_active' => true,
        'assigned_by' => $user->id,
    ]);
    $period = workforceTestPeriod('WF-JUL-1', '2026-07-01', '2026-07-02');

    $service = app(WorkforceRosterGenerationService::class);
    $preview = $service->preview($period);

    expect($preview['assignments'])->toHaveCount(1)
        ->and($preview['rest_days'])->toHaveCount(1);

    $service->generate($period, $user->id);
    $service->generate($period->fresh(), $user->id);

    expect(WorkforceRosterAssignment::query()->where('workforce_roster_period_id', $period->id)->count())->toBe(1)
        ->and(PayrollLine::query()->count())->toBe(0);
});

it('uses published roster assignments before legacy work schedule assignments', function (): void {
    $employee = Employee::factory()->create(['employee_number' => 'WF-EMP-002']);
    $legacyShift = workforceTestShift('LEGACY', '09:00:00', '17:00:00');
    $rosterShift = workforceTestShift('ROSTER', '12:00:00', '20:00:00');

    EmployeeWorkScheduleAssignment::query()->create([
        'employee_id' => $employee->id,
        'employee_shift_id' => $legacyShift->id,
        'effective_from' => '2026-07-01',
        'is_active' => true,
        'working_days' => [1, 2, 3, 4, 5],
    ]);

    $period = workforceTestPeriod('WF-JUL-2', '2026-07-03', '2026-07-03', WorkforceRosterPeriod::STATUS_PUBLISHED);
    $assignment = workforceTestRosterAssignment($period, $employee, $rosterShift, '2026-07-03', WorkforceRosterAssignment::STATUS_PUBLISHED);

    $resolved = app(WorkforceScheduleResolverService::class)->resolve($employee, '2026-07-03');
    $day = app(AttendanceCalculationService::class)->recalculate($employee, '2026-07-03');

    expect($resolved['source'])->toBe('workforce_roster')
        ->and($resolved['assignment']->is($assignment))->toBeTrue()
        ->and($day->employee_shift_id)->toBe($rosterShift->id)
        ->and($day->workforce_roster_assignment_id)->toBe($assignment->id)
        ->and($day->schedule_source)->toBe('workforce_roster');
});

it('blocks overlapping active roster assignments atomically', function (): void {
    $employee = Employee::factory()->create(['employee_number' => 'WF-EMP-003']);
    $shift = workforceTestShift('OVER', '08:00:00', '16:00:00');
    $period = workforceTestPeriod('WF-JUL-3', '2026-07-04', '2026-07-04');

    workforceTestRosterAssignment($period, $employee, $shift, '2026-07-04', WorkforceRosterAssignment::STATUS_PUBLISHED);

    expect(fn () => workforceTestRosterAssignment($period, $employee, $shift, '2026-07-04', WorkforceRosterAssignment::STATUS_PUBLISHED))
        ->toThrow(RuntimeException::class, 'overlapping active roster assignment');
});

it('blocks publishing rosters with approved leave conflicts and supports idempotent publish', function (): void {
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['employee_number' => 'WF-EMP-004']);
    $shift = workforceTestShift('PUB', '08:00:00', '16:00:00');
    $period = workforceTestPeriod('WF-JUL-4', '2026-07-05', '2026-07-05', WorkforceRosterPeriod::STATUS_GENERATED);

    $assignment = workforceTestRosterAssignment($period, $employee, $shift, '2026-07-05');
    $leaveType = LeaveType::query()->create(['code' => 'AL', 'name' => 'Annual Leave']);
    LeaveRequest::query()->create([
        'request_number' => 'LV-WF-001',
        'employee_id' => $employee->id,
        'leave_type_id' => $leaveType->id,
        'start_date' => '2026-07-05',
        'end_date' => '2026-07-05',
        'start_part' => 'full_day',
        'end_part' => 'full_day',
        'requested_quantity' => 1,
        'approved_quantity' => 1,
        'status' => LeaveRequest::STATUS_APPROVED,
    ]);

    expect(app(WorkforceScheduleValidationService::class)->validateAssignment($assignment->fresh()->toArray(), $assignment->id)['blocking'])
        ->toContain('approved_leave_conflict');

    expect(fn () => app(WorkforceRosterPublishingService::class)->publish($period, $user->id, acknowledgeWarnings: true))
        ->toThrow(RuntimeException::class, 'blocking conflicts');

    LeaveRequest::query()->delete();
    $published = app(WorkforceRosterPublishingService::class)->publish($period->fresh(), $user->id, acknowledgeWarnings: true);
    $publishedAgain = app(WorkforceRosterPublishingService::class)->publish($published, $user->id, acknowledgeWarnings: true);

    expect($published->status)->toBe(WorkforceRosterPeriod::STATUS_PUBLISHED)
        ->and($publishedAgain->status)->toBe(WorkforceRosterPeriod::STATUS_PUBLISHED)
        ->and(WorkforceRosterHistory::query()->where('event_type', 'assignment_published')->count())->toBe(1)
        ->and(PayrollLine::query()->count())->toBe(0);
});

it('preserves original assignments when approving controlled replacements', function (): void {
    $user = User::factory()->create();
    $employee = Employee::factory()->create(['employee_number' => 'WF-EMP-005']);
    $replacementEmployee = Employee::factory()->create(['employee_number' => 'WF-EMP-006']);
    $shift = workforceTestShift('REP', '08:00:00', '16:00:00');
    $period = workforceTestPeriod('WF-JUL-5', '2026-07-06', '2026-07-06', WorkforceRosterPeriod::STATUS_PUBLISHED);
    $assignment = workforceTestRosterAssignment($period, $employee, $shift, '2026-07-06', WorkforceRosterAssignment::STATUS_PUBLISHED);

    $service = app(WorkforceShiftReplacementService::class);
    $replacement = $service->propose($assignment, $replacementEmployee->id, $user->id, 'Employee unavailable');
    $approved = $service->approve($replacement, $user->id)->fresh();

    $assignment->refresh();
    $replacementAssignment = WorkforceRosterAssignment::query()->findOrFail($approved->replacement_roster_assignment_id);

    expect($assignment->status)->toBe(WorkforceRosterAssignment::STATUS_REPLACED)
        ->and($assignment->replaced_by_assignment_id)->toBe($replacementAssignment->id)
        ->and($replacementAssignment->original_assignment_id)->toBe($assignment->id)
        ->and($replacementAssignment->employee_id)->toBe($replacementEmployee->id);
});

it('enforces own roster and confidential availability authorization boundaries', function (): void {
    $employee = Employee::factory()->create(['employee_number' => 'WF-EMP-007']);
    $otherEmployee = Employee::factory()->create(['employee_number' => 'WF-EMP-008']);
    $user = User::factory()->create(['employee_id' => $employee->id]);
    $hrUser = User::factory()->create();

    workforceGivePermissions($user, ['hr.my_roster.view', 'hr.my_availability.view']);
    workforceGivePermissions($hrUser, ['hr.employee_work_availability.view', 'hr.employee_work_availability.view_confidential']);

    $shift = workforceTestShift('AUTH', '08:00:00', '16:00:00');
    $period = workforceTestPeriod('WF-JUL-6', '2026-07-07', '2026-07-07', WorkforceRosterPeriod::STATUS_PUBLISHED);
    $ownAssignment = workforceTestRosterAssignment($period, $employee, $shift, '2026-07-07', WorkforceRosterAssignment::STATUS_PUBLISHED);
    $otherAssignment = workforceTestRosterAssignment($period, $otherEmployee, $shift, '2026-07-07', WorkforceRosterAssignment::STATUS_PUBLISHED);
    $availability = EmployeeWorkAvailability::query()->create([
        'employee_id' => $employee->id,
        'availability_type' => EmployeeWorkAvailability::TYPE_UNAVAILABLE,
        'status' => EmployeeWorkAvailability::STATUS_SUBMITTED,
        'date_from' => '2026-07-08',
        'date_to' => '2026-07-08',
        'is_confidential' => true,
    ]);

    expect(Gate::forUser($user)->allows('view', $ownAssignment))->toBeTrue()
        ->and(Gate::forUser($user)->allows('view', $otherAssignment))->toBeFalse()
        ->and(Gate::forUser($user)->allows('view', $availability))->toBeFalse()
        ->and(Gate::forUser($hrUser)->allows('view', $availability))->toBeTrue();
});

it('exports report-only workforce roster reconcile findings', function (): void {
    $employee = Employee::factory()->create(['employee_number' => 'WF-EMP-009']);
    $shift = workforceTestShift('REC', '08:00:00', '16:00:00');
    $period = workforceTestPeriod('WF-JUL-7', '2026-07-09', '2026-07-09', WorkforceRosterPeriod::STATUS_PUBLISHED);
    workforceTestRosterAssignment($period, $employee, $shift, '2026-07-09', WorkforceRosterAssignment::STATUS_PUBLISHED);

    $exportPath = 'storage/app/testing/workforce-roster-reconcile.json';

    $this->artisan('biwms:workforce-roster-reconcile', ['--details' => true, '--export' => $exportPath])
        ->expectsOutputToContain('BIWMS Workforce Roster Reconcile')
        ->assertSuccessful();

    expect(file_exists(base_path($exportPath)))->toBeTrue();
    $report = json_decode((string) file_get_contents(base_path($exportPath)), true);

    expect($report['findings'])->toBeArray()
        ->and(collect($report['findings'])->pluck('classification'))->toContain('published_roster_without_attendance_trace');
});

function workforceTestShift(string $code, string $startTime, string $endTime, bool $crossesMidnight = false): EmployeeShift
{
    return EmployeeShift::query()->create([
        'code' => $code.'-'.fake()->unique()->numerify('###'),
        'name' => $code.' Shift',
        'start_time' => $startTime,
        'end_time' => $endTime,
        'crosses_midnight' => $crossesMidnight,
        'break_minutes' => 0,
        'is_active' => true,
    ]);
}

function workforceTestPeriod(string $code, string $from, string $to, string $status = WorkforceRosterPeriod::STATUS_DRAFT): WorkforceRosterPeriod
{
    return WorkforceRosterPeriod::query()->create([
        'code' => $code,
        'name' => $code,
        'date_from' => $from,
        'date_to' => $to,
        'status' => $status,
    ]);
}

function workforceTestRosterAssignment(
    WorkforceRosterPeriod $period,
    Employee $employee,
    EmployeeShift $shift,
    string $workDate,
    string $status = WorkforceRosterAssignment::STATUS_DRAFT,
): WorkforceRosterAssignment {
    $start = Carbon::parse($workDate.' '.$shift->start_time);
    $end = Carbon::parse($workDate.' '.$shift->end_time);

    if ($shift->crosses_midnight || $end->lessThanOrEqualTo($start)) {
        $end->addDay();
    }

    return WorkforceRosterAssignment::query()->create([
        'workforce_roster_period_id' => $period->id,
        'employee_id' => $employee->id,
        'work_date' => $workDate,
        'employee_shift_id' => $shift->id,
        'department_id' => $employee->department_id,
        'assignment_type' => WorkforceRosterAssignment::TYPE_MANUAL,
        'status' => $status,
        'expected_start_at' => $start,
        'expected_end_at' => $end,
        'break_minutes' => $shift->break_minutes,
        'published_at' => in_array($status, [WorkforceRosterAssignment::STATUS_PUBLISHED, WorkforceRosterAssignment::STATUS_ACCEPTED], true) ? now() : null,
    ]);
}

function workforceGivePermissions(User $user, array $permissions): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    foreach ($permissions as $permission) {
        Permission::findOrCreate($permission, 'web');
    }

    $role = Role::query()->create([
        'name' => 'workforce-test-'.fake()->unique()->numerify('####'),
        'guard_name' => 'web',
    ]);
    $role->syncPermissions($permissions);
    $user->assignRole($role);

    app(PermissionRegistrar::class)->forgetCachedPermissions();
}
