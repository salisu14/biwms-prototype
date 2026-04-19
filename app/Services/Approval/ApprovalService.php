<?php

declare(strict_types=1);

namespace App\Services\Approval;

use App\Contracts\Approvable;
use App\Models\ApprovalEntry;
use App\Models\User;
use App\Notifications\ApprovalRequested;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Access\AuthorizationException;

class ApprovalService
{
    public function __construct(
        private readonly ApprovalTemplateService $templateService
    ) {}

    /**
     * Submit a document for approval.
     * Super admins bypass the approval chain entirely.
     * @throws \Throwable
     */
    public function submitForApproval(Approvable $model): void
    {
        // Super Admin Bypass
        if (Auth::user()?->hasRole('super_admin')) {
            $this->release($model);

            return;
        }

        DB::transaction(function () use ($model) {
            $approvers = $this->templateService->getApproversForDocument($model);

            if (empty($approvers)) {
                $this->release($model);

                return;
            }

            // Update model status
            $this->updateModelStatus($model, 'PENDING_APPROVAL');

            // Create approval entries
            foreach ($approvers as $index => $approver) {
                $model->approvalEntries()->create([
                    'sequence_no' => $index + 1,
                    'approver_id' => $approver->id,
                    'status' => 'created',
                ]);
            }

            // Notify first approver
            $firstEntry = $model->approvalEntries()
                ->where('status', 'created')
                ->orderBy('sequence_no')
                ->first();

            if ($firstEntry) {
                $this->sendNotification($firstEntry);
            }
        });
    }

    /**
     * Approve an entry.
     */
    public function approve(ApprovalEntry $entry, ?string $comment = null): void
    {
        // Ensure caller is allowed to approve this entry
        $currentId = Auth::id();
        if (! Auth::user()?->hasRole('super_admin')) {
            if ($entry->approver_id !== $currentId && $entry->delegated_to !== $currentId) {
                throw new AuthorizationException('Not authorized to approve this entry.');
            }
        }

        if ($entry->status !== 'created') {
            throw new \RuntimeException('Approval entry is not in a state that can be approved.');
        }

        DB::transaction(function () use ($entry, $comment) {
            $entry->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => Auth::id(),
                'comment' => $comment,
            ]);

            /** @var Approvable $model */
            $model = $entry->approvable;

            $hasPending = $model->approvalEntries()
                ->where('status', 'created')
                ->exists();

            if (! $hasPending) {
                $this->release($model);
            } else {
                $nextEntry = $model->approvalEntries()
                    ->where('status', 'created')
                    ->orderBy('sequence_no')
                    ->first();

                if ($nextEntry) {
                    $this->sendNotification($nextEntry);
                }
            }
        });
    }

    /**
     * Reject an entry.
     */
    public function reject(ApprovalEntry $entry, string $reason): void
    {
        // Ensure caller is allowed to reject this entry
        $currentId = Auth::id();
        if (! Auth::user()?->hasRole('super_admin')) {
            if ($entry->approver_id !== $currentId && $entry->delegated_to !== $currentId) {
                throw new AuthorizationException('Not authorized to reject this entry.');
            }
        }

        if ($entry->status !== 'created') {
            throw new \RuntimeException('Approval entry is not in a state that can be rejected.');
        }

        DB::transaction(function () use ($entry, $reason) {
            $entry->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejected_by' => Auth::id(),
                'comment' => $reason,
            ]);

            $this->updateModelStatus($entry->approvable, 'OPEN');
        });
    }

    /**
     * Cancel an approval request.
     */
    public function cancelRequest(Approvable $model): void
    {
        DB::transaction(function () use ($model) {
            $model->approvalEntries()->where('status', 'created')->delete();
            $this->updateModelStatus($model, 'OPEN');
        });
    }

    /**
     * Delegate an approval to another user.
     */
    public function delegate(ApprovalEntry $entry, User $delegatee): void
    {
        $entry->update([
            'delegated_to' => $delegatee->id,
            'delegated_at' => now(),
            'status' => 'delegated',
        ]);

        // Create a replacement entry for the delegatee
        $newEntry = $entry->replicate();
        $newEntry->approver_id = $delegatee->id;
        $newEntry->status = 'created';
        $newEntry->delegated_to = null;
        $newEntry->delegated_at = null;
        $newEntry->save();

        $this->sendNotification($newEntry);
    }

    /**
     * Release the document (Final Approval / Auto-release).
     */
    public function release(Approvable $model): void
    {
        if (method_exists($model, 'markAsReleased')) {
            $model->markAsReleased();
        } else {
            $this->updateModelStatus($model, 'RELEASED');

            $model->updateQuietly([
                'released_at' => now(),
                'released_by' => Auth::id(),
            ]);
        }
    }

    /**
     * Resolve the correct enum value for a given state key and update the model.
     */
    private function updateModelStatus(Approvable $model, string $stateKey): void
    {
        $casts = $model->getCasts();
        $statusEnum = $casts['status'] ?? null;

        if ($statusEnum && enum_exists($statusEnum)) {
            // Use the enum constant (e.g., PurchaseQuoteStatus::PENDING_APPROVAL)
            $model->update(['status' => constant("{$statusEnum}::{$stateKey}")]);
        } else {
            $model->update(['status' => strtolower($stateKey)]);
        }
    }

    /**
     * Send a generic approval notification.
     */
    private function sendNotification(ApprovalEntry $entry): void
    {
        Notification::send($entry->approver, new ApprovalRequested($entry));
    }
}

