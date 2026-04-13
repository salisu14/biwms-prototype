<?php

declare(strict_types=1);

namespace App\Services\FixedAsset;

use App\Enums\FAPostingType;
use App\Models\FixedAsset;
use App\Models\FALedgerEntry;
use App\Models\GlEntry;
use Illuminate\Support\Facades\Auth;

class FAPostingService
{
    public function postEntry(
        FixedAsset $asset,
        FAPostingType $postingType,
        float $amount,
        string $description,
        ?string $documentNo = null,
        ?\DateTime $postingDate = null,
        ?array $additionalData = []
    ): FALedgerEntry {
        $date = $postingDate ?? now();

        $entry = FALedgerEntry::create([
            'fixed_asset_id' => $asset->id,
            'depreciation_book_id' => $asset->depreciation_book_id,
            'fa_posting_type' => $postingType,
            'posting_date' => $date,
            'document_no' => $documentNo,
            'amount' => $amount,
            'amount_lcy' => $amount,
            'description' => $description,
            'created_by' => Auth::id(),
            'entry_timestamp' => now(),
            ...$additionalData,
        ]);

        // Create corresponding GL entry
        $this->createGLEntry($asset, $postingType, $amount, $date, $description, $documentNo);

        return $entry;
    }

    private function createGLEntry(
        FixedAsset $asset,
        FAPostingType $postingType,
        float $amount,
        \DateTime $date,
        string $description,
        ?string $documentNo
    ): void {
        $accounts = $this->resolveAccounts($asset, $postingType);

        // Debit entry
        GlEntry::create([
            'entry_number' => $accounts['debit'],
            'posting_date' => $date,
            'document_type' => 'FA ' . $postingType->name,
            'transaction_number' => $documentNo ?? 'FA-' . time(),
            'debit_amount' => $amount > 0 ? $amount : 0,
            'credit_amount' => $amount < 0 ? abs($amount) : 0,
            'description' => $description,
            'user_id' => Auth::id(),
        ]);

        // Credit entry
        GlEntry::create([
            'entry_number' => $accounts['credit'],
            'posting_date' => $date,
            'document_type' => 'FA ' . $postingType->name,
            'transaction_number' => $documentNo ?? 'FA-' . time(),
            'debit_amount' => $amount < 0 ? abs($amount) : 0,
            'credit_amount' => $amount > 0 ? $amount : 0,
            'description' => $description . ' (Offset)',
            'user_id' => Auth::id(),
        ]);
    }

    private function resolveAccounts(FixedAsset $asset, FAPostingType $postingType): array
    {
        $group = $asset->postingGroup;

        return match($postingType) {
            FAPostingType::ACQUISITION => [
                'debit' => $group->acquisition_cost_account_id,
                'credit' => $group->payable_account_id ?? 1, // AP or Cash
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
    }
}
