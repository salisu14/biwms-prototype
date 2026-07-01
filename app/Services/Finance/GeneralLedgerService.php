<?php

namespace App\Services\Finance;

use App\Enums\SourceType;
use App\Models\ChartOfAccount;
use App\Models\GlEntry;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
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
     * Get trial balance for a specified date range from G/L entries only.
     *
     * @param  array{
     *     account_id?: int,
     *     account_ids?: array<int, int>,
     *     account_number?: string,
     *     general_business_posting_group_id?: int,
     *     shortcut_dimension_1_code?: string,
     *     shortcut_dimension_2_code?: string,
     *     dimensions?: array<string, scalar|null>
     * }  $filters
     */
    public function getTrialBalance($startDate, $endDate, array $filters = []): array
    {
        return $this->trialBalance(Carbon::parse($startDate), Carbon::parse($endDate), $filters);
    }

    /**
     * @param  array{
     *     account_id?: int,
     *     account_ids?: array<int, int>,
     *     account_number?: string,
     *     general_business_posting_group_id?: int,
     *     shortcut_dimension_1_code?: string,
     *     shortcut_dimension_2_code?: string,
     *     dimensions?: array<string, scalar|null>
     * }  $filters
     * @return array{period: array{start: string, end: string}, filters: array<string, mixed>, accounts: array<int, array<string, mixed>>, totals: array{debit: float, credit: float, difference: float}, is_balanced: bool}
     */
    public function trialBalance(Carbon $startDate, Carbon $endDate, array $filters = []): array
    {
        $accounts = ChartOfAccount::query()
            ->whereHas('glEntries', fn (Builder $query): Builder => $this->applyGlEntryFilters($query, $startDate, $endDate, $filters))
            ->when($filters['account_id'] ?? null, fn (Builder $query, int $accountId): Builder => $query->whereKey($accountId))
            ->when($filters['account_ids'] ?? null, fn (Builder $query, array $accountIds): Builder => $query->whereKey($accountIds))
            ->when($filters['account_number'] ?? null, fn (Builder $query, string $accountNumber): Builder => $query->where('account_number', $accountNumber))
            ->orderBy('account_number')
            ->get();

        $rows = $accounts->map(function (ChartOfAccount $account) use ($startDate, $endDate, $filters): array {
            $totals = GlEntry::query()
                ->where('chart_of_account_id', $account->id)
                ->tap(fn (Builder $query): Builder => $this->applyGlEntryFilters($query, $startDate, $endDate, $filters))
                ->selectRaw('COALESCE(SUM(debit_amount), 0) as debit_total, COALESCE(SUM(credit_amount), 0) as credit_total')
                ->first();

            $debit = round((float) ($totals->debit_total ?? 0), 2);
            $credit = round((float) ($totals->credit_total ?? 0), 2);

            return [
                'account_id' => $account->id,
                'account_number' => $account->account_number,
                'account_name' => $account->name,
                'account_category' => $account->account_category?->value ?? $account->account_category,
                'debit' => $debit,
                'credit' => $credit,
                'net_change' => round($debit - $credit, 2),
            ];
        })->values();

        $debitTotal = round((float) $rows->sum('debit'), 2);
        $creditTotal = round((float) $rows->sum('credit'), 2);
        $difference = round($debitTotal - $creditTotal, 2);

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'filters' => $filters,
            'accounts' => $rows->all(),
            'totals' => [
                'debit' => $debitTotal,
                'credit' => $creditTotal,
                'difference' => $difference,
            ],
            'is_balanced' => abs($difference) < 0.01,
        ];
    }

    /**
     * @param  array{
     *     account_id?: int,
     *     account_ids?: array<int, int>,
     *     account_number?: string,
     *     general_business_posting_group_id?: int,
     *     shortcut_dimension_1_code?: string,
     *     shortcut_dimension_2_code?: string,
     *     dimensions?: array<string, scalar|null>
     * }  $filters
     * @return array{period: array{start: string, end: string}, filters: array<string, mixed>, accounts: array<int, array<string, mixed>>}
     */
    public function generalLedgerReport(Carbon $startDate, Carbon $endDate, array $filters = []): array
    {
        $accounts = ChartOfAccount::query()
            ->whereHas('glEntries', function (Builder $query) use ($endDate, $filters): Builder {
                return $this->applyGlEntryFilters($query, null, $endDate, $filters);
            })
            ->when($filters['account_id'] ?? null, fn (Builder $query, int $accountId): Builder => $query->whereKey($accountId))
            ->when($filters['account_ids'] ?? null, fn (Builder $query, array $accountIds): Builder => $query->whereKey($accountIds))
            ->when($filters['account_number'] ?? null, fn (Builder $query, string $accountNumber): Builder => $query->where('account_number', $accountNumber))
            ->orderBy('account_number')
            ->get();

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'filters' => $filters,
            'accounts' => $accounts
                ->map(fn (ChartOfAccount $account): array => $this->generalLedgerAccountSection($account, $startDate, $endDate, $filters))
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function generalLedgerAccountSection(ChartOfAccount $account, Carbon $startDate, Carbon $endDate, array $filters): array
    {
        $openingBalance = (float) GlEntry::query()
            ->where('chart_of_account_id', $account->id)
            ->tap(fn (Builder $query): Builder => $this->applyGlEntryFilters($query, null, $startDate->copy()->subDay(), $filters))
            ->sum(DB::raw('debit_amount - credit_amount'));

        $entries = GlEntry::query()
            ->where('chart_of_account_id', $account->id)
            ->tap(fn (Builder $query): Builder => $this->applyGlEntryFilters($query, $startDate, $endDate, $filters))
            ->orderBy('posting_date')
            ->orderBy('entry_number')
            ->get();

        $periodDebit = round((float) $entries->sum('debit_amount'), 2);
        $periodCredit = round((float) $entries->sum('credit_amount'), 2);
        $closingBalance = round($openingBalance + $periodDebit - $periodCredit, 2);

        return [
            'account_id' => $account->id,
            'account_number' => $account->account_number,
            'account_name' => $account->name,
            'opening_balance' => round($openingBalance, 2),
            'period_debit' => $periodDebit,
            'period_credit' => $periodCredit,
            'closing_balance' => $closingBalance,
            'entries' => $entries->map(fn (GlEntry $entry): array => [
                'entry_number' => $entry->entry_number,
                'posting_date' => $entry->posting_date?->toDateString(),
                'document_type' => $entry->document_type,
                'document_number' => $entry->document_number,
                'source_type' => $entry->source_type?->value ?? $entry->source_type,
                'source_number' => $entry->source_number,
                'sourceable_type' => $entry->sourceable_type,
                'sourceable_id' => $entry->sourceable_id,
                'description' => $entry->description,
                'debit' => round((float) $entry->debit_amount, 2),
                'credit' => round((float) $entry->credit_amount, 2),
                'amount' => round((float) $entry->amount, 2),
                'shortcut_dimension_1_code' => $entry->shortcut_dimension_1_code,
                'shortcut_dimension_2_code' => $entry->shortcut_dimension_2_code,
                'dimensions' => $entry->dimensions,
            ])->values()->all(),
        ];
    }

    private function applyGlEntryFilters(Builder $query, ?Carbon $startDate, ?Carbon $endDate, array $filters): Builder
    {
        if ($startDate && $endDate) {
            $query->whereBetween('posting_date', [$startDate->toDateString(), $endDate->toDateString()]);
        } elseif ($endDate) {
            $query->whereDate('posting_date', '<=', $endDate->toDateString());
        }

        if ($generalBusinessPostingGroupId = $filters['general_business_posting_group_id'] ?? null) {
            $query->where('general_business_posting_group_id', $generalBusinessPostingGroupId);
        }

        if ($dimension1 = $filters['shortcut_dimension_1_code'] ?? null) {
            $query->where('shortcut_dimension_1_code', $dimension1);
        }

        if ($dimension2 = $filters['shortcut_dimension_2_code'] ?? null) {
            $query->where('shortcut_dimension_2_code', $dimension2);
        }

        foreach (($filters['dimensions'] ?? []) as $key => $value) {
            $query->where("dimensions->{$key}", $value);
        }

        return $query;
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
