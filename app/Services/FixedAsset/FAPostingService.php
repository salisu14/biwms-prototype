<?php

declare(strict_types=1);

namespace App\Services\FixedAsset;

use App\Enums\FAPostingType;
use App\Enums\SourceType;
use App\Events\FixedAssetPosted;
use App\Models\FALedgerEntry;
use App\Models\FixedAsset;
use App\Models\GlEntry;
use App\Services\PostingDateValidator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class FAPostingService
{
    public function __construct(
        private readonly PostingDateValidator $postingDateValidator
    ) {}

    public function postEntry(
        FixedAsset $asset,
        FAPostingType $postingType,
        float $amount,
        string $description,
        ?string $documentNo = null,
        ?\DateTime $postingDate = null,
        array $additionalData = []
    ): FALedgerEntry {
        $date = $postingDate ?? now();

        $this->authorize($asset, $postingType);
        $this->postingDateValidator->validate($date);
        $this->guardDuplicatePosting($asset, $postingType, $date, $documentNo);

        return DB::transaction(function () use ($asset, $postingType, $amount, $description, $documentNo, $date, $additionalData) {
            $asset->refresh();

            $lastEntryNo = (int) FALedgerEntry::where('fixed_asset_id', $asset->id)
                ->where('depreciation_book_id', $asset->depreciation_book_id)
                ->max('entry_no');
            $nextEntryNo = $lastEntryNo + 1;

            $currentAccum = (float) $asset->accumulated_depreciation;
            $currentAcq = (float) $asset->acquisition_cost;
            $deprAmount = $postingType === FAPostingType::DEPRECIATION ? $amount : 0.0;
            $newAccum = $currentAccum + $deprAmount;
            $bookValueAfter = match ($postingType) {
                FAPostingType::ACQUISITION => $currentAcq + $amount - $currentAccum,
                default => $currentAcq - $newAccum,
            };

            $debitGlEntry = $this->createGLEntry($asset, $postingType, $amount, $date, $description, $documentNo, $additionalData);

            $entry = FALedgerEntry::create([
                'fixed_asset_id' => $asset->id,
                'depreciation_book_id' => $asset->depreciation_book_id,
                'entry_no' => $nextEntryNo,
                'fa_posting_type' => $postingType,
                'posting_date' => $date,
                'document_no' => $documentNo,
                'gl_entry_id' => $debitGlEntry->id,
                'amount' => $amount,
                'amount_lcy' => $amount,
                'depreciation_amount' => $deprAmount,
                'accumulated_depreciation' => $newAccum,
                'book_value_after' => $bookValueAfter,
                'description' => $description,
                'created_by' => Auth::id() ?? $asset->created_by ?? 1,
                'entry_timestamp' => now(),
                ...$additionalData,
            ]);

            if ($postingType === FAPostingType::DEPRECIATION) {
                $asset->forceFill([
                    'accumulated_depreciation' => (float) $asset->accumulated_depreciation + $amount,
                ])->save();
            }

            if ($postingType === FAPostingType::ACQUISITION) {
                $asset->forceFill([
                    'acquisition_cost' => (float) $asset->acquisition_cost + $amount,
                    'book_value' => (float) $asset->book_value + $amount,
                ])->save();
            }

            FixedAssetPosted::dispatch($entry);

            return $entry;
        });
    }

    private function createGLEntry(
        FixedAsset $asset,
        FAPostingType $postingType,
        float $amount,
        \DateTime $date,
        string $description,
        ?string $documentNo,
        array $additionalData
    ): GlEntry {
        $accounts = $this->resolveAccounts($asset, $postingType, $additionalData);
        $nextEntryNumber = $this->nextGlEntryNumber();
        $transactionNumber = (int) (GlEntry::query()->max('transaction_number') ?? 0) + 1;
        $resolvedDocumentNo = $documentNo ?? 'FA-'.time();

        // Debit entry
        $debitEntry = GlEntry::create([
            'entry_number' => $nextEntryNumber++,
            'chart_of_account_id' => $accounts['debit'],
            'posting_date' => $date,
            'document_type' => 'FA '.$postingType->name,
            'document_number' => $resolvedDocumentNo,
            'document_date' => $date,
            'transaction_number' => $transactionNumber,
            'debit_amount' => $amount > 0 ? $amount : 0,
            'credit_amount' => $amount < 0 ? abs($amount) : 0,
            'amount' => $amount,
            'amount_lcy' => $amount,
            'source_type' => SourceType::FIXED_ASSET,
            'source_number' => $asset->fa_no,
            'description' => $description,
            'user_id' => Auth::id() ?? $asset->created_by ?? 1,
        ]);

        // Credit entry
        GlEntry::create([
            'entry_number' => $nextEntryNumber,
            'chart_of_account_id' => $accounts['credit'],
            'posting_date' => $date,
            'document_type' => 'FA '.$postingType->name,
            'document_number' => $resolvedDocumentNo,
            'document_date' => $date,
            'transaction_number' => $transactionNumber,
            'debit_amount' => $amount < 0 ? abs($amount) : 0,
            'credit_amount' => $amount > 0 ? $amount : 0,
            'amount' => $amount,
            'amount_lcy' => $amount,
            'source_type' => SourceType::FIXED_ASSET,
            'source_number' => $asset->fa_no,
            'description' => $description.' (Offset)',
            'user_id' => Auth::id() ?? $asset->created_by ?? 1,
        ]);

        return $debitEntry;
    }

    private function nextGlEntryNumber(): int
    {
        return (int) (GlEntry::query()->max('entry_number') ?? 0) + 1;
    }

    private function resolveAccounts(FixedAsset $asset, FAPostingType $postingType, array $additionalData = []): array
    {
        $group = $asset->postingGroup;

        if (! $group) {
            throw new \RuntimeException("Fixed asset {$asset->fa_no} is missing a posting group.");
        }

        $accounts = match ($postingType) {
            FAPostingType::ACQUISITION => [
                'debit' => $group->acquisition_cost_account_id,
                'credit' => $additionalData['offset_account_id']
                    ?? $group->capitalization_account_id
                    ?? $group->disposal_proceeds_account_id,
            ],
            FAPostingType::DEPRECIATION => [
                'debit' => $group->depreciation_expense_account_id,
                'credit' => $group->accumulated_depreciation_account_id,
            ],
            FAPostingType::APPRECIATION => [
                'debit' => $group->acquisition_cost_account_id,
                'credit' => $group->revaluation_account_id,
            ],
            FAPostingType::WRITE_DOWN => [
                'debit' => $group->reversal_of_revaluation_id ?? $group->depreciation_expense_account_id,
                'credit' => $group->acquisition_cost_account_id,
            ],
            FAPostingType::DISPOSAL => [
                'debit' => $group->accumulated_depreciation_account_id,
                'credit' => $group->acquisition_cost_account_id,
            ],
            default => throw new \InvalidArgumentException("Unknown posting type: {$postingType->value}"),
        };

        if (! $accounts['debit'] || ! $accounts['credit']) {
            throw new \RuntimeException("Posting accounts are incomplete for fixed asset {$asset->fa_no} and posting type {$postingType->value}.");
        }

        return $accounts;
    }

    private function authorize(FixedAsset $asset, FAPostingType $postingType): void
    {
        if (! Auth::check()) {
            throw new \RuntimeException('Authenticated user is required for fixed asset posting.');
        }

        match ($postingType) {
            FAPostingType::ACQUISITION => Gate::authorize('acquire', $asset),
            FAPostingType::DEPRECIATION => Gate::authorize('depreciate', $asset),
            FAPostingType::DISPOSAL, FAPostingType::DISPOSAL_GAIN, FAPostingType::DISPOSAL_LOSS => Gate::authorize('dispose', $asset),
            default => null,
        };
    }

    private function guardDuplicatePosting(FixedAsset $asset, FAPostingType $postingType, \DateTime $postingDate, ?string $documentNo): void
    {
        $query = FALedgerEntry::query()
            ->where('fixed_asset_id', $asset->id)
            ->where('depreciation_book_id', $asset->depreciation_book_id)
            ->where('fa_posting_type', $postingType);

        if ($postingType === FAPostingType::DEPRECIATION) {
            $query->whereYear('posting_date', $postingDate->format('Y'))
                ->whereMonth('posting_date', $postingDate->format('m'));

            if ($query->exists()) {
                throw new \RuntimeException("Depreciation for asset {$asset->fa_no} is already posted for {$postingDate->format('Y-m')}.");
            }

            return;
        }

        if ($postingType === FAPostingType::ACQUISITION && $query->exists()) {
            throw new \RuntimeException("Acquisition for asset {$asset->fa_no} is already posted.");
        }

        if ($documentNo && $query->where('document_no', $documentNo)->exists()) {
            throw new \RuntimeException("Fixed asset posting {$documentNo} is already posted.");
        }
    }
}
