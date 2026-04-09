<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Enums\PurchaseQuoteStatus;
use App\Models\PurchaseQuote;
use App\Models\PurchaseQuoteApprovalEntry;
use App\Models\User;
use App\Notifications\QuoteApprovalDelegated;
use App\Notifications\QuoteApprovalRequested;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

class PurchaseQuoteApprovalWorkflowService
{
    public function __construct(
        private readonly ApprovalTemplateService $templateService
    ) {}

    /**
     * Submit quote for approval (BC: Send Approval Request)
     */
    public function submitForApproval(PurchaseQuote $quote): void
    {
        if (! $quote->status->canSubmitForApproval()) {
            throw new \InvalidArgumentException('Quote cannot be submitted for approval');
        }

        DB::transaction(function () use ($quote) {
            // Create approval entries based on template
            $approvers = $this->templateService->getApproversForQuote($quote);

            if (empty($approvers)) {
                // No approval required - auto-release
                $this->release($quote);

                return;
            }

            $quote->update(['status' => PurchaseQuoteStatus::PENDING_APPROVAL]);

            foreach ($approvers as $sequence => $approver) {
                PurchaseQuoteApprovalEntry::create([
                    'purchase_quote_id' => $quote->id,
                    'sequence_no' => $sequence + 1,
                    'approver_id' => $approver->id,
                    'status' => 'created',
                ]);
            }

            // Notify first approver
            $firstApprover = $approvers[0];
            Notification::send($firstApprover, new QuoteApprovalRequested($quote));
        });
    }

    /**
     * Approve quote (BC: Approve)
     */
    public function approve(PurchaseQuoteApprovalEntry $entry, ?string $comment = null): void
    {
        DB::transaction(function () use ($entry, $comment) {
            $entry->update([
                'status' => 'approved',
                'approved_at' => now(),
                'approved_by' => Auth::id(),
                'comment' => $comment,
            ]);

            $quote = $entry->purchaseQuote;

            // Check if all approvals complete
            $pendingCount = $quote->approvalEntries()
                ->where('status', 'created')
                ->count();

            if ($pendingCount === 0) {
                $this->release($quote);
            } else {
                // Notify next approver
                $nextEntry = $quote->approvalEntries()
                    ->where('status', 'created')
                    ->orderBy('sequence_no')
                    ->first();

                Notification::send($nextEntry->approver, new QuoteApprovalRequested($quote));
            }
        });
    }

    /**
     * Reject quote (BC: Reject)
     */
    public function reject(PurchaseQuoteApprovalEntry $entry, string $reason): void
    {
        $entry->update([
            'status' => 'rejected',
            'rejected_at' => now(),
            'rejected_by' => Auth::id(),
            'comment' => $reason,
        ]);

        $entry->purchaseQuote->update([
            'status' => PurchaseQuoteStatus::OPEN,
        ]);
    }

    /**
     * Release quote (BC: Release)
     */
    public function release(PurchaseQuote $quote): void
    {
        if (! $quote->status->canRelease()) {
            throw new \InvalidArgumentException('Quote cannot be released');
        }

        if ($quote->lines()->count() === 0) {
            throw new \InvalidArgumentException('Cannot release quote without lines');
        }

        $quote->update([
            'status' => PurchaseQuoteStatus::RELEASED,
            'released_at' => now(),
            'released_by' => Auth::id(),
        ]);
    }

    /**
     * Reopen quote (BC: Reopen)
     */
    public function reopen(PurchaseQuote $quote): void
    {
        if (! $quote->status->canReopen()) {
            throw new \InvalidArgumentException('Only released quotes can be reopened');
        }

        // BC Check: Has any line been partially processed?
        $processedLines = $quote->lines()
            ->where(function ($q) {
                $q->where('quantity_received', '>', 0)
                    ->orWhereNotNull('purchase_order_line_id');
            })
            ->exists();

        if ($processedLines) {
            throw new \InvalidArgumentException(
                'Cannot reopen quote - some lines have been processed'
            );
        }

        $quote->update([
            'status' => PurchaseQuoteStatus::OPEN,
            'released_at' => null,
            'released_by' => null,
        ]);
    }

    /**
     * Delegate approval (BC: Delegate)
     */
    public function delegate(PurchaseQuoteApprovalEntry $entry, User $delegatee): void
    {
        $entry->update([
            'delegated_to' => $delegatee->id,
            'delegated_at' => now(),
        ]);

        Notification::send($delegatee, new QuoteApprovalDelegated($entry->purchaseQuote));
    }
}
