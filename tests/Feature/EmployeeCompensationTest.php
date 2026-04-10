<?php

use App\Models\Employee;
use App\Models\EmployeeCompensation;
use App\Models\EmployeePostingGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('getCurrentBaseSalary fetches the latest active salary before or on today', function () {
    $group = EmployeePostingGroup::factory()->create();
    $employee = Employee::factory()->create(['employee_posting_group_id' => $group->id]);

    // Insert old salary
    EmployeeCompensation::create([
        'employee_id' => $employee->id,
        'effective_date' => now()->subMonths(6)->toDateString(),
        'base_salary' => 5000,
        'reason_code' => 'HIRE',
    ]);

    // Insert current salary
    EmployeeCompensation::create([
        'employee_id' => $employee->id,
        'effective_date' => now()->subMonth()->toDateString(),
        'base_salary' => 6000,
        'reason_code' => 'PROMOTION',
    ]);

    // Insert future salary change
    EmployeeCompensation::create([
        'employee_id' => $employee->id,
        'effective_date' => now()->addMonth()->toDateString(),
        'base_salary' => 7000,
        'reason_code' => 'UPCOMING',
    ]);

    expect((float) $employee->getCurrentBaseSalary())->toBe(6000.00);
});
