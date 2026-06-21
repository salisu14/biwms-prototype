<?php

declare(strict_types=1);

namespace App\Contracts;

use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;

interface Approvable
{
    /**
     * Get the approval entries for the model.
     */
    public function approvalEntries(): MorphMany;

    /**
     * Get the current pending approval entry.
     */
    public function currentApprovalEntry(): MorphOne;

    /**
     * Get the amount to be used for approval limits.
     */
    public function getApprovalAmount(): float;

    /**
     * Get the document type for approval template matching.
     */
    public function getApprovalDocumentType(): string;

    /**
     * Get the location code for filtering.
     */
    public function getApprovalLocationCode(): ?string;

    /**
     * Get the dimensions for filtering.
     */
    public function getApprovalDimensions(): array;

    /**
     * Get the requestor (user who submitted the document).
     */
    public function getApprovalRequestorId(): int;

    /**
     * Get the vendor/customer posting group ID for filtering.
     */
    public function getApprovalPostingGroupId(): ?int;
}
