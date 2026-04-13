<?php

declare(strict_types=1);

namespace App\Services\FixedAsset;

use App\Enums\FAStatus;
use App\Enums\FAPostingType;
use App\Models\FixedAsset;
use App\Models\FALedgerEntry;
use Illuminate\Support\Facades\DB;

class DisposalService
{
    public function dispose(
        FixedAsset $asset,
        float $proceeds,
        \DateTime $disposalDate,
        string $disposalType = 'sale' // sale, scrap, theft, donation
    ): array {
        return DB::transaction(function () use ($asset, $proceeds, $disposalDate, $disposalType) {
            $bookValue = $asset->net_book_value;
            $accumulatedDepreciation = $asset->accumulated_depreciation;
            $gainLoss = $proceeds - $bookValue;

            // 1. Post disposal entry (remove asset from books)
            $this->postDisposalEntry($asset, $disposalDate, $bookValue);

            // 2. Post proceeds
            $this->postProceeds($asset, $proceeds, $disposalDate);

            // 3. Post gain or loss
            if ($gainLoss > 0) {
                $this->postGain($asset, $gainLoss, $disposalDate);
            } elseif ($gainLoss < 0) {
                $this->postLoss($asset, abs($gainLoss), $disposalDate);
            }

            // 4. Update asset
            $asset->update([
                'status' => FAStatus::DISPOSED,
                'disposal_date' => $disposalDate,
                'disposal_proceeds' => $proceeds,
                'disposal_cost' => $bookValue,
                'disposal_gain_loss' => $gainLoss,
                'book_value' => 0,
                'accumulated_depreciation' => 0,
            ]);

            return [
                'book_value' => $bookValue,
                'accumulated_depreciation' => $accumulatedDepreciation,
                'proceeds' => $proceeds,
                'gain_loss' => $gainLoss,
                'is_gain' => $gainLoss > 0,
            ];
        });
    }

    private function postDisposalEntry(FixedAsset $asset, \DateTime $date, float $bookValue): void
    {
        // Remove asset cost and accumulated depreciation
        FALedgerEntry::create([
            'fixed_asset_id' => $asset->id,
            'depreciation_book_id' => $asset->depreciation_book_id,
            'fa_posting_type' => FAPostingType::DISPOSAL,
            'posting_date' => $date,
            'amount' => -$asset->book_value, // Negative = removal
            'accumulated_depreciation' => -$asset->accumulated_depreciation,
            'book_value_after' => 0,
            'description' => "Disposal of {$asset->fa_no}",
            'created_by' => auth()->id(),
            'entry_timestamp' => now(),
        ]);

        // GL: Dr Accumulated Depreciation, Cr Asset Cost
        // (Net effect is removal of NBV)
    }

    private function postProceeds(FixedAsset $asset, float $proceeds, \DateTime $date): void
    {
        FALedgerEntry::create([
            'fixed_asset_id' => $asset->id,
            'depreciation_book_id' => $asset->depreciation_book_id,
            'fa_posting_type' => FAPostingType::DISPOSAL,
            'posting_date' => $date,
            'proceeds_on_disposal' => $proceeds,
            'amount' => $proceeds,
            'description' => "Disposal proceeds: {$asset->fa_no}",
            'created_by' => auth()->id(),
            'entry_timestamp' => now(),
        ]);
    }

    private function postGain(FixedAsset $asset, float $amount, \DateTime $date): void
    {
        FALedgerEntry::create([
            'fixed_asset_id' => $asset->id,
            'depreciation_book_id' => $asset->depreciation_book_id,
            'fa_posting_type' => FAPostingType::DISPOSAL_GAIN,
            'posting_date' => $date,
            'gain_loss_on_disposal' => $amount,
            'amount' => $amount,
            'description' => "Gain on disposal: {$asset->fa_no}",
            'created_by' => auth()->id(),
            'entry_timestamp' => now(),
        ]);
    }

    private function postLoss(FixedAsset $asset, float $amount, \DateTime $date): void
    {
        FALedgerEntry::create([
            'fixed_asset_id' => $asset->id,
            'depreciation_book_id' => $asset->depreciation_book_id,
            'fa_posting_type' => FAPostingType::DISPOSAL_LOSS,
            'posting_date' => $date,
            'gain_loss_on_disposal' => -$amount,
            'amount' => -$amount,
            'description' => "Loss on disposal: {$asset->fa_no}",
            'created_by' => auth()->id(),
            'entry_timestamp' => now(),
        ]);
    }
}
