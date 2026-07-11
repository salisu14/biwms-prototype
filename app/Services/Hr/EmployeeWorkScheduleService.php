<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeWorkScheduleAssignment;
use Illuminate\Support\Facades\DB;

class EmployeeWorkScheduleService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): EmployeeWorkScheduleAssignment
    {
        return DB::transaction(function () use ($data): EmployeeWorkScheduleAssignment {
            Employee::query()->lockForUpdate()->findOrFail($data['employee_id']);

            return EmployeeWorkScheduleAssignment::query()->create($data);
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(EmployeeWorkScheduleAssignment $assignment, array $data): EmployeeWorkScheduleAssignment
    {
        return DB::transaction(function () use ($assignment, $data): EmployeeWorkScheduleAssignment {
            Employee::query()->lockForUpdate()->findOrFail($data['employee_id'] ?? $assignment->employee_id);

            $assignment->fill($data);
            $assignment->save();

            return $assignment->fresh(['employee', 'shift']);
        });
    }
}
