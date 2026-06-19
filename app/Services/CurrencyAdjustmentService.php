<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CurrencyAdjustmentType;
use App\Models\BankAccountLedgerEntry;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\CurrencyAdjustmentLedger;
use App\Models\CurrencyBuffer;
use App\Models\CustomerLedgerEntry;
use App\Models\VendorLedgerEntry;
use App\Services\Finance\GeneralLedgerService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class CurrencyAdjustmentService
{
    public function __construct(
        private readonly CurrencyService $currencyService,
        private readonly NumberSeriesService $numberSeriesService,
        private readonly GeneralLedgerService $glService
    ) {}

    /**
     * Revalue foreign currency balances (BC: Adjust Exchange Rates)
     */
    public function revalueCurrency(
        Currency $currency,
        \DateTime $revaluationDate,
        ?string $documentNo = null
    ): array {
        if ($currency->is_lcy) {
            throw new \InvalidArgumentException('Cannot revalue LCY');
        }

        $documentNo = $documentNo ?? $this->numberSeriesService->getNextNo('CUR-ADJ');
        $newRate = $currency->getExchangeRate($revaluationDate);
        $adjustments = [];

        DB::transaction(function () use ($currency, $revaluationDate, $newRate, $documentNo, &$adjustments) {
            // 1. Revalue Vendor Ledger (A/P)
            $vendorAdjustments = $this->revalueVendorLedger($currency, $newRate, $revaluationDate, $documentNo);

            // 2. Revalue Customer Ledger (A/R)
            $customerAdjustments = $this->revalueCustomerLedger($currency, $newRate, $revaluationDate, $documentNo);

            // 3. Revalue Bank Accounts
            $bankAdjustments = $this->revalueBankAccounts($currency, $newRate, $revaluationDate, $documentNo);

            $adjustments = array_merge($vendorAdjustments, $customerAdjustments, $bankAdjustments);

            // Post summary to G/L
            $this->postRevaluationSummary($adjustments, $currency, $documentNo, $revaluationDate);
        });

        return [
            'document_no' => $documentNo,
            'adjustments' => $adjustments,
            'total_gain' => collect($adjustments)->where('type', 'gain')->sum('amount'),
            'total_loss' => collect($adjustments)->where('type', 'loss')->sum('amount'),
        ];
    }

    /**
     * Post realized gain/loss on payment (BC: Post Realized Gain/Loss)
     */
    public function postRealizedGainLoss(
        VendorLedgerEntry $entry,
        float $paymentAmountFCY,
        float $paymentExchangeRate,
        string $documentNo
    ): ?CurrencyAdjustmentLedger {
        $originalRate = $entry->original_exch_rate ?? $entry->currency_factor;
        $currency = $this->currencyService->getByCode($entry->currency_code);

        if (! $currency || $currency->is_lcy) {
            return null;
        }

        // Calculate gain/loss
        $originalLCY = $paymentAmountFCY * $originalRate;
        $paymentLCY = $paymentAmountFCY * $paymentExchangeRate;
        $gainLoss = $paymentLCY - $originalLCY;

        if (abs($gainLoss) < 0.01) {
            return null; // Negligible
        }

        $isGain = $gainLoss > 0;
        $adjustmentType = $isGain
            ? CurrencyAdjustmentType::REALIZED_GAIN
            : CurrencyAdjustmentType::REALIZED_LOSS;

        return DB::transaction(function () use (
            $entry, $currency, $gainLoss, $originalRate, $paymentExchangeRate,
            $adjustmentType, $isGain, $documentNo, $originalLCY, $paymentLCY
        ) {
            // Create adjustment ledger entry
            $adjustment = CurrencyAdjustmentLedger::create([
                'currency_id' => $currency->id,
                'adjustment_account_id' => $isGain
                    ? $currency->realized_gains_account_id
                    : $currency->realized_losses_account_id,
                'document_type' => 'payment',
                'document_no' => $documentNo,
                'posting_date' => now(),
                'adjustment_type' => $adjustmentType,
                'original_amount' => $originalLCY,
                'adjusted_amount' => $paymentLCY,
                'adjustment_amount' => abs($gainLoss),
                'original_exch_rate' => $originalRate,
                'new_exch_rate' => $paymentExchangeRate,
                'vendor_ledger_entry_id' => $entry->id,
                'created_by' => Auth::id(),
                'description' => "Realized {$adjustmentType->label()} on payment",
            ]);

            // Post to G/L
            $this->postAdjustmentToGL($adjustment);

            return $adjustment;
        });
    }

    /**
     * Prepare currency revaluation buffer (BC: Fill Currency Buffer)
     */
    public function prepareRevaluationBuffer(Currency $currency, string $bufferType): Collection
    {
        $openEntries = match ($bufferType) {
            'payable' => VendorLedgerEntry::where('currency_code', $currency->code)
                ->where('open', true)
                ->get(),
            'receivable' => CustomerLedgerEntry::where('currency_code', $currency->code)
                ->where('open', true)
                ->get(),
            'bank' => BankAccountLedgerEntry::where('currency_code', $currency->code)
                ->where('open', true)
                ->get(),
            default => collect(),
        };

        $buffer = $openEntries->map(function ($entry) use ($currency) {
            $currentRate = $currency->getExchangeRate();
            $unrealizedGL = $this->calculateUnrealizedGainLoss($entry, $currentRate);

            return CurrencyBuffer::create([
                'currency_id' => $currency->id,
                'buffer_type' => $bufferType,
                'entity_id' => $entry->vendor_id ?? $entry->customer_id ?? $entry->bank_account_id,
                'amount_lcy' => $entry->amount_lcy,
                'amount_fcy' => $entry->amount,
                'remaining_amount_lcy' => $entry->remaining_amount_lcy ?? $entry->amount_lcy,
                'remaining_amount_fcy' => $entry->remaining_amount ?? $entry->amount,
                'original_exch_rate' => $entry->currency_factor,
                'current_exch_rate' => $currentRate,
                'unrealized_gain_loss' => $unrealizedGL,
                'posting_date' => $entry->posting_date,
                'due_date' => $entry->due_date,
            ]);
        });

        return $buffer;
    }

    // Private methods
    private function revalueVendorLedger(
        Currency $currency,
        float $newRate,
        \DateTime $date,
        string $documentNo
    ): array {
        $adjustments = [];

        $openEntries = VendorLedgerEntry::where('currency_code', $currency->code)
            ->where('open', true)
            ->get();

        foreach ($openEntries as $entry) {
            $oldRate = $entry->currency_factor;
            $remainingFCY = $entry->remaining_amount;

            $oldLCY = $remainingFCY * $oldRate;
            $newLCY = $remainingFCY * $newRate;
            $adjustment = $newLCY - $oldLCY;

            if (abs($adjustment) < 0.01) {
                continue;
            }

            $isGain = $adjustment > 0;
            $adjustmentType = $isGain
                ? CurrencyAdjustmentType::UNREALIZED_GAIN
                : CurrencyAdjustmentType::UNREALIZED_LOSS;

            $ledgerEntry = CurrencyAdjustmentLedger::create([
                'currency_id' => $currency->id,
                'adjustment_account_id' => $isGain
                    ? $currency->unrealized_gains_account_id
                    : $currency->unrealized_losses_account_id,
                'document_type' => 'revaluation',
                'document_no' => $documentNo,
                'posting_date' => $date,
                'adjustment_type' => $adjustmentType,
                'original_amount' => $oldLCY,
                'adjusted_amount' => $newLCY,
                'adjustment_amount' => abs($adjustment),
                'original_exch_rate' => $oldRate,
                'new_exch_rate' => $newRate,
                'vendor_ledger_entry_id' => $entry->id,
                'created_by' => Auth::id(),
            ]);

            // Update entry with new rate
            $entry->update(['currency_factor' => $newRate]);

            $adjustments[] = [
                'type' => $isGain ? 'gain' : 'loss',
                'amount' => abs($adjustment),
                'entry_id' => $entry->id,
                'ledger_id' => $ledgerEntry->id,
            ];
        }

        return $adjustments;
    }

    private function revalueCustomerLedger(
        Currency $currency,
        float $newRate,
        \DateTime $date,
        string $documentNo
    ): array {
        // Similar to vendor ledger...
        return [];
    }

    private function revalueBankAccounts(
        Currency $currency,
        float $newRate,
        \DateTime $date,
        string $documentNo
    ): array {
        // Implementation for bank accounts...
        return [];
    }

    private function calculateUnrealizedGainLoss($entry, float $currentRate): float
    {
        $originalRate = $entry->currency_factor;
        $remainingFCY = $entry->remaining_amount ?? $entry->amount;

        $originalLCY = $remainingFCY * $originalRate;
        $currentLCY = $remainingFCY * $currentRate;

        return $currentLCY - $originalLCY;
    }

    private function postAdjustmentToGL(CurrencyAdjustmentLedger $adjustment): void
    {
        $account = $adjustment->adjustmentAccount;
        $offsetAccount = $adjustment->isGain()
            ? ChartOfAccount::where('account_type', 'income')->first() // Unrealized gain offset
            : ChartOfAccount::where('account_type', 'expense')->first(); // Unrealized loss offset

        if (! $account || ! $offsetAccount) {
            throw new \RuntimeException('Missing currency adjustment G/L account setup.');
        }

        $amount = $adjustment->adjustment_amount;

        $this->glService->post([
            [
                'account_id' => $account->id,
                'debit' => $adjustment->isGain() ? $amount : 0,
                'credit' => $adjustment->isGain() ? 0 : $amount,
                'description' => $adjustment->description,
            ],
            [
                'account_id' => $offsetAccount->id,
                'debit' => $adjustment->isGain() ? 0 : $amount,
                'credit' => $adjustment->isGain() ? $amount : 0,
                'description' => $adjustment->description,
            ],
        ], [
            'posting_date' => $adjustment->posting_date,
            'document_type' => 'currency_adjustment',
            'document_number' => $adjustment->document_no,
            'description' => $adjustment->description,
            'sourceable_type' => CurrencyAdjustmentLedger::class,
            'sourceable_id' => $adjustment->id,
        ]);
    }

    private function postRevaluationSummary(array $adjustments, Currency $currency, string $documentNo, \DateTime $date): void
    {
        $totalGain = collect($adjustments)->where('type', 'gain')->sum('amount');
        $totalLoss = collect($adjustments)->where('type', 'loss')->sum('amount');

        // Post net amount to currency revaluation account
        // Implementation depends on your GL structure
    }
}
