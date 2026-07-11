<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\WorkforceRosterAssignment;

class WorkforceReplacementRecommendationService
{
    /**
     * @return array<int, array{employee: Employee, score: int, reasons: array<int, string>, warnings: array<int, string>}>
     */
    public function recommendFor(WorkforceRosterAssignment $assignment): array
    {
        return Employee::query()
            ->where('is_active', true)
            ->whereKeyNot($assignment->employee_id)
            ->when($assignment->department_id, fn ($query) => $query->where('department_id', $assignment->department_id))
            ->limit(20)
            ->get()
            ->map(function (Employee $employee) use ($assignment): array {
                $hasOverlap = WorkforceRosterAssignment::query()
                    ->where('employee_id', $employee->id)
                    ->whereNotIn('status', [WorkforceRosterAssignment::STATUS_CANCELLED, WorkforceRosterAssignment::STATUS_REPLACED, WorkforceRosterAssignment::STATUS_DECLINED])
                    ->where('expected_start_at', '<', $assignment->expected_end_at)
                    ->where('expected_end_at', '>', $assignment->expected_start_at)
                    ->exists();

                return [
                    'employee' => $employee,
                    'score' => $hasOverlap ? 10 : 100,
                    'reasons' => [$hasOverlap ? 'Department match, but overlapping assignment exists.' : 'Department match and no overlapping roster assignment.'],
                    'warnings' => $hasOverlap ? ['overlapping_assignment'] : [],
                ];
            })
            ->sortByDesc('score')
            ->values()
            ->all();
    }
}
