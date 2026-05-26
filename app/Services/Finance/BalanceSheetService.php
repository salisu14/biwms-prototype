<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\AccountType;
use App\Enums\IncomeBalanceType;
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
                'account_type' => $account->account_type?->value ?? null,
                'indentation' => $account->indentation ?? 0,
                'bold' => (bool) ($account->bold || $account->isTotalAccount()),
                'is_total_account' => (bool) $account->isTotalAccount(),
                'amount' => $displayAmount,
            ];
        });

        $postingLines = $lines->filter(fn (array $line): bool => ! $line['is_total_account']);

        $totalAssets = $postingLines
            ->filter(fn (array $line): bool => strtoupper((string) $line['account_type']) === AccountType::ASSET->value)
            ->sum('amount');

        $totalLiabilities = $postingLines
            ->filter(fn (array $line): bool => strtoupper((string) $line['account_type']) === AccountType::LIABILITY->value)
            ->sum('amount');

        $totalEquity = $postingLines
            ->filter(fn (array $line): bool => strtoupper((string) $line['account_type']) === AccountType::EQUITY->value)
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
}
