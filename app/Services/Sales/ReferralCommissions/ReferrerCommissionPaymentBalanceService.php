<?php

declare(strict_types=1);

namespace App\Services\Sales\ReferralCommissions;

use App\Enums\CommissionPaymentApplicationStatus;
use App\Enums\CommissionPaymentApplicationType;
use App\Models\CommissionPaymentApplication;
use App\Models\CommissionSettlementLine;
use App\Models\Referrer;

class ReferrerCommissionPaymentBalanceService
{
    /**
     * @param  array<string, mixed>  $filters
     * @return array<int, array<string, mixed>>
     */
    public function balances(array $filters = []): array
    {
        return Referrer::query()
            ->when($filters['referrer_id'] ?? null, fn ($query, int $referrerId) => $query->whereKey($referrerId))
            ->orderBy('name')
            ->get()
            ->map(function (Referrer $referrer) use ($filters): array {
                $settled = (float) CommissionSettlementLine::query()
                    ->where('referrer_id', $referrer->id)
                    ->when($filters['business_id'] ?? null, fn ($query, int $businessId) => $query->where('business_id', $businessId))
                    ->sum('net_settlement_amount');

                $paid = $this->applicationSum($referrer->id, CommissionPaymentApplicationType::Payment, $filters);
                $reversed = $this->applicationSum($referrer->id, CommissionPaymentApplicationType::Reversal, $filters);
                $netPaid = $paid - $reversed;

                return [
                    'referrer_id' => $referrer->id,
                    'referrer_code' => $referrer->code,
                    'referrer_name' => $referrer->name,
                    'settled_amount' => round($settled, 4),
                    'paid_amount' => round($netPaid, 4),
                    'outstanding_amount' => round($settled - $netPaid, 4),
                    'payment_reversed_amount' => round($reversed, 4),
                    'recovery_required_amount' => max(round($netPaid - $settled, 4), 0),
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function applicationSum(int $referrerId, CommissionPaymentApplicationType $type, array $filters): float
    {
        return (float) CommissionPaymentApplication::query()
            ->where('referrer_id', $referrerId)
            ->where('application_type', $type)
            ->where('status', CommissionPaymentApplicationStatus::Applied)
            ->when($filters['business_id'] ?? null, fn ($query, int $businessId) => $query->where('business_id', $businessId))
            ->sum('applied_amount');
    }
}
