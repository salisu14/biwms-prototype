<?php

namespace App\Services\Workflow;

use App\Enums\ApprovalStatus;
use App\Services\AuditTrailService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DocumentApprovalWorkflowService
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService
    ) {}

    public function submit(Model $document, ?int $userId = null): Model
    {
        return $this->transition(
            document: $document,
            allowedStatuses: ['draft', 'rejected', 'PENDING', 'Open', 'PLANNED'],
            targetStatus: $this->submittedStatusFor($document),
            action: 'submitted',
            userId: $userId
        );
    }

    public function approve(Model $document, ?int $userId = null): Model
    {
        return $this->transition(
            document: $document,
            allowedStatuses: ['submitted', 'pending', 'PENDING_APPROVAL', 'FIRM_PLANNED', 'Submitted', 'SUBMITTED'],
            targetStatus: $this->approvedStatusFor($document),
            action: 'approved',
            userId: $userId,
            extraAttributes: [
                'approved_by' => $userId,
                'approved_at' => now(),
            ]
        );
    }

    public function reject(Model $document, ?int $userId = null): Model
    {
        return $this->transition(
            document: $document,
            allowedStatuses: ['submitted', 'pending', 'PENDING_APPROVAL', 'FIRM_PLANNED', 'Submitted', 'SUBMITTED'],
            targetStatus: $this->rejectedStatusFor($document),
            action: 'rejected',
            userId: $userId
        );
    }

    public function reopen(Model $document, ?int $userId = null): Model
    {
        return $this->transition(
            document: $document,
            allowedStatuses: ['submitted', 'pending', 'approved', 'rejected', 'PENDING_APPROVAL', 'Released', 'FIRM_PLANNED', 'RELEASED', 'Submitted', 'SUBMITTED', 'APPROVED'],
            targetStatus: $this->draftStatusFor($document),
            action: 'reopened',
            userId: $userId,
            extraAttributes: [
                'approved_by' => null,
                'approved_at' => null,
            ]
        );
    }

    public function cancel(Model $document, ?int $userId = null): Model
    {
        return $this->transition(
            document: $document,
            allowedStatuses: ['draft', 'submitted', 'pending', 'approved', 'PENDING', 'SUBMITTED', 'APPROVED', 'PENDING_APPROVAL', 'Open', 'Released', 'PLANNED', 'FIRM_PLANNED', 'RELEASED'],
            targetStatus: $this->cancelledStatusFor($document),
            action: 'cancelled',
            userId: $userId
        );
    }

    public function ensureApprovedForPosting(Model $document): void
    {
        if (! $this->isApproved($document)) {
            throw new \RuntimeException('Only approved documents can be posted.');
        }
    }

    public function isDraft(Model $document): bool
    {
        return in_array($this->statusValue($document), ['draft', 'PENDING', 'Open', 'PLANNED'], true);
    }

    public function isSubmitted(Model $document): bool
    {
        return in_array($this->statusValue($document), ['submitted', 'pending', 'PENDING_APPROVAL', 'Submitted', 'SUBMITTED', 'FIRM_PLANNED'], true);
    }

    public function isApproved(Model $document): bool
    {
        return in_array($this->statusValue($document), ['approved', 'APPROVED', 'Released', 'RELEASED'], true);
    }

    public function isPosted(Model $document): bool
    {
        return in_array($this->statusValue($document), ['posted', 'POSTED', 'Posted', 'FINISHED'], true)
            || (bool) ($document->getAttribute('posted_at') ?? false)
            || (bool) ($document->getAttribute('posted') ?? false);
    }

    /**
     * @param  array<int, string>  $allowedStatuses
     * @param  array<string, mixed>  $extraAttributes
     */
    private function transition(Model $document, array $allowedStatuses, mixed $targetStatus, string $action, ?int $userId = null, array $extraAttributes = []): Model
    {
        return DB::transaction(function () use ($document, $allowedStatuses, $targetStatus, $action, $userId, $extraAttributes): Model {
            /** @var Model $lockedDocument */
            $lockedDocument = $document->newQuery()->lockForUpdate()->findOrFail($document->getKey());
            $oldStatus = $this->statusValue($lockedDocument);

            if ($this->isPosted($lockedDocument)) {
                throw new \RuntimeException('Posted documents are immutable.');
            }

            if (! in_array($oldStatus, $allowedStatuses, true)) {
                throw new \RuntimeException("Document cannot be {$action} from status {$oldStatus}.");
            }

            $lockedDocument->forceFill($this->existingAttributes($lockedDocument, ['status' => $targetStatus, ...$extraAttributes]))->save();
            $newStatus = $this->statusValue($lockedDocument);

            $this->auditTrailService->recordApproval(
                auditable: $lockedDocument,
                action: $action,
                userId: $userId,
                metadata: [
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                ]
            );

            return $lockedDocument->fresh();
        });
    }

    private function statusValue(Model $document): string
    {
        $status = $document->getAttribute('status');

        return $status instanceof \BackedEnum ? (string) $status->value : (string) $status;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function existingAttributes(Model $document, array $attributes): array
    {
        return collect($attributes)
            ->filter(fn (mixed $value, string $attribute): bool => Schema::hasColumn($document->getTable(), $attribute))
            ->all();
    }

    private function draftStatusFor(Model $document): mixed
    {
        return match (class_basename($document)) {
            'InventoryAdjustmentJournal' => 'Open',
            'Payment' => 'PENDING',
            'ProductionOrder' => 'PLANNED',
            default => ApprovalStatus::DRAFT,
        };
    }

    private function submittedStatusFor(Model $document): mixed
    {
        return match (class_basename($document)) {
            'InventoryAdjustmentJournal' => 'Submitted',
            'Payment' => 'SUBMITTED',
            'ProductionOrder' => 'FIRM_PLANNED',
            default => ApprovalStatus::PENDING,
        };
    }

    private function approvedStatusFor(Model $document): mixed
    {
        return match (class_basename($document)) {
            'InventoryAdjustmentJournal' => 'Released',
            'Payment' => 'APPROVED',
            'ProductionOrder' => 'RELEASED',
            default => ApprovalStatus::APPROVED,
        };
    }

    private function rejectedStatusFor(Model $document): mixed
    {
        return match (class_basename($document)) {
            'InventoryAdjustmentJournal' => 'Open',
            'Payment' => 'PENDING',
            'ProductionOrder' => 'PLANNED',
            default => ApprovalStatus::REJECTED,
        };
    }

    private function cancelledStatusFor(Model $document): mixed
    {
        return match (class_basename($document)) {
            'Payment' => 'VOIDED',
            'InventoryAdjustmentJournal' => 'Cancelled',
            'ProductionOrder' => 'CANCELLED',
            default => ApprovalStatus::CANCELLED,
        };
    }
}
