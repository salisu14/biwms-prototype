<?php

declare(strict_types=1);

namespace App\Services\Sales\ReferralCommissions;

use App\Enums\CommissionLedgerEntryStatus;
use App\Models\CommissionLedgerEntry;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use Illuminate\Database\Eloquent\Builder;

class ReferrerCommissionBalanceService
{
    /**
     * @param  array{business_id?: int, referrer_id?: int, currency_code?: string, date_from?: string, date_to?: string, commission_plan_id?: int, customer_id?: int, source_number?: string}  $filters
     * @return array<string, array{accrued_open: string, approved_for_future_payment: string, reversed: string, forfeited: string, net_outstanding: string}>
     */
    public function balances(array $filters = []): array
    {
        $rows = $this->query($filters)
            ->selectRaw('currency_code, status, SUM(amount) as total')
            ->groupBy('currency_code', 'status')
            ->get();

        $balances = [];
        foreach ($rows as $row) {
            $currency = (string) $row->currency_code;
            $balances[$currency] ??= $this->emptyBalance();
            $status = $row->status instanceof CommissionLedgerEntryStatus ? $row->status->value : (string) $row->status;
            $amount = DecimalMath::amount($row->total);

            match ($status) {
                CommissionLedgerEntryStatus::Open->value => $balances[$currency]['accrued_open'] = DecimalMath::add($balances[$currency]['accrued_open'], $amount, DecimalPrecision::AMOUNT_SCALE),
                CommissionLedgerEntryStatus::ApprovedForFuturePayment->value => $balances[$currency]['approved_for_future_payment'] = DecimalMath::add($balances[$currency]['approved_for_future_payment'], $amount, DecimalPrecision::AMOUNT_SCALE),
                CommissionLedgerEntryStatus::Reversed->value => $balances[$currency]['reversed'] = DecimalMath::add($balances[$currency]['reversed'], $amount, DecimalPrecision::AMOUNT_SCALE),
                CommissionLedgerEntryStatus::Forfeited->value => $balances[$currency]['forfeited'] = DecimalMath::add($balances[$currency]['forfeited'], $amount, DecimalPrecision::AMOUNT_SCALE),
                default => null,
            };
        }

        foreach ($balances as &$balance) {
            $balance['net_outstanding'] = DecimalMath::add(
                DecimalMath::add($balance['accrued_open'], $balance['approved_for_future_payment'], DecimalPrecision::AMOUNT_SCALE),
                DecimalMath::add($balance['reversed'], $balance['forfeited'], DecimalPrecision::AMOUNT_SCALE),
                DecimalPrecision::AMOUNT_SCALE,
            );
        }

        return $balances;
    }

    private function query(array $filters): Builder
    {
        return CommissionLedgerEntry::query()
            ->when($filters['business_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('business_id', $id))
            ->when($filters['referrer_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('referrer_id', $id))
            ->when($filters['currency_code'] ?? null, fn (Builder $query, string $currency): Builder => $query->where('currency_code', $currency))
            ->when($filters['date_from'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('posting_date', '>=', $date))
            ->when($filters['date_to'] ?? null, fn (Builder $query, string $date): Builder => $query->whereDate('posting_date', '<=', $date))
            ->when($filters['customer_id'] ?? null, fn (Builder $query, int $id): Builder => $query->where('customer_id', $id))
            ->when($filters['source_number'] ?? null, fn (Builder $query, string $source): Builder => $query->where('source_number', $source))
            ->when($filters['commission_plan_id'] ?? null, fn (Builder $query, int $id): Builder => $query->whereHas('calculation', fn (Builder $calculationQuery): Builder => $calculationQuery->where('commission_plan_id', $id)));
    }

    /**
     * @return array{accrued_open: string, approved_for_future_payment: string, reversed: string, forfeited: string, net_outstanding: string}
     */
    private function emptyBalance(): array
    {
        return [
            'accrued_open' => '0.0000',
            'approved_for_future_payment' => '0.0000',
            'reversed' => '0.0000',
            'forfeited' => '0.0000',
            'net_outstanding' => '0.0000',
        ];
    }
}
