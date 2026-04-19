<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\ApprovalEntry;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait Approvable
{
    /**
     * Get the approval entries for the model.
     */
    public function approvalEntries(): MorphMany
    {
        return $this->morphMany(ApprovalEntry::class, 'approvable')->orderBy('sequence_no');
    }

    /**
     * Get the current pending approval entry.
     */
    public function currentApprovalEntry(): MorphOne
    {
        return $this->morphOne(ApprovalEntry::class, 'approvable')
            ->where('status', 'created')
            ->orderBy('sequence_no');
    }

    /**
     * Check if the document is pending approval.
     */
    public function isPendingApproval(): bool
    {
        return $this->approvalEntries()->where('status', 'created')->exists();
    }

    /**
     * Default implementation for getApprovalAmount.
     */
    public function getApprovalAmount(): float
    {
        return (float) ($this->amount_including_vat ?? $this->total_amount ?? 0);
    }

    /**
     * Default implementation for getApprovalLocationCode.
     */
    public function getApprovalLocationCode(): ?string
    {
        return $this->location_code ?? null;
    }

    /**
     * Default implementation for getApprovalDimensions.
     */
    public function getApprovalDimensions(): array
    {
        return $this->dimensions ?? [];
    }

    /**
     * Default implementation for getApprovalRequestorId.
     */
    public function getApprovalRequestorId(): int
    {
        return (int) ($this->buyer_id ?? $this->created_by ?? auth()->id());
    }

    /**
     * Default implementation for getApprovalPostingGroupId.
     */
    public function getApprovalPostingGroupId(): ?int
    {
        return $this->vendor_posting_group_id ?? null;
    }
}
