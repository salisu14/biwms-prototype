<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\WorkforceRosterAssignment;
use App\Models\WorkforceRosterHistory;
use App\Models\WorkforceShiftReplacement;
use App\Services\AuditTrailService;
use Illuminate\Support\Facades\DB;

class WorkforceShiftReplacementService
{
    public function __construct(
        private readonly WorkforceScheduleValidationService $validator,
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function propose(WorkforceRosterAssignment $assignment, int $replacementEmployeeId, int $proposedByUserId, string $reason, string $type = 'manager_reassignment'): WorkforceShiftReplacement
    {
        if ($assignment->status === WorkforceRosterAssignment::STATUS_CANCELLED) {
            throw new \RuntimeException('Cancelled assignments cannot be replaced.');
        }

        return WorkforceShiftReplacement::query()->create([
            'original_roster_assignment_id' => $assignment->id,
            'original_employee_id' => $assignment->employee_id,
            'replacement_employee_id' => $replacementEmployeeId,
            'replacement_type' => $type,
            'reason' => $reason,
            'status' => WorkforceShiftReplacement::STATUS_PROPOSED,
            'proposed_by' => $proposedByUserId,
        ]);
    }

    public function approve(WorkforceShiftReplacement $replacement, int $approverUserId): WorkforceShiftReplacement
    {
        return DB::transaction(function () use ($replacement, $approverUserId): WorkforceShiftReplacement {
            $locked = WorkforceShiftReplacement::query()->lockForUpdate()->findOrFail($replacement->id);
            $original = WorkforceRosterAssignment::query()->lockForUpdate()->findOrFail($locked->original_roster_assignment_id);

            $payload = [
                ...$this->assignmentPayload($original),
                'employee_id' => $locked->replacement_employee_id,
                'assignment_type' => WorkforceRosterAssignment::TYPE_REPLACEMENT,
                'status' => WorkforceRosterAssignment::STATUS_PUBLISHED,
                'original_assignment_id' => $original->id,
                'assigned_by' => $approverUserId,
                'published_at' => now(),
                'may_create_overtime' => $locked->may_create_overtime,
            ];

            $validation = $this->validator->validateAssignment($payload);
            if ($validation['blocking'] !== []) {
                throw new \RuntimeException('Replacement has blocking conflicts: '.implode(', ', $validation['blocking']));
            }

            $replacementAssignment = WorkforceRosterAssignment::query()->create($payload);
            $original->forceFill([
                'status' => WorkforceRosterAssignment::STATUS_REPLACED,
                'replaced_by_assignment_id' => $replacementAssignment->id,
            ])->save();

            $locked->forceFill([
                'replacement_roster_assignment_id' => $replacementAssignment->id,
                'status' => WorkforceShiftReplacement::STATUS_APPROVED,
                'approved_by' => $approverUserId,
                'approved_at' => now(),
            ])->save();

            WorkforceRosterHistory::query()->create([
                'workforce_roster_period_id' => $original->workforce_roster_period_id,
                'workforce_roster_assignment_id' => $replacementAssignment->id,
                'employee_id' => $replacementAssignment->employee_id,
                'event_type' => 'replacement_approved',
                'changed_by' => $approverUserId,
                'changed_at' => now(),
                'before_values' => $original->toArray(),
                'after_values' => $replacementAssignment->toArray(),
                'reason' => $locked->reason,
            ]);

            $this->auditTrailService->recordGeneric('workforce_roster', 'shift_replacement_approved', $locked, userId: $approverUserId);

            return $locked->fresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function assignmentPayload(WorkforceRosterAssignment $assignment): array
    {
        return $assignment->only([
            'workforce_roster_period_id',
            'work_date',
            'employee_shift_id',
            'attendance_location_id',
            'department_id',
            'work_center_id',
            'roster_role_id',
            'source_reference_type',
            'source_reference_id',
            'expected_start_at',
            'expected_end_at',
            'break_minutes',
            'conflict_status',
            'conflict_details',
            'forecast_overtime_minutes',
            'metadata',
        ]);
    }
}
