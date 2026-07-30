<?php

declare(strict_types=1);

namespace App\Services\Sales\ReferralCommissions;

use App\Enums\CommissionReadinessClassification;
use App\Enums\CommissionReviewBatchStatus;
use App\Models\CommissionReviewBatch;

class CommissionSettlementReadinessService
{
    /**
     * @return array<int, array{code: string, classification: string, message: string}>
     */
    public function findingsFor(CommissionReviewBatch $batch): array
    {
        $findings = [];
        if (! in_array($batch->status, [CommissionReviewBatchStatus::Approved, CommissionReviewBatchStatus::Locked], true)) {
            $findings[] = $this->finding('review_batch_not_approved', CommissionReadinessClassification::UserActionRequired, 'Review batch must be approved before settlement preparation.');
        }
        if ($batch->holds()->where('status', 'active')->exists()) {
            $findings[] = $this->finding('active_hold', CommissionReadinessClassification::UserActionRequired, 'Review batch has active holds.');
        }
        if ($batch->disputes()->whereIn('status', ['open', 'under_review', 'awaiting_information'])->exists()) {
            $findings[] = $this->finding('open_dispute', CommissionReadinessClassification::UserActionRequired, 'Review batch has open disputes.');
        }

        return $findings;
    }

    private function finding(string $code, CommissionReadinessClassification $classification, string $message): array
    {
        return compact('code') + [
            'classification' => $classification->value,
            'message' => $message,
        ];
    }
}
