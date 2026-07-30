<?php

declare(strict_types=1);

namespace App\Services\Sales\ReferralCommissions;

use App\Enums\CommissionHoldStatus;
use App\Enums\CommissionLedgerEntryStatus;
use App\Enums\CommissionLedgerEntryType;
use App\Enums\CommissionReviewLineStatus;
use App\Enums\CommissionSettlementBatchStatus;
use App\Models\CommissionHold;
use App\Models\CommissionLedgerEntry;
use App\Models\CommissionReviewBatchLine;
use App\Models\CommissionSettlementAllocation;
use App\Support\DecimalMath;
use Illuminate\Database\Eloquent\Builder;

class ReferrerCommissionApprovalBalanceService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, array<string, string>>
     */
    public function balances(array $filters = []): array
    {
        $currencies = CommissionLedgerEntry::query()
            ->when($filters['business_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('business_id', $id))
            ->when($filters['referrer_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('referrer_id', $id))
            ->distinct()
            ->pluck('currency_code')
            ->filter()
            ->values();

        $result = [];
        foreach ($currencies as $currency) {
            $result[$currency] = [
                'open_accrual' => $this->ledgerSum($filters, $currency, [CommissionLedgerEntryType::Accrual], [CommissionLedgerEntryStatus::Open]),
                'under_review' => $this->reviewLineSum($filters, $currency, [CommissionReviewLineStatus::Eligible, CommissionReviewLineStatus::Pending]),
                'held' => $this->holdSum($filters, $currency),
                'disputed' => $this->reviewLineSum($filters, $currency, [CommissionReviewLineStatus::Disputed]),
                'forfeited' => $this->ledgerSum($filters, $currency, [CommissionLedgerEntryType::Forfeiture], [CommissionLedgerEntryStatus::Forfeited]),
                'approved' => $this->reviewLineSum($filters, $currency, [CommissionReviewLineStatus::Approved]),
                'allocated_to_settlement' => $this->allocationSum($filters, $currency, false),
                'locked_for_future_payment' => $this->allocationSum($filters, $currency, true),
                'net_unallocated' => DecimalMath::sub(
                    $this->ledgerSum($filters, $currency, [CommissionLedgerEntryType::Accrual, CommissionLedgerEntryType::Adjustment, CommissionLedgerEntryType::Reversal, CommissionLedgerEntryType::Forfeiture], [CommissionLedgerEntryStatus::Open, CommissionLedgerEntryStatus::Forfeited]),
                    $this->allocationSum($filters, $currency, false),
                    4,
                ),
            ];
        }

        return $result;
    }

    private function ledgerSum(array $filters, string $currency, array $types, array $statuses): string
    {
        return DecimalMath::amount(CommissionLedgerEntry::query()
            ->where('currency_code', $currency)
            ->whereIn('entry_type', $types)
            ->whereIn('status', $statuses)
            ->when($filters['business_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('business_id', $id))
            ->when($filters['referrer_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('referrer_id', $id))
            ->sum('amount'));
    }

    private function reviewLineSum(array $filters, string $currency, array $statuses): string
    {
        return DecimalMath::amount(CommissionReviewBatchLine::query()
            ->where('currency_code', $currency)
            ->whereIn('review_status', $statuses)
            ->when($filters['business_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('business_id', $id))
            ->when($filters['referrer_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('referrer_id', $id))
            ->sum('approved_amount'));
    }

    private function holdSum(array $filters, string $currency): string
    {
        return DecimalMath::amount(CommissionHold::query()
            ->where('currency_code', $currency)
            ->where('status', CommissionHoldStatus::Active)
            ->when($filters['business_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('business_id', $id))
            ->when($filters['referrer_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('referrer_id', $id))
            ->sum('amount'));
    }

    private function allocationSum(array $filters, string $currency, bool $lockedOnly): string
    {
        return DecimalMath::amount(CommissionSettlementAllocation::query()
            ->where('currency_code', $currency)
            ->when($lockedOnly, fn (Builder $query): Builder => $query->whereHas('settlementLine.settlementBatch', fn (Builder $batchQuery): Builder => $batchQuery->where('status', CommissionSettlementBatchStatus::Locked)))
            ->when($filters['business_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('business_id', $id))
            ->when($filters['referrer_id'] ?? null, fn (Builder $query, int $id): Builder => $query->whereHas('settlementLine', fn (Builder $lineQuery): Builder => $lineQuery->where('referrer_id', $id)))
            ->sum('allocated_amount'));
    }
}
