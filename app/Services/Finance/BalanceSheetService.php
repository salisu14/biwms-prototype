<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\AccountScheduleRowType;
use App\Enums\AccountScheduleTotalingType;
use App\Enums\AccountType;
use App\Enums\IncomeBalanceType;
use App\Models\AccountSchedule;
use App\Models\ChartOfAccount;
use App\Models\GlEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BalanceSheetService
{
    public function generate(Carbon $asOfDate): array
    {
        $accounts = ChartOfAccount::query()
            ->where('income_balance', IncomeBalanceType::BALANCE_SHEET)
            ->where('blocked', false)
            ->orderBy('account_number')
            ->get();

        $lines = $accounts->map(function (ChartOfAccount $account) use ($asOfDate): array {
            $rawBalance = $this->calculateBalanceAtDate($account, $asOfDate);
            $displayAmount = $this->normalizeDisplayAmount($account, $rawBalance);

            return [
                'account_no' => $account->account_number,
                'description' => $account->name,
                'account_type' => $account->account_type?->value ?? $account->account_type,
                'account_category' => $account->account_category?->value ?? $account->account_category,
                'indentation' => $account->indentation ?? 0,
                'bold' => (bool) ($account->bold || $account->isTotalAccount()),
                'is_total_account' => (bool) $account->isTotalAccount(),
                'amount' => $displayAmount,
            ];
        });

        $postingLines = $lines->filter(fn (array $line): bool => ! $line['is_total_account']);

        $totalAssets = $postingLines
            ->filter(fn (array $line): bool => in_array((string) $line['account_category'], [
                'asset',
                'liquid_asset',
                'receivable',
                'inventory',
                'fixed_asset',
            ], true))
            ->sum('amount');

        $totalLiabilities = $postingLines
            ->filter(fn (array $line): bool => in_array((string) $line['account_category'], [
                'liability',
                'payable',
            ], true))
            ->sum('amount');

        $totalEquity = $postingLines
            ->filter(fn (array $line): bool => (string) $line['account_category'] === 'equity')
            ->sum('amount');

        return [
            'as_of_date' => $asOfDate->toDateString(),
            'printed_at' => now()->format('Y-m-d H:i'),
            'lines' => $lines->values()->all(),
            'totals' => [
                'assets' => $totalAssets,
                'liabilities' => $totalLiabilities,
                'equity' => $totalEquity,
                'liabilities_and_equity' => $totalLiabilities + $totalEquity,
                'difference' => $totalAssets - ($totalLiabilities + $totalEquity),
            ],
        ];
    }

    public function generateFromSchedule(int $scheduleId, Carbon $asOfDate): array
    {
        $schedule = AccountSchedule::with('lines')->findOrFail($scheduleId);
        $results = collect();

        foreach ($schedule->lines as $line) {
            $amount = match ($line->totaling_type) {
                AccountScheduleTotalingType::POSTING_ACCOUNTS,
                AccountScheduleTotalingType::TOTAL_ACCOUNTS => $this->sumAccountsByRowType(
                    (string) $line->totaling,
                    $asOfDate,
                    $line->row_type ?? AccountScheduleRowType::BALANCE_AT_DATE
                ),
                AccountScheduleTotalingType::FORMULA => $this->calculateFormula((string) $line->totaling, $results->all()),
                default => 0.0,
            };

            $results->push([
                'account_no' => $line->row_no ?: '',
                'description' => $line->description,
                'account_type' => null,
                'indentation' => $line->indentation ?? 0,
                'bold' => (bool) $line->bold,
                'is_total_account' => false,
                'amount' => $line->show_opposite_sign ? $amount * -1 : $amount,
            ]);
        }

        return [
            'as_of_date' => $asOfDate->toDateString(),
            'printed_at' => now()->format('Y-m-d H:i'),
            'schedule_name' => $schedule->name,
            'lines' => $results->all(),
            'totals' => [
                'assets' => 0.0,
                'liabilities' => 0.0,
                'equity' => 0.0,
                'liabilities_and_equity' => 0.0,
                'difference' => 0.0,
            ],
        ];
    }

    private function calculateBalanceAtDate(ChartOfAccount $account, Carbon $asOfDate): float
    {
        // Some heading/total accounts are configured without explicit totaling.
        // For core inventory headings (13xxx), roll up posting accounts by prefix.
        if ($account->isTotalAccount() && empty($account->totaling) && str_starts_with((string) $account->account_number, '13')) {
            return (float) GlEntry::query()
                ->whereHas('chartOfAccount', function ($query): void {
                    $query->where('account_number', 'like', '13%')
                        ->where('structural_type', 'POSTING');
                })
                ->whereDate('posting_date', '<=', $asOfDate)
                ->sum(DB::raw('debit_amount - credit_amount'));
        }

        if ($account->isTotalAccount() && ! empty($account->totaling)) {
            $accountCodes = $this->parseTotaling($account->totaling);

            return (float) GlEntry::query()
                ->whereHas('chartOfAccount', function ($query) use ($accountCodes): void {
                    $query->whereIn('account_number', $accountCodes);
                })
                ->whereDate('posting_date', '<=', $asOfDate)
                ->sum(DB::raw('debit_amount - credit_amount'));
        }

        return (float) GlEntry::query()
            ->where('chart_of_account_id', $account->id)
            ->whereDate('posting_date', '<=', $asOfDate)
            ->sum(DB::raw('debit_amount - credit_amount'));
    }

    private function normalizeDisplayAmount(ChartOfAccount $account, float $rawBalance): float
    {
        $accountType = strtoupper((string) ($account->account_type?->value ?? $account->account_type));

        if (in_array((string) ($account->account_category?->value ?? $account->account_category), ['liability', 'payable', 'equity'], true)) {
            return $rawBalance * -1;
        }

        if (in_array($accountType, [AccountType::LIABILITY->value, AccountType::EQUITY->value], true)) {
            return $rawBalance * -1;
        }

        return $rawBalance;
    }

    /**
     * @return array<int, string>
     */
    private function parseTotaling(?string $totaling): array
    {
        if (! $totaling) {
            return [];
        }

        if (str_contains($totaling, '|')) {
            return array_map('trim', explode('|', $totaling));
        }

        if (str_contains($totaling, '..')) {
            [$start, $end] = explode('..', $totaling);

            return ChartOfAccount::query()
                ->whereBetween('account_number', [trim($start), trim($end)])
                ->pluck('account_number')
                ->all();
        }

        return [trim($totaling)];
    }

    private function sumAccountsByRowType(string $totaling, Carbon $asOfDate, AccountScheduleRowType|string|null $rowType): float
    {
        $accountCodes = $this->parseTotaling($totaling);
        $query = GlEntry::query()->whereHas('chartOfAccount', function ($query) use ($accountCodes): void {
            $query->whereIn('account_number', $accountCodes);
        });

        $resolvedRowType = $rowType instanceof AccountScheduleRowType
            ? $rowType
            : AccountScheduleRowType::tryFrom((string) $rowType);

        return match ($resolvedRowType) {
            AccountScheduleRowType::NET_CHANGE => (float) $query
                ->whereBetween('posting_date', [$asOfDate->copy()->startOfYear(), $asOfDate])
                ->sum(DB::raw('debit_amount - credit_amount')),
            AccountScheduleRowType::BEGINNING_BALANCE => (float) $query
                ->whereDate('posting_date', '<', $asOfDate->copy()->startOfYear())
                ->sum(DB::raw('debit_amount - credit_amount')),
            default => (float) $query
                ->whereDate('posting_date', '<=', $asOfDate)
                ->sum(DB::raw('debit_amount - credit_amount')),
        };
    }

    /**
     * @param  array<int, array{row_no?: string, amount?: float|int|string}>  $previousResults
     */
    private function calculateFormula(string $formula, array $previousResults): float
    {
        $expression = $formula;

        foreach ($previousResults as $result) {
            if (! empty($result['row_no'])) {
                $expression = str_replace((string) $result['row_no'], (string) ($result['amount'] ?? 0), $expression);
            }
        }

        if (preg_match('/[^0-9\+\-\*\/\(\)\. ]/', $expression)) {
            return 0.0;
        }

        try {
            return (float) eval("return {$expression};");
        } catch (\Throwable) {
            return 0.0;
        }
    }
}
