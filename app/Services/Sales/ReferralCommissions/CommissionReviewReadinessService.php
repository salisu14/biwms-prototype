<?php

declare(strict_types=1);

namespace App\Services\Sales\ReferralCommissions;

use App\Enums\CommissionReadinessClassification;
use App\Enums\CommissionReviewPeriodStatus;
use App\Models\CommissionLedgerEntry;
use App\Models\CommissionReviewPeriod;

class CommissionReviewReadinessService
{
    /**
     * @return array<int, array{code: string, classification: string, message: string}>
     */
    public function findingsFor(CommissionLedgerEntry $entry, CommissionReviewPeriod $period, string $cutoffDate): array
    {
        $findings = [];
        if ($period->status !== CommissionReviewPeriodStatus::Open && $period->status !== CommissionReviewPeriodStatus::UnderReview && $period->status !== CommissionReviewPeriodStatus::Approved) {
            $findings[] = $this->finding('period_not_open', CommissionReadinessClassification::UserActionRequired, 'Review period is not open for generation.');
        }
        if ($entry->reviewLines()->exists()) {
            $findings[] = $this->finding('ledger_entry_already_reviewed', CommissionReadinessClassification::IntegrityProblem, 'Ledger entry is already in a review batch.');
        }
        if ($entry->settlementAllocations()->exists()) {
            $findings[] = $this->finding('already_allocated', CommissionReadinessClassification::IntegrityProblem, 'Ledger entry is already allocated to settlement preparation.');
        }
        if ($entry->posting_date?->toDateString() > $cutoffDate) {
            $findings[] = $this->finding('maturity_period_pending', CommissionReadinessClassification::Waiting, 'Ledger entry posting date is after the cutoff date.');
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
