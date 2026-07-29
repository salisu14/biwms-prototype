<?php

declare(strict_types=1);

namespace App\Services\Sales\ReferralCommissions;

use App\Enums\CommissionLedgerEntryStatus;
use App\Enums\CommissionLedgerEntryType;
use App\Models\CommissionLedgerEntry;
use App\Models\Referrer;
use App\Models\User;
use App\Support\DecimalMath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Carbon;

class CommissionAdjustmentService
{
    /**
     * @throws AuthorizationException
     */
    public function create(Referrer $referrer, Carbon|string $postingDate, string $currencyCode, string $amount, string $reasonCode, string $description, User $actor, ?string $idempotencyKey = null): CommissionLedgerEntry
    {
        if (! $actor->can('sales.commission_adjustment.create')) {
            throw new AuthorizationException('User is not authorized to create commission adjustments.');
        }

        if (blank($reasonCode)) {
            throw new \RuntimeException('Commission adjustment requires a reason code.');
        }

        $postingDate = $postingDate instanceof Carbon ? $postingDate : Carbon::parse($postingDate);
        $idempotencyKey ??= hash('sha256', implode('|', [
            'commission-adjustment',
            $referrer->id,
            $postingDate->toDateString(),
            $currencyCode,
            DecimalMath::amount($amount),
            $reasonCode,
        ]));

        return CommissionLedgerEntry::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'business_id' => $referrer->business_id,
                'entry_type' => CommissionLedgerEntryType::Adjustment,
                'referrer_id' => $referrer->id,
                'source_type' => Referrer::class,
                'source_id' => $referrer->id,
                'source_number' => $referrer->code,
                'posting_date' => $postingDate->toDateString(),
                'currency_code' => $currencyCode,
                'amount' => DecimalMath::amount($amount),
                'base_amount' => '0.0000',
                'status' => CommissionLedgerEntryStatus::Open,
                'reason_code' => $reasonCode,
                'description' => $description,
                'created_by' => $actor->id,
            ],
        );
    }
}
