<?php

namespace App\Services\Finance;

use App\Enums\SourceType;
use App\Models\ChartOfAccount;
use App\Models\GlEntry;
use Illuminate\Support\Facades\DB;

class GeneralLedgerService
{
    /**
     * Post a journal entry to the General Ledger.
     *
     * @param array<int, array{
     *     account_id: int,
     *     debit: float,
     *     credit: float,
     *     description?: string,
     *     dimensions?: array
     * }> $lines
     * @param array{
     *     posting_date?: \DateTime|string,
     *     document_number?: string,
     *     document_date?: \DateTime|string,
     *     source_type?: string,
     *     source_number?: string,
     *     document_type?: string,
     *     description?: string,
     *     dimensions?: array
     * } $meta
     *
     * @throws \Exception
     * @throws \Throwable
     */
    public function post(array $lines, array $meta = []): void
    {
        DB::transaction(function () use ($lines, $meta) {
            $totalDebit = collect($lines)->sum('debit');
            $totalCredit = collect($lines)->sum('credit');

            if (round($totalDebit, 2) !== round($totalCredit, 2)) {
                throw new \Exception('Journal not balanced: Debit ('.$totalDebit.') != Credit ('.$totalCredit.')');
            }

            $transactionNumber = $this->generateTransactionNumber();
            $nextEntryNumber = $this->getNextEntryNumber();

            foreach ($lines as $line) {
                GlEntry::create([
                    'entry_number' => $nextEntryNumber++,
                    'transaction_number' => $transactionNumber,
                    'chart_of_account_id' => $line['account_id'],
                    'debit_amount' => $line['debit'],
                    'credit_amount' => $line['credit'],
                    'amount' => $line['debit'] - $line['credit'],
                    'posting_date' => $meta['posting_date'] ?? now(),
                    'document_number' => $meta['document_number'] ?? '',
                    'document_date' => $meta['document_date'] ?? now(),

                    // Fix: Allow Line-level or Meta-level source override
                    'source_type' => $line['source_type'] ?? $meta['source_type'] ?? SourceType::GENERAL_JOURNAL->value,
                    'source_number' => $line['source_number'] ?? $meta['source_number'] ?? null,
                    'document_type' => $meta['document_type'] ?? 'JOURNAL',

                    // Fix: Polymorphic tracking for Expense Transactions
                    'sourceable_type' => $meta['sourceable_type'] ?? null,
                    'sourceable_id' => $meta['sourceable_id'] ?? null,

                    'description' => $line['description'] ?? $meta['description'] ?? 'G/L Entry',
                    'dimensions' => array_merge($meta['dimensions'] ?? [], $line['dimensions'] ?? []),
                    'user_id' => auth()->id(),

                    // FIX: Map the new fields we added in ExpenseService
                    'currency_id' => $line['currency_id'] ?? null,
                    'debit_amount_lcy' => $line['debit_amount_lcy'] ?? null,
                    'credit_amount_lcy' => $line['credit_amount_lcy'] ?? null,

                    // FIX: Map Dimension Shortcuts
                    'shortcut_dimension_1_code' => $line['shortcut_dimension_1_code'] ?? null,
                    'shortcut_dimension_2_code' => $line['shortcut_dimension_2_code'] ?? null,
                ]);
            }
        });
    }

    /**
     * Get trial balance for a specified date range.
     */
    public function getTrialBalance($startDate, $endDate)
    {
        return ChartOfAccount::withSum(['glEntries as debit' => function ($q) use ($startDate, $endDate) {
            $q->whereBetween('posting_date', [$startDate, $endDate]);
        }], 'debit_amount')
            ->withSum(['glEntries as credit' => function ($q) use ($startDate, $endDate) {
                $q->whereBetween('posting_date', [$startDate, $endDate]);
            }], 'credit_amount')
            ->get();
    }

    protected function generateTransactionNumber(): int
    {
        return (GlEntry::max('transaction_number') ?? 0) + 1;
    }

    protected function getNextEntryNumber(): int
    {
        return (GlEntry::max('entry_number') ?? 0) + 1;
    }
}
