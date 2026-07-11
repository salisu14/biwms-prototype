<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\EmployeeWorkScheduleAssignment;
use App\Models\WorkforceRosterAssignment;
use Illuminate\Support\Carbon;

class WorkforceScheduleResolverService
{
    /**
     * @return array{shift: EmployeeShift|null, assignment: WorkforceRosterAssignment|null, location_id: int|null, work_center_id: int|null, expected_start_at: Carbon|null, expected_end_at: Carbon|null, break_minutes: int, source: string, version: string|null, conflicts: array<int, string>}
     */
    public function resolve(Employee $employee, Carbon|string $date): array
    {
        $workDate = Carbon::parse($date)->startOfDay();
        $publishedAssignments = WorkforceRosterAssignment::query()
            ->with('shift')
            ->where('employee_id', $employee->id)
            ->whereDate('work_date', $workDate->toDateString())
            ->whereIn('status', [WorkforceRosterAssignment::STATUS_PUBLISHED, WorkforceRosterAssignment::STATUS_ACCEPTED, WorkforceRosterAssignment::STATUS_COMPLETED])
            ->whereNotIn('status', [WorkforceRosterAssignment::STATUS_CANCELLED, WorkforceRosterAssignment::STATUS_REPLACED])
            ->orderBy('expected_start_at')
            ->get();

        if ($publishedAssignments->isNotEmpty()) {
            $assignment = $publishedAssignments->first();

            return [
                'shift' => $assignment->shift,
                'assignment' => $assignment,
                'location_id' => $assignment->attendance_location_id,
                'work_center_id' => $assignment->work_center_id,
                'expected_start_at' => $assignment->expected_start_at,
                'expected_end_at' => $assignment->expected_end_at,
                'break_minutes' => (int) ($assignment->break_minutes ?? $assignment->shift?->break_minutes ?? 0),
                'source' => 'workforce_roster',
                'version' => $assignment->published_at?->toIso8601String(),
                'conflicts' => $publishedAssignments->count() > 1 ? ['multiple_published_roster_assignments'] : [],
            ];
        }

        $schedule = EmployeeWorkScheduleAssignment::query()
            ->with('shift')
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $workDate->toDateString())
            ->where(function ($query) use ($workDate): void {
                $query->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $workDate->toDateString());
            })
            ->orderByDesc('effective_from')
            ->first();

        if ($schedule instanceof EmployeeWorkScheduleAssignment) {
            [$start, $end] = $this->bounds($schedule->shift, $workDate);

            return [
                'shift' => $schedule->shift,
                'assignment' => null,
                'location_id' => null,
                'work_center_id' => null,
                'expected_start_at' => $start,
                'expected_end_at' => $end,
                'break_minutes' => (int) ($schedule->shift?->break_minutes ?? 0),
                'source' => 'employee_work_schedule',
                'version' => $schedule->updated_at?->toIso8601String(),
                'conflicts' => [],
            ];
        }

        return [
            'shift' => null,
            'assignment' => null,
            'location_id' => null,
            'work_center_id' => null,
            'expected_start_at' => null,
            'expected_end_at' => null,
            'break_minutes' => 0,
            'source' => 'none',
            'version' => null,
            'conflicts' => ['no_schedule_found'],
        ];
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    public function bounds(?EmployeeShift $shift, Carbon|string $date): array
    {
        if ($shift === null) {
            return [null, null];
        }

        $workDate = Carbon::parse($date)->startOfDay();
        $start = Carbon::parse($workDate->toDateString().' '.$shift->start_time);
        $end = Carbon::parse($workDate->toDateString().' '.$shift->end_time);

        if ($shift->crosses_midnight || $end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }
}
