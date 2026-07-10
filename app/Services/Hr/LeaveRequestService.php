<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\EmployeeLeaveLedgerEntry;
use App\Models\LeavePolicyRule;
use App\Models\LeaveRequest;
use App\Models\User;
use App\Services\AuditTrailService;
use Exception;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LeaveRequestService
{
    public function __construct(
        private readonly LeaveDurationService $durationService,
        private readonly AuditTrailService $auditTrailService,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(Employee $employee, array $data): LeaveRequest
    {
        return DB::transaction(function () use ($employee, $data): LeaveRequest {
            $quantity = $this->durationService->calculate(
                $data['start_date'],
                $data['end_date'],
                $data['start_part'] ?? 'full_day',
                $data['end_part'] ?? 'full_day',
                $data['business_id'] ?? null,
            );

            if ($quantity <= 0) {
                throw new Exception('Leave duration must be greater than zero working days.');
            }

            $request = LeaveRequest::query()->create([
                ...$data,
                'request_number' => $data['request_number'] ?? $this->generateRequestNumber(),
                'employee_id' => $employee->id,
                'requested_quantity' => $quantity,
                'status' => LeaveRequest::STATUS_DRAFT,
            ]);

            $this->durationService->assertNoOverlap($request);
            $this->recordAudit($request, 'leave_request_created', 'Leave request created.');

            return $request->fresh(['employee.department', 'leaveType']);
        });
    }

    public function submit(LeaveRequest $request, User $actor): LeaveRequest
    {
        return DB::transaction(function () use ($request, $actor): LeaveRequest {
            /** @var LeaveRequest $lockedRequest */
            $lockedRequest = LeaveRequest::query()->with(['leaveType', 'employee'])->lockForUpdate()->findOrFail($request->id);

            if ($lockedRequest->status !== LeaveRequest::STATUS_DRAFT) {
                return $lockedRequest->fresh(['employee.department', 'leaveType']);
            }

            $this->durationService->assertNoOverlap($lockedRequest);
            $this->assertAttachmentRules($lockedRequest);
            $this->assertBalanceAvailable($lockedRequest);

            $lockedRequest->forceFill([
                'status' => LeaveRequest::STATUS_SUBMITTED,
                'submitted_at' => now(),
            ])->save();

            $this->recordAudit($lockedRequest, 'leave_request_submitted', 'Leave request submitted.', $actor->id);

            return $lockedRequest->fresh(['employee.department', 'leaveType']);
        });
    }

    public function managerApprove(LeaveRequest $request, User $actor): LeaveRequest
    {
        return DB::transaction(function () use ($request, $actor): LeaveRequest {
            /** @var LeaveRequest $lockedRequest */
            $lockedRequest = LeaveRequest::query()->with(['employee.department.manager.user', 'leaveType'])->lockForUpdate()->findOrFail($request->id);

            if ($lockedRequest->status !== LeaveRequest::STATUS_SUBMITTED) {
                return $lockedRequest->fresh(['employee.department', 'leaveType']);
            }

            $this->assertNotSelfApproval($lockedRequest, $actor);

            $nextStatus = $lockedRequest->leaveType->requires_hr_approval
                ? LeaveRequest::STATUS_MANAGER_APPROVED
                : LeaveRequest::STATUS_APPROVED;

            $lockedRequest->forceFill([
                'status' => $nextStatus,
                'manager_approved_by' => $actor->id,
                'manager_approved_at' => now(),
                'approved_quantity' => $lockedRequest->requested_quantity,
            ])->save();

            $this->recordAudit($lockedRequest, 'leave_manager_approved', 'Leave request manager-approved.', $actor->id);

            if ($nextStatus === LeaveRequest::STATUS_APPROVED) {
                $this->postApprovedLeave($lockedRequest->fresh(), $actor);
            }

            return $lockedRequest->fresh(['employee.department', 'leaveType']);
        });
    }

    public function hrApprove(LeaveRequest $request, User $actor): LeaveRequest
    {
        return DB::transaction(function () use ($request, $actor): LeaveRequest {
            /** @var LeaveRequest $lockedRequest */
            $lockedRequest = LeaveRequest::query()->with(['employee.department', 'leaveType'])->lockForUpdate()->findOrFail($request->id);

            if (! in_array($lockedRequest->status, [LeaveRequest::STATUS_SUBMITTED, LeaveRequest::STATUS_MANAGER_APPROVED], true)) {
                return $lockedRequest->fresh(['employee.department', 'leaveType']);
            }

            $this->assertNotSelfApproval($lockedRequest, $actor);
            $this->assertBalanceAvailable($lockedRequest);

            $lockedRequest->forceFill([
                'status' => LeaveRequest::STATUS_APPROVED,
                'hr_approved_by' => $actor->id,
                'hr_approved_at' => now(),
                'approved_quantity' => $lockedRequest->requested_quantity,
                'payroll_review_required' => ! $lockedRequest->leaveType->paid,
                'payroll_impact_status' => $lockedRequest->leaveType->paid ? null : 'review_required',
            ])->save();

            $this->recordAudit($lockedRequest, 'leave_hr_approved', 'Leave request HR-approved.', $actor->id);
            $this->postApprovedLeave($lockedRequest->fresh(), $actor);

            return $lockedRequest->fresh(['employee.department', 'leaveType']);
        });
    }

    public function reject(LeaveRequest $request, User $actor, string $reason): LeaveRequest
    {
        if (blank($reason)) {
            throw new Exception('Rejection reason is required.');
        }

        $request->forceFill([
            'status' => LeaveRequest::STATUS_REJECTED,
            'rejected_by' => $actor->id,
            'rejected_at' => now(),
            'rejection_reason' => $reason,
        ])->save();

        $this->recordAudit($request, 'leave_request_rejected', 'Leave request rejected.', $actor->id);

        return $request->fresh(['employee.department', 'leaveType']);
    }

    public function cancel(LeaveRequest $request, User $actor, ?string $reason = null): LeaveRequest
    {
        return DB::transaction(function () use ($request, $actor, $reason): LeaveRequest {
            /** @var LeaveRequest $lockedRequest */
            $lockedRequest = LeaveRequest::query()->with('leaveType')->lockForUpdate()->findOrFail($request->id);

            if ($lockedRequest->status === LeaveRequest::STATUS_CANCELLED) {
                return $lockedRequest->fresh(['employee.department', 'leaveType']);
            }

            if ($lockedRequest->ledgerEntries()->where('entry_type', EmployeeLeaveLedgerEntry::TYPE_APPROVED_LEAVE)->exists()) {
                $this->reverseApprovedLeave($lockedRequest, $actor, $reason);
            }

            $lockedRequest->forceFill([
                'status' => LeaveRequest::STATUS_CANCELLED,
                'cancelled_by' => $actor->id,
                'cancelled_at' => now(),
            ])->save();

            $this->recordAudit($lockedRequest, 'leave_request_cancelled', 'Leave request cancelled.', $actor->id);

            return $lockedRequest->fresh(['employee.department', 'leaveType']);
        });
    }

    public function balance(int $employeeId, int $leaveTypeId, ?int $leaveYear = null): float
    {
        $leaveYear ??= (int) now()->year;

        return (float) EmployeeLeaveLedgerEntry::query()
            ->where('employee_id', $employeeId)
            ->where('leave_type_id', $leaveTypeId)
            ->where('leave_year', $leaveYear)
            ->sum('quantity');
    }

    public function postApprovedLeave(LeaveRequest $request, User $actor): void
    {
        /** @var LeaveRequest $lockedRequest */
        $lockedRequest = LeaveRequest::query()->with('leaveType')->lockForUpdate()->findOrFail($request->id);

        if ($lockedRequest->ledgerEntries()->where('entry_type', EmployeeLeaveLedgerEntry::TYPE_APPROVED_LEAVE)->exists()) {
            return;
        }

        $approvedQuantity = (float) ($lockedRequest->approved_quantity ?? $lockedRequest->requested_quantity);

        EmployeeLeaveLedgerEntry::query()->create([
            'employee_id' => $lockedRequest->employee_id,
            'leave_type_id' => $lockedRequest->leave_type_id,
            'leave_request_id' => $lockedRequest->id,
            'leave_year' => (int) $lockedRequest->start_date->year,
            'entry_type' => EmployeeLeaveLedgerEntry::TYPE_APPROVED_LEAVE,
            'quantity' => -abs($approvedQuantity),
            'posting_date' => now()->toDateString(),
            'description' => "Approved leave {$lockedRequest->request_number}",
            'reference_type' => LeaveRequest::class,
            'reference_id' => $lockedRequest->id,
            'created_by' => $actor->id,
        ]);

        $lockedRequest->forceFill(['status' => LeaveRequest::STATUS_POSTED])->save();
        $this->recordAudit($lockedRequest, 'leave_request_posted', 'Approved leave posted to leave ledger.', $actor->id);
    }

    private function reverseApprovedLeave(LeaveRequest $request, User $actor, ?string $reason): void
    {
        if ($request->ledgerEntries()->where('entry_type', EmployeeLeaveLedgerEntry::TYPE_REVERSAL)->exists()) {
            return;
        }

        $postedQuantity = (float) $request->ledgerEntries()
            ->where('entry_type', EmployeeLeaveLedgerEntry::TYPE_APPROVED_LEAVE)
            ->sum('quantity');

        if ($postedQuantity === 0.0) {
            return;
        }

        EmployeeLeaveLedgerEntry::query()->create([
            'employee_id' => $request->employee_id,
            'leave_type_id' => $request->leave_type_id,
            'leave_request_id' => $request->id,
            'leave_year' => (int) $request->start_date->year,
            'entry_type' => EmployeeLeaveLedgerEntry::TYPE_REVERSAL,
            'quantity' => abs($postedQuantity),
            'posting_date' => now()->toDateString(),
            'description' => $reason ?: "Leave cancellation reversal {$request->request_number}",
            'reference_type' => LeaveRequest::class,
            'reference_id' => $request->id,
            'created_by' => $actor->id,
        ]);
    }

    private function assertBalanceAvailable(LeaveRequest $request): void
    {
        $request->loadMissing('leaveType');

        if ($request->leaveType->allow_negative_balance || $this->policyRuleAllowsNegativeBalance($request)) {
            return;
        }

        $balance = $this->balance($request->employee_id, $request->leave_type_id, (int) $request->start_date->year);
        if ($balance < (float) $request->requested_quantity) {
            throw new Exception('Insufficient leave balance for this request.');
        }
    }

    private function assertAttachmentRules(LeaveRequest $request): void
    {
        $request->loadMissing('leaveType');
        $threshold = $request->leaveType->attachment_required_after_days;
        $requiresAttachment = $request->leaveType->requires_attachment
            || ($threshold !== null && (float) $request->requested_quantity >= (float) $threshold);

        if ($requiresAttachment && blank($request->attachment_path)) {
            throw new Exception('Supporting attachment is required for this leave request.');
        }
    }

    private function assertNotSelfApproval(LeaveRequest $request, User $actor): void
    {
        if ($actor->employee_id !== null && (int) $actor->employee_id === (int) $request->employee_id) {
            throw new Exception('Users cannot approve their own leave request.');
        }
    }

    private function policyRuleAllowsNegativeBalance(LeaveRequest $request): bool
    {
        return LeavePolicyRule::query()
            ->where('leave_type_id', $request->leave_type_id)
            ->where('allow_negative_balance', true)
            ->whereHas('policy', fn ($query) => $query->where('is_active', true))
            ->exists();
    }

    private function generateRequestNumber(): string
    {
        do {
            $number = 'LV-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (LeaveRequest::query()->where('request_number', $number)->exists());

        return $number;
    }

    private function recordAudit(LeaveRequest $request, string $action, string $description, ?int $userId = null): void
    {
        $this->auditTrailService->recordGeneric(
            eventType: 'leave',
            action: $action,
            auditable: $request,
            documentType: 'LEAVE_REQUEST',
            documentNo: $request->request_number,
            userId: $userId ?? Auth::id(),
            description: $description,
            metadata: [
                'employee_id' => $request->employee_id,
                'leave_type_id' => $request->leave_type_id,
                'status' => $request->status,
                'requested_quantity' => $request->requested_quantity,
                'approved_quantity' => $request->approved_quantity,
            ],
        );
    }
}
