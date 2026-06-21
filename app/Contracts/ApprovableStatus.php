<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Interface for status enums used on Approvable models.
 *
 * Any status enum on a model implementing Approvable should implement this
 * interface so the ApprovalService can interact with statuses generically.
 */
interface ApprovableStatus
{
    /**
     * Whether the document can be submitted for approval.
     */
    public function canSubmitForApproval(): bool;

    /**
     * Whether the document can be edited in this state.
     */
    public function canEdit(): bool;

    /**
     * Whether the document is currently pending approval.
     */
    public function isPendingApproval(): bool;

    /**
     * Whether the document has been released/approved.
     */
    public function isReleased(): bool;
}
