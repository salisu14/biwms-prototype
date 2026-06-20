<?php

declare(strict_types=1);

namespace App\Services\FixedAsset;

use App\Enums\FAPostingType;
use App\Enums\FAStatus;
use App\Enums\SourceType;
use App\Events\FixedAssetPosted;
use App\Models\FALedgerEntry;
use App\Models\FixedAsset;
use App\Models\GlEntry;
use App\Services\PostingDateValidator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class DisposalService
{
    public function __construct(
        private readonly PostingDateValidator $postingDateValidator
    ) {}

    public function dispose(
        FixedAsset $asset,
        float $proceeds,
        \DateTime $disposalDate,
        string $disposalType = 'sale' // sale, scrap, theft, donation
    ): array {
        Gate::authorize('dispose', $asset);
        $this->postingDateValidator->validate($disposalDate);

        if (! $asset->status->canDispose()) {
            throw new \RuntimeException("Fixed asset {$asset->fa_no} cannot be disposed from status {$asset->status->value}.");
        }

        if (FALedgerEntry::query()
            ->where('fixed_asset_id', $asset->id)
            ->where('depreciation_book_id', $asset->depreciation_book_id)
            ->where('fa_posting_type', FAPostingType::DISPOSAL)
            ->exists()) {
            throw new \RuntimeException("Fixed asset {$asset->fa_no} is already disposed.");
        }

        return DB::transaction(function () use ($asset, $proceeds, $disposalDate, $disposalType) {
            $asset->refresh();
            $bookValue = $asset->net_book_value;
            $accumulatedDepreciation = $asset->accumulated_depreciation;
            $gainLoss = $proceeds - $bookValue;

            $entry = $this->postDisposalEntry($asset, $disposalDate, $bookValue, $proceeds, $gainLoss, $disposalType);
            $this->postDisposalGlEntries($asset, $disposalDate, $proceeds, $gainLoss);

            $asset->update([
                'status' => FAStatus::DISPOSED,
                'disposal_date' => $disposalDate,
                'disposal_proceeds' => $proceeds,
                'disposal_cost' => $bookValue,
                'disposal_gain_loss' => $gainLoss,
                'book_value' => 0,
                'accumulated_depreciation' => 0,
            ]);

            FixedAssetPosted::dispatch($entry);

            return [
                'book_value' => $bookValue,
                'accumulated_depreciation' => $accumulatedDepreciation,
                'proceeds' => $proceeds,
                'gain_loss' => $gainLoss,
                'is_gain' => $gainLoss > 0,
            ];
        });
    }

    private function postDisposalEntry(
        FixedAsset $asset,
        \DateTime $date,
        float $bookValue,
        float $proceeds,
        float $gainLoss,
        string $disposalType
    ): FALedgerEntry {
        $entryNo = ((int) FALedgerEntry::query()
            ->where('fixed_asset_id', $asset->id)
            ->where('depreciation_book_id', $asset->depreciation_book_id)
            ->max('entry_no')) + 1;

        return FALedgerEntry::create([
            'fixed_asset_id' => $asset->id,
            'depreciation_book_id' => $asset->depreciation_book_id,
            'entry_no' => $entryNo,
            'fa_posting_type' => FAPostingType::DISPOSAL,
            'posting_date' => $date,
            'document_type' => 'FA DISPOSAL',
            'document_no' => $this->documentNo($asset, $date),
            'amount' => -$bookValue,
            'amount_lcy' => -$bookValue,
            'accumulated_depreciation' => -$asset->accumulated_depreciation,
            'proceeds_on_disposal' => $proceeds,
            'gain_loss_on_disposal' => $gainLoss,
            'book_value_after' => 0,
            'description' => "Disposal of {$asset->fa_no} ({$disposalType})",
            'created_by' => Auth::id() ?? $asset->created_by ?? 1,
            'entry_timestamp' => now(),
        ]);
    }

    private function postDisposalGlEntries(FixedAsset $asset, \DateTime $date, float $proceeds, float $gainLoss): void
    {
        $group = $asset->postingGroup;

        if (! $group?->acquisition_cost_account_id || ! $group?->accumulated_depreciation_account_id || ! $group?->disposal_proceeds_account_id) {
            throw new \RuntimeException("Disposal posting accounts are incomplete for fixed asset {$asset->fa_no}.");
        }

        $entryNumber = (int) (GlEntry::query()->max('entry_number') ?? 0) + 1;
        $transactionNumber = (int) (GlEntry::query()->max('transaction_number') ?? 0) + 1;
        $documentNo = $this->documentNo($asset, $date);

        if ((float) $asset->accumulated_depreciation > 0) {
            $this->createGlEntry($entryNumber++, $transactionNumber, $group->accumulated_depreciation_account_id, (float) $asset->accumulated_depreciation, $date, $documentNo, $asset->fa_no, "Clear accumulated depreciation {$asset->fa_no}");
        }

        if ($proceeds > 0) {
            $this->createGlEntry($entryNumber++, $transactionNumber, $group->disposal_proceeds_account_id, $proceeds, $date, $documentNo, $asset->fa_no, "Disposal proceeds {$asset->fa_no}");
        }

        if ($gainLoss < 0) {
            $lossAccount = $group->disposal_loss_account_id;

            if (! $lossAccount) {
                throw new \RuntimeException("Disposal loss account is missing for fixed asset {$asset->fa_no}.");
            }

            $this->createGlEntry($entryNumber++, $transactionNumber, $lossAccount, abs($gainLoss), $date, $documentNo, $asset->fa_no, "Loss on disposal {$asset->fa_no}");
        }

        $this->createGlEntry($entryNumber++, $transactionNumber, $group->acquisition_cost_account_id, -(float) $asset->book_value, $date, $documentNo, $asset->fa_no, "Clear asset cost {$asset->fa_no}");

        if ($gainLoss > 0) {
            $gainAccount = $group->disposal_gain_account_id;

            if (! $gainAccount) {
                throw new \RuntimeException("Disposal gain account is missing for fixed asset {$asset->fa_no}.");
            }

            $this->createGlEntry($entryNumber, $transactionNumber, $gainAccount, -$gainLoss, $date, $documentNo, $asset->fa_no, "Gain on disposal {$asset->fa_no}");
        }
    }

    private function createGlEntry(
        int $entryNumber,
        int $transactionNumber,
        int $accountId,
        float $amount,
        \DateTime $date,
        string $documentNo,
        string $sourceNumber,
        string $description
    ): void {
        GlEntry::query()->create([
            'entry_number' => $entryNumber,
            'transaction_number' => $transactionNumber,
            'chart_of_account_id' => $accountId,
            'posting_date' => $date,
            'document_date' => $date,
            'document_type' => 'FA DISPOSAL',
            'document_number' => $documentNo,
            'source_type' => SourceType::FIXED_ASSET,
            'source_number' => $sourceNumber,
            'description' => $description,
            'amount' => $amount,
            'amount_lcy' => $amount,
            'debit_amount' => $amount > 0 ? $amount : 0,
            'credit_amount' => $amount < 0 ? abs($amount) : 0,
            'user_id' => Auth::id() ?? 1,
        ]);
    }

    private function documentNo(FixedAsset $asset, \DateTime $date): string
    {
        return 'FAD-'.$asset->id.'-'.$date->format('Ymd');
    }
}
