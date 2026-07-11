<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeWorkAvailability;
use App\Models\LeaveRequest;
use App\Models\WorkforceRosterAssignment;
use App\Models\WorkforceSchedulingRule;
use Illuminate\Support\Carbon;

class WorkforceScheduleValidationService
{
    /**
     * @param  array<string, mixed>  $assignment
     * @return array{blocking: array<int, string>, warnings: array<int, string>}
     */
    public function validateAssignment(array $assignment, ?int $ignoreAssignmentId = null): array
    {
        $blocking = [];
        $warnings = [];
        $employee = Employee::query()->find($assignment['employee_id'] ?? null);
        $workDate = ! empty($assignment['work_date'])
            ? $this->normalizeDate($assignment['work_date'])
            : '';

        if (! $employee || ! $employee->is_active) {
            $blocking[] = 'inactive_employee';
        }

        if ($this->hasApprovedFullDayLeave((int) ($assignment['employee_id'] ?? 0), $workDate)) {
            $blocking[] = 'approved_leave_conflict';
        }

        if ($this->hasApprovedUnavailability((int) ($assignment['employee_id'] ?? 0), $workDate)) {
            $blocking[] = 'approved_unavailability';
        }

        if ($this->hasOverlap($assignment, $ignoreAssignmentId)) {
            $blocking[] = 'overlapping_assignment';
        }

        if ($this->violatesMinimumRest($assignment, $ignoreAssignmentId)) {
            $severity = $this->minimumRestSeverity();
            $severity === 'blocking' ? $blocking[] = 'minimum_rest_violation' : $warnings[] = 'minimum_rest_warning';
        }

        return compact('blocking', 'warnings');
    }

    public function hasOverlap(array $assignment, ?int $ignoreAssignmentId = null): bool
    {
        if (empty($assignment['expected_start_at']) || empty($assignment['expected_end_at'])) {
            return false;
        }

        return WorkforceRosterAssignment::query()
            ->when($ignoreAssignmentId, fn ($query) => $query->whereKeyNot($ignoreAssignmentId))
            ->where('employee_id', $assignment['employee_id'])
            ->whereNotIn('status', [WorkforceRosterAssignment::STATUS_CANCELLED, WorkforceRosterAssignment::STATUS_REPLACED, WorkforceRosterAssignment::STATUS_DECLINED])
            ->where('expected_start_at', '<', $assignment['expected_end_at'])
            ->where('expected_end_at', '>', $assignment['expected_start_at'])
            ->exists();
    }

    private function hasApprovedFullDayLeave(int $employeeId, string $date): bool
    {
        return LeaveRequest::query()
            ->where('employee_id', $employeeId)
            ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_POSTED, LeaveRequest::STATUS_COMPLETED])
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date)
            ->get()
            ->contains(function (LeaveRequest $request) use ($date): bool {
                $requestStart = $request->start_date?->toDateString();
                $requestEnd = $request->end_date?->toDateString();

                if ($requestStart !== $requestEnd && $date !== $requestStart && $date !== $requestEnd) {
                    return true;
                }

                if ($date === $requestStart && $this->isFullDayPart($request->start_part)) {
                    return true;
                }

                return $date === $requestEnd && $this->isFullDayPart($request->end_part);
            });
    }

    private function isFullDayPart(?string $part): bool
    {
        return $part === null || in_array($part, ['full', 'full_day'], true);
    }

    private function normalizeDate(mixed $date): string
    {
        return Carbon::parse($date)->setTimezone(config('app.timezone'))->toDateString();
    }

    private function hasApprovedUnavailability(int $employeeId, string $date): bool
    {
        return EmployeeWorkAvailability::query()
            ->where('employee_id', $employeeId)
            ->where('status', EmployeeWorkAvailability::STATUS_APPROVED)
            ->where('availability_type', EmployeeWorkAvailability::TYPE_UNAVAILABLE)
            ->whereDate('date_from', '<=', $date)
            ->whereDate('date_to', '>=', $date)
            ->exists();
    }

    private function violatesMinimumRest(array $assignment, ?int $ignoreAssignmentId = null): bool
    {
        $rule = WorkforceSchedulingRule::query()
            ->where('is_active', true)
            ->where('rule_type', WorkforceSchedulingRule::TYPE_MINIMUM_REST_HOURS)
            ->orderByDesc('effective_from')
            ->first();

        if (! $rule || empty($assignment['expected_start_at'])) {
            return false;
        }

        $minimumMinutes = (int) round((float) ($rule->value_decimal ?? $rule->value_integer ?? 0) * 60);
        if ($minimumMinutes <= 0) {
            return false;
        }

        $start = Carbon::parse($assignment['expected_start_at']);
        $previous = WorkforceRosterAssignment::query()
            ->when($ignoreAssignmentId, fn ($query) => $query->whereKeyNot($ignoreAssignmentId))
            ->where('employee_id', $assignment['employee_id'])
            ->whereNotIn('status', [WorkforceRosterAssignment::STATUS_CANCELLED, WorkforceRosterAssignment::STATUS_REPLACED, WorkforceRosterAssignment::STATUS_DECLINED])
            ->where('expected_end_at', '<=', $start)
            ->orderByDesc('expected_end_at')
            ->first();

        return $previous !== null && $previous->expected_end_at->diffInMinutes($start) < $minimumMinutes;
    }

    private function minimumRestSeverity(): string
    {
        return WorkforceSchedulingRule::query()
            ->where('is_active', true)
            ->where('rule_type', WorkforceSchedulingRule::TYPE_MINIMUM_REST_HOURS)
            ->value('severity') ?? 'warning';
    }
}
