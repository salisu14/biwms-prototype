<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\EmployeeAttendanceDay;
use App\Models\WorkforceRosterAssignment;
use App\Models\WorkforceRosterHistory;
use App\Models\WorkforceRosterPeriod;
use App\Services\AuditTrailService;
use Illuminate\Support\Facades\DB;

class WorkforceRosterPublishingService
{
    public function __construct(
        private readonly WorkforceScheduleValidationService $validator,
        private readonly AttendanceCalculationService $attendanceCalculationService,
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function publish(WorkforceRosterPeriod $period, int $userId, bool $acknowledgeWarnings = false): WorkforceRosterPeriod
    {
        return DB::transaction(function () use ($period, $userId, $acknowledgeWarnings): WorkforceRosterPeriod {
            $locked = WorkforceRosterPeriod::query()->lockForUpdate()->findOrFail($period->id);
            if (in_array($locked->status, [WorkforceRosterPeriod::STATUS_PUBLISHED, WorkforceRosterPeriod::STATUS_ACTIVE], true)) {
                return $locked->fresh(['assignments']);
            }

            if (! in_array($locked->status, [WorkforceRosterPeriod::STATUS_DRAFT, WorkforceRosterPeriod::STATUS_GENERATED, WorkforceRosterPeriod::STATUS_UNDER_REVIEW, WorkforceRosterPeriod::STATUS_REOPENED], true)) {
                throw new \RuntimeException('Roster period is not publishable.');
            }

            $assignments = $locked->assignments()->with(['employee', 'shift'])->lockForUpdate()->get();
            if ($assignments->isEmpty()) {
                throw new \RuntimeException('Roster period has no assignments to publish.');
            }

            foreach ($assignments as $assignment) {
                if (! $assignment->shift?->is_active || ! $assignment->employee?->is_active) {
                    throw new \RuntimeException('Roster contains inactive employee or shift.');
                }

                $validation = $this->validator->validateAssignment($assignment->toArray(), $assignment->id);
                if ($validation['blocking'] !== []) {
                    throw new \RuntimeException('Roster contains blocking conflicts: '.implode(', ', $validation['blocking']));
                }

                if ($validation['warnings'] !== [] && ! $acknowledgeWarnings) {
                    throw new \RuntimeException('Roster contains warnings that require acknowledgement.');
                }
            }

            foreach ($assignments as $assignment) {
                $before = $assignment->only(['status', 'published_at']);
                $assignment->forceFill([
                    'status' => WorkforceRosterAssignment::STATUS_PUBLISHED,
                    'published_at' => $assignment->published_at ?? now(),
                ])->save();

                WorkforceRosterHistory::query()->create([
                    'workforce_roster_period_id' => $locked->id,
                    'workforce_roster_assignment_id' => $assignment->id,
                    'employee_id' => $assignment->employee_id,
                    'event_type' => 'assignment_published',
                    'changed_by' => $userId,
                    'changed_at' => now(),
                    'before_values' => $before,
                    'after_values' => $assignment->only(['status', 'published_at']),
                    'attendance_recalculated' => $this->recalculateUnlockedAttendance($assignment),
                    'attendance_period_locked' => false,
                ]);
            }

            $locked->forceFill([
                'status' => WorkforceRosterPeriod::STATUS_PUBLISHED,
                'published_by' => $userId,
                'published_at' => now(),
            ])->save();

            $this->auditTrailService->recordGeneric('workforce_roster', 'roster_published', $locked, userId: $userId);

            return $locked->fresh(['assignments']);
        });
    }

    public function reopen(WorkforceRosterPeriod $period, int $userId, string $reason): WorkforceRosterPeriod
    {
        if (blank($reason)) {
            throw new \RuntimeException('A reopen reason is required.');
        }

        return DB::transaction(function () use ($period, $userId, $reason): WorkforceRosterPeriod {
            $locked = WorkforceRosterPeriod::query()->lockForUpdate()->findOrFail($period->id);
            if (! $locked->isPublishedLike()) {
                throw new \RuntimeException('Only published, active, or closed roster periods can be reopened.');
            }

            $locked->forceFill([
                'status' => WorkforceRosterPeriod::STATUS_REOPENED,
                'reopened_by' => $userId,
                'reopened_at' => now(),
                'reopen_reason' => $reason,
            ])->save();

            $this->auditTrailService->recordGeneric('workforce_roster', 'roster_reopened', $locked, userId: $userId, metadata: ['reason' => $reason]);

            return $locked->fresh();
        });
    }

    private function recalculateUnlockedAttendance(WorkforceRosterAssignment $assignment): bool
    {
        $day = EmployeeAttendanceDay::query()
            ->where('employee_id', $assignment->employee_id)
            ->whereDate('attendance_date', $assignment->work_date)
            ->first();

        if ($day?->isLocked()) {
            return false;
        }

        if ($assignment->employee === null) {
            return false;
        }

        $this->attendanceCalculationService->recalculate($assignment->employee, $assignment->work_date);

        return true;
    }
}
