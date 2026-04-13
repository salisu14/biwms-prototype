<?php

declare(strict_types=1);

namespace App\Services\FixedAsset;

use App\Enums\FAPostingType;
use App\Models\FixedAsset;
use App\Models\FALedgerEntry;
use Illuminate\Support\Facades\DB;

class RevaluationService
{
    public function revalue(
        FixedAsset $asset,
        float $newAmount,
        \DateTime $date,
        string $reason,
        ?float $indexFactor = null
    ): void {
        DB::transaction(function () use ($asset, $newAmount, $date, $reason, $indexFactor) {
            $oldBookValue = $asset->net_book_value;
            $revaluationAmount = $newAmount - $oldBookValue;

            if ($revaluationAmount == 0) {
                return;
            }

            $isAppreciation = $revaluationAmount > 0;

            // Create revaluation entry
            FALedgerEntry::create([
                'fixed_asset_id' => $asset->id,
                'depreciation_book_id' => $asset->depreciation_book_id,
                'fa_posting_type' => $isAppreciation ? FAPostingType::APPRECIATION : FAPostingType::WRITE_DOWN,
                'posting_date' => $date,
                'amount' => $revaluationAmount,
                'revaluation_amount' => $revaluationAmount,
                'index_factor' => $indexFactor,
                'book_value_after' => $newAmount,
                'description' => "Revaluation: {$reason}",
                'created_by' => auth()->id(),
                'entry_timestamp' => now(),
            ]);

            // Update asset
            $asset->update([
                'book_value' => $newAmount + $asset->accumulated_depreciation, // Gross book value
                'last_revaluation_amount' => $revaluationAmount,
                'last_revaluation_date' => $date,
                'revaluation_reserve' => $isAppreciation
                    ? $asset->revaluation_reserve + $revaluationAmount
                    : max(0, $asset->revaluation_reserve + $revaluationAmount), // Reduce reserve first
            ]);

            // Post GL entries
            $this->postGLRevaluation($asset, $revaluationAmount, $date, $isAppreciation);
        });
    }

    public function depreciateRevaluationReserve(FixedAsset $asset, float $amount): void
    {
        // Transfer from revaluation reserve to retained earnings as asset is used
        // IAS 16 requirement
    }

    private function postGLRevaluation(
        FixedAsset $asset,
        float $amount,
        \DateTime $date,
        bool $isAppreciation
    ): void {
        if ($isAppreciation) {
            // Dr Asset Cost, Cr Revaluation Reserve (Equity)
        } else {
            // Dr Revaluation Reserve (up to amount in reserve), then Dr Impairment Loss
            // Cr Asset Cost
        }
    }
}
