<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\WorkforceRosterAssignment;
use App\Models\WorkforceRosterHistory;
use App\Models\WorkforceShiftSwapRequest;
use App\Services\AuditTrailService;
use Illuminate\Support\Facades\DB;

class WorkforceShiftSwapService
{
    public function __construct(
        private readonly WorkforceScheduleValidationService $validator,
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function submit(WorkforceRosterAssignment $assignment, int $requesterEmployeeId, string $reason, ?int $targetEmployeeId = null, ?int $targetAssignmentId = null): WorkforceShiftSwapRequest
    {
        if ((int) $assignment->employee_id !== $requesterEmployeeId) {
            throw new \RuntimeException('Requester must own the roster assignment.');
        }

        if (! $assignment->period?->isPublishedLike()) {
            throw new \RuntimeException('Shift swaps require a published roster assignment.');
        }

        return DB::transaction(function () use ($assignment, $requesterEmployeeId, $reason, $targetEmployeeId, $targetAssignmentId): WorkforceShiftSwapRequest {
            $request = WorkforceShiftSwapRequest::query()->create([
                'requester_employee_id' => $requesterEmployeeId,
                'requester_roster_assignment_id' => $assignment->id,
                'target_employee_id' => $targetEmployeeId,
                'target_roster_assignment_id' => $targetAssignmentId,
                'swap_type' => $targetAssignmentId ? 'direct_swap' : 'open_swap',
                'reason' => $reason,
                'status' => $targetEmployeeId ? WorkforceShiftSwapRequest::STATUS_AWAITING_EMPLOYEE_ACCEPTANCE : WorkforceShiftSwapRequest::STATUS_MANAGER_REVIEW,
            ]);

            $this->auditTrailService->recordGeneric('workforce_roster', 'shift_swap_submitted', $request, metadata: ['assignment_id' => $assignment->id]);

            return $request;
        });
    }

    public function accept(WorkforceShiftSwapRequest $request, int $acceptedByUserId): WorkforceShiftSwapRequest
    {
        $request->forceFill([
            'status' => WorkforceShiftSwapRequest::STATUS_ACCEPTED_BY_EMPLOYEE,
            'accepted_by' => $acceptedByUserId,
            'accepted_at' => now(),
        ])->save();

        $this->auditTrailService->recordGeneric('workforce_roster', 'shift_swap_accepted', $request, userId: $acceptedByUserId);

        return $request->fresh();
    }

    public function approve(WorkforceShiftSwapRequest $request, int $approverUserId): WorkforceShiftSwapRequest
    {
        return DB::transaction(function () use ($request, $approverUserId): WorkforceShiftSwapRequest {
            $locked = WorkforceShiftSwapRequest::query()->lockForUpdate()->findOrFail($request->id);
            if ((int) $locked->accepted_by === $approverUserId) {
                throw new \RuntimeException('A user cannot approve their own accepted shift swap.');
            }

            $assignment = WorkforceRosterAssignment::query()->lockForUpdate()->findOrFail($locked->requester_roster_assignment_id);
            $replacementEmployeeId = $locked->target_employee_id;
            if (! $replacementEmployeeId) {
                throw new \RuntimeException('Approved shift swap requires a target employee.');
            }

            $payload = [
                ...$this->assignmentPayload($assignment),
                'employee_id' => $replacementEmployeeId,
                'assignment_type' => WorkforceRosterAssignment::TYPE_SWAPPED,
                'status' => WorkforceRosterAssignment::STATUS_PUBLISHED,
                'original_assignment_id' => $assignment->id,
                'assigned_by' => $approverUserId,
                'published_at' => now(),
            ];

            $validation = $this->validator->validateAssignment($payload);
            if ($validation['blocking'] !== []) {
                throw new \RuntimeException('Shift swap target has blocking conflicts: '.implode(', ', $validation['blocking']));
            }

            $replacement = WorkforceRosterAssignment::query()->create($payload);
            $assignment->forceFill([
                'status' => WorkforceRosterAssignment::STATUS_REPLACED,
                'replaced_by_assignment_id' => $replacement->id,
            ])->save();

            $locked->forceFill([
                'status' => WorkforceShiftSwapRequest::STATUS_APPROVED,
                'approved_by' => $approverUserId,
                'approved_at' => now(),
            ])->save();

            WorkforceRosterHistory::query()->create([
                'workforce_roster_period_id' => $assignment->workforce_roster_period_id,
                'workforce_roster_assignment_id' => $replacement->id,
                'employee_id' => $replacement->employee_id,
                'event_type' => 'shift_swap_approved',
                'changed_by' => $approverUserId,
                'changed_at' => now(),
                'before_values' => $assignment->toArray(),
                'after_values' => $replacement->toArray(),
                'reason' => $locked->reason,
            ]);

            $this->auditTrailService->recordGeneric('workforce_roster', 'shift_swap_approved', $locked, userId: $approverUserId);

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
