<?php

declare(strict_types=1);

namespace App\Services\Sales\ReferralCommissions;

use App\Enums\CommissionLedgerEntryStatus;
use App\Enums\CommissionLedgerEntryType;
use App\Enums\CommissionReviewLineStatus;
use App\Models\CommissionLedgerEntry;
use App\Models\CommissionReviewBatchLine;
use App\Models\User;
use App\Services\AuditTrailService;
use App\Support\DecimalMath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CommissionForfeitureService
{
    public function __construct(
        private readonly CommissionReviewBatchService $batchService,
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function forfeit(CommissionReviewBatchLine $line, string $amount, string $reasonCode, string $description, User $actor, ?string $idempotencyKey = null): CommissionLedgerEntry
    {
        if (! $actor->can('sales.commission_forfeiture.create')) {
            throw new AuthorizationException('User is not authorized to forfeit commission.');
        }
        if (blank($reasonCode)) {
            throw new \RuntimeException('Commission forfeiture requires a reason code.');
        }

        return DB::transaction(function () use ($line, $amount, $reasonCode, $description, $actor, $idempotencyKey): CommissionLedgerEntry {
            $locked = CommissionReviewBatchLine::query()->lockForUpdate()->findOrFail($line->id);
            $amount = DecimalMath::amount($amount);
            if (DecimalMath::compare($amount, $locked->approved_amount) > 0) {
                throw new \RuntimeException('Commission forfeiture cannot exceed available approved amount.');
            }

            $entry = CommissionLedgerEntry::query()->firstOrCreate(
                ['idempotency_key' => $idempotencyKey ?? hash('sha256', 'commission-forfeiture|'.$locked->id.'|'.$amount.'|'.$reasonCode)],
                [
                    'business_id' => $locked->business_id,
                    'entry_type' => CommissionLedgerEntryType::Forfeiture,
                    'referrer_id' => $locked->referrer_id,
                    'customer_id' => $locked->ledgerEntry?->customer_id,
                    'customer_referral_id' => $locked->ledgerEntry?->customer_referral_id,
                    'commission_calculation_id' => $locked->commission_calculation_id,
                    'commission_calculation_line_id' => $locked->commission_calculation_line_id,
                    'source_type' => CommissionReviewBatchLine::class,
                    'source_id' => $locked->id,
                    'source_line_id' => $locked->commission_ledger_entry_id,
                    'source_number' => $locked->source_number,
                    'posting_date' => now()->toDateString(),
                    'currency_code' => $locked->currency_code,
                    'amount' => DecimalMath::mul($amount, -1, 4),
                    'base_amount' => $locked->eligible_amount,
                    'status' => CommissionLedgerEntryStatus::Forfeited,
                    'reverses_entry_id' => $locked->commission_ledger_entry_id,
                    'reason_code' => $reasonCode,
                    'description' => $description,
                    'created_by' => $actor->id,
                    'metadata' => ['review_batch_line_id' => $locked->id],
                ],
            );

            $locked->forceFill([
                'review_status' => DecimalMath::compare(DecimalMath::sub($locked->approved_amount, $amount, 4), 0) > 0
                    ? CommissionReviewLineStatus::Eligible
                    : CommissionReviewLineStatus::Forfeited,
                'forfeited_amount' => $amount,
                'approved_amount' => DecimalMath::sub($locked->approved_amount, $amount, 4),
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ])->save();
            $this->batchService->recalculate($locked->batch);
            $this->auditTrailService->recordGeneric('commission_review', 'forfeiture_created', $entry, userId: $actor->id, metadata: ['reason_code' => $reasonCode]);

            return $entry;
        });
    }
}
