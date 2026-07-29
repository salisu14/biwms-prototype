<?php

declare(strict_types=1);

namespace App\Services\Sales\ReferralCommissions;

use App\Enums\CommissionLedgerEntryStatus;
use App\Enums\CommissionLedgerEntryType;
use App\Models\CommissionLedgerEntry;
use App\Models\PostedSalesCreditMemo;
use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use Illuminate\Support\Facades\DB;

class CommissionReversalService
{
    public function reverseForPostedSalesCreditMemo(PostedSalesCreditMemo $creditMemo, ?int $userId = null): int
    {
        return DB::transaction(function () use ($creditMemo, $userId): int {
            $lockedMemo = PostedSalesCreditMemo::query()
                ->with('lines.correctedInvoiceLine')
                ->lockForUpdate()
                ->findOrFail($creditMemo->id);

            if (! $lockedMemo->corrected_invoice_id) {
                return 0;
            }

            $created = 0;
            foreach ($lockedMemo->lines as $line) {
                if (! $line->corrected_invoice_line_id) {
                    continue;
                }

                $originalAccruals = CommissionLedgerEntry::query()
                    ->where('entry_type', CommissionLedgerEntryType::Accrual)
                    ->where('source_line_id', $line->corrected_invoice_line_id)
                    ->get();

                foreach ($originalAccruals as $accrual) {
                    $reversalAmount = $this->reversalAmount($accrual, $line);
                    if (DecimalMath::isZero($reversalAmount)) {
                        continue;
                    }

                    $entry = CommissionLedgerEntry::query()->firstOrCreate(
                        ['idempotency_key' => $this->reversalIdempotencyKey($lockedMemo, (int) $line->id, (int) $accrual->id)],
                        [
                            'business_id' => $accrual->business_id,
                            'entry_type' => CommissionLedgerEntryType::Reversal,
                            'referrer_id' => $accrual->referrer_id,
                            'customer_id' => $accrual->customer_id,
                            'customer_referral_id' => $accrual->customer_referral_id,
                            'commission_calculation_id' => $accrual->commission_calculation_id,
                            'commission_calculation_line_id' => $accrual->commission_calculation_line_id,
                            'source_type' => PostedSalesCreditMemo::class,
                            'source_id' => $lockedMemo->id,
                            'source_line_id' => $line->id,
                            'source_number' => $lockedMemo->document_number,
                            'posting_date' => $lockedMemo->posting_date,
                            'currency_code' => $accrual->currency_code,
                            'amount' => DecimalMath::mul($reversalAmount, '-1', DecimalPrecision::AMOUNT_SCALE),
                            'base_amount' => DecimalMath::amount(abs((float) $line->line_amount)),
                            'status' => CommissionLedgerEntryStatus::Open,
                            'reverses_entry_id' => $accrual->id,
                            'reason_code' => 'SALES_CREDIT_MEMO',
                            'description' => 'Referral commission reversal for posted sales credit memo',
                            'metadata' => [
                                'original_source_number' => $accrual->source_number,
                                'credit_memo_number' => $lockedMemo->document_number,
                                'uses_original_snapshot' => true,
                            ],
                            'created_by' => $userId,
                        ],
                    );

                    if ($entry->wasRecentlyCreated) {
                        $created++;
                    }
                }
            }

            return $created;
        });
    }

    private function reversalAmount(CommissionLedgerEntry $accrual, mixed $creditMemoLine): string
    {
        $originalBase = DecimalMath::abs($accrual->base_amount, DecimalPrecision::AMOUNT_SCALE);
        if (DecimalMath::isZero($originalBase)) {
            return DecimalMath::amount($accrual->amount);
        }

        $creditBase = DecimalMath::amount(abs((float) $creditMemoLine->line_amount));
        $ratio = DecimalMath::div($creditBase, $originalBase, 8);
        $candidate = DecimalMath::mul(DecimalMath::abs($accrual->amount, DecimalPrecision::AMOUNT_SCALE), $ratio, DecimalPrecision::AMOUNT_SCALE);
        $remaining = DecimalMath::sub(
            DecimalMath::abs($accrual->amount, DecimalPrecision::AMOUNT_SCALE),
            DecimalMath::abs($this->existingReversalTotal($accrual), DecimalPrecision::AMOUNT_SCALE),
            DecimalPrecision::AMOUNT_SCALE,
        );

        if (DecimalMath::compare($candidate, $remaining) > 0) {
            return $remaining;
        }

        return $candidate;
    }

    private function existingReversalTotal(CommissionLedgerEntry $accrual): string
    {
        return DecimalMath::amount(CommissionLedgerEntry::query()
            ->where('reverses_entry_id', $accrual->id)
            ->sum('amount'));
    }

    private function reversalIdempotencyKey(PostedSalesCreditMemo $creditMemo, int $lineId, int $accrualId): string
    {
        return hash('sha256', implode('|', [
            'commission-ledger-reversal',
            PostedSalesCreditMemo::class,
            $creditMemo->id,
            $lineId,
            $accrualId,
        ]));
    }
}
