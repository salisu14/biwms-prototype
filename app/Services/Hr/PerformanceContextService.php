<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeAttendanceDay;
use Carbon\CarbonInterface;

class PerformanceContextService
{
    /**
     * @return array<string, int|float|string>
     */
    public function attendanceSummary(Employee $employee, CarbonInterface|string $from, CarbonInterface|string $to): array
    {
        $days = EmployeeAttendanceDay::query()
            ->where('employee_id', $employee->id)
            ->whereBetween('attendance_date', [$from, $to])
            ->get();

        $scheduledDays = max(1, $days->count());
        $presentDays = $days->whereIn('status', ['present', 'late', 'partial'])->count();

        return [
            'source' => 'employee_attendance_days',
            'from' => (string) $from,
            'to' => (string) $to,
            'scheduled_days' => $days->count(),
            'attendance_rate' => round(($presentDays / $scheduledDays) * 100, 2),
            'approved_leave_days' => $days->where('on_leave', true)->count(),
            'lateness_count' => $days->where('late_minutes', '>', 0)->count(),
            'early_departure_count' => $days->where('early_departure_minutes', '>', 0)->count(),
            'missing_clock_out_count' => $days->where('missing_clock_out', true)->count(),
            'approved_overtime_minutes' => 0,
            'payroll_amounts_exposed' => 0,
        ];
    }
}
