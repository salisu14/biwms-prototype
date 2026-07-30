<?php

declare(strict_types=1);

namespace App\Services\Sales\ReferralCommissions;

use App\Enums\CommissionLedgerEntryStatus;
use App\Enums\CommissionLedgerEntryType;
use App\Enums\CommissionReviewBatchStatus;
use App\Enums\CommissionReviewLineStatus;
use App\Enums\CommissionReviewPeriodStatus;
use App\Models\CommissionLedgerEntry;
use App\Models\CommissionReviewBatch;
use App\Models\CommissionReviewBatchLine;
use App\Models\CommissionReviewPeriod;
use App\Models\User;
use App\Services\AuditTrailService;
use App\Support\DecimalMath;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CommissionReviewBatchService
{
    public function __construct(private readonly AuditTrailService $auditTrailService) {}

    public function generate(CommissionReviewPeriod $period, string $currencyCode, string $cutoffDate, User $actor, string $referrerScope = 'all', int $generationVersion = 1): CommissionReviewBatch
    {
        $this->authorize($actor, 'sales.commission_review_batch.generate');

        return DB::transaction(function () use ($period, $currencyCode, $cutoffDate, $actor, $referrerScope, $generationVersion): CommissionReviewBatch {
            $lockedPeriod = CommissionReviewPeriod::query()->lockForUpdate()->findOrFail($period->id);
            if (! in_array($lockedPeriod->status, [CommissionReviewPeriodStatus::Open, CommissionReviewPeriodStatus::UnderReview, CommissionReviewPeriodStatus::Approved], true)) {
                throw new \RuntimeException('Commission review batches can only be generated from open, under-review, or approved periods.');
            }
            if ($lockedPeriod->status === CommissionReviewPeriodStatus::Cancelled) {
                throw new \RuntimeException('Cancelled commission review periods cannot generate batches.');
            }

            $idempotencyKey = hash('sha256', implode('|', [
                'commission-review-batch',
                $lockedPeriod->business_id ?? 'global',
                $lockedPeriod->id,
                $currencyCode,
                $referrerScope,
                $cutoffDate,
                $generationVersion,
            ]));

            $batch = CommissionReviewBatch::query()
                ->where('idempotency_key', $idempotencyKey)
                ->lockForUpdate()
                ->first();

            if (! $batch) {
                $batch = CommissionReviewBatch::query()->create([
                    'business_id' => $lockedPeriod->business_id,
                    'commission_review_period_id' => $lockedPeriod->id,
                    'batch_number' => 'CRB-'.$lockedPeriod->code.'-'.$currencyCode.'-'.str_pad((string) $generationVersion, 3, '0', STR_PAD_LEFT),
                    'currency_code' => $currencyCode,
                    'status' => CommissionReviewBatchStatus::Generated,
                    'referrer_scope' => $referrerScope,
                    'calculation_date' => now()->toDateString(),
                    'cutoff_date' => $cutoffDate,
                    'generated_at' => now(),
                    'generated_by' => $actor->id,
                    'idempotency_key' => $idempotencyKey,
                    'metadata' => ['generation_version' => $generationVersion],
                ]);
            }

            if (in_array($batch->status, [CommissionReviewBatchStatus::Approved, CommissionReviewBatchStatus::Locked, CommissionReviewBatchStatus::Cancelled, CommissionReviewBatchStatus::Superseded], true)) {
                return $batch->fresh(['lines']);
            }

            foreach ($this->eligibleLedgerEntries($lockedPeriod, $currencyCode, $cutoffDate)->get() as $entry) {
                CommissionReviewBatchLine::query()->firstOrCreate(
                    ['commission_ledger_entry_id' => $entry->id],
                    $this->linePayload($batch, $entry),
                );
            }

            $this->recalculate($batch);
            $this->auditTrailService->recordGeneric('commission_review', 'batch_generated', $batch, userId: $actor->id);

            return $batch->fresh(['lines']);
        });
    }

    public function recalculate(CommissionReviewBatch $batch): CommissionReviewBatch
    {
        $locked = CommissionReviewBatch::query()->lockForUpdate()->findOrFail($batch->id);
        $lines = $locked->lines()->get();

        $locked->forceFill([
            'total_accrual_amount' => DecimalMath::amount($lines->where('entry_type', CommissionLedgerEntryType::Accrual->value)->sum('eligible_amount')),
            'total_adjustment_amount' => DecimalMath::amount($lines->where('entry_type', CommissionLedgerEntryType::Adjustment->value)->sum('eligible_amount')),
            'total_reversal_amount' => DecimalMath::amount($lines->where('entry_type', CommissionLedgerEntryType::Reversal->value)->sum('eligible_amount')),
            'total_hold_amount' => DecimalMath::amount($lines->sum('held_amount')),
            'total_forfeiture_amount' => DecimalMath::amount($lines->sum('forfeited_amount')),
            'total_eligible_amount' => DecimalMath::amount($lines->sum('approved_amount')),
            'line_count' => $lines->count(),
            'exception_count' => $lines->filter(fn (CommissionReviewBatchLine $line): bool => filled($line->exception_code) || in_array($line->review_status, [CommissionReviewLineStatus::Held, CommissionReviewLineStatus::Disputed, CommissionReviewLineStatus::Rejected], true))->count(),
        ])->save();

        return $locked->fresh();
    }

    private function eligibleLedgerEntries(CommissionReviewPeriod $period, string $currencyCode, string $cutoffDate): Builder
    {
        return CommissionLedgerEntry::query()
            ->where('status', CommissionLedgerEntryStatus::Open)
            ->where('currency_code', $currencyCode)
            ->whereDate('posting_date', '>=', $period->period_start)
            ->whereDate('posting_date', '<=', $period->period_end)
            ->whereDate('posting_date', '<=', $cutoffDate)
            ->whereDoesntHave('reviewLines')
            ->whereDoesntHave('settlementAllocations')
            ->where(function (Builder $query) use ($period): void {
                $query->where('business_id', $period->business_id)
                    ->orWhereNull('business_id');
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function linePayload(CommissionReviewBatch $batch, CommissionLedgerEntry $entry): array
    {
        $eligibleAmount = DecimalMath::amount($entry->amount);

        return [
            'business_id' => $entry->business_id,
            'commission_review_batch_id' => $batch->id,
            'referrer_id' => $entry->referrer_id,
            'currency_code' => $entry->currency_code,
            'commission_ledger_entry_id' => $entry->id,
            'commission_calculation_id' => $entry->commission_calculation_id,
            'commission_calculation_line_id' => $entry->commission_calculation_line_id,
            'source_type' => $entry->source_type,
            'source_id' => $entry->source_id,
            'source_number' => $entry->source_number,
            'source_posting_date' => $entry->posting_date,
            'entry_type' => $entry->entry_type->value,
            'original_amount' => $entry->amount,
            'eligible_amount' => $eligibleAmount,
            'approved_amount' => $eligibleAmount,
            'review_status' => CommissionReviewLineStatus::Eligible,
            'snapshot' => [
                'ledger_entry_id' => $entry->id,
                'entry_number' => $entry->entry_number,
                'entry_type' => $entry->entry_type->value,
                'amount' => (string) $entry->amount,
                'currency_code' => $entry->currency_code,
                'source_number' => $entry->source_number,
                'calculation_id' => $entry->commission_calculation_id,
                'calculation_line_id' => $entry->commission_calculation_line_id,
            ],
            'idempotency_key' => hash('sha256', 'commission-review-line|'.$batch->id.'|'.$entry->id),
        ];
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $actor->can($permission)) {
            throw new AuthorizationException("User is not authorized for {$permission}.");
        }
    }
}
