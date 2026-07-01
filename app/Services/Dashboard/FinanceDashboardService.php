<?php

namespace App\Services\Dashboard;

use App\Enums\AccountCategory;
use App\Models\BankAccountLedgerEntry;
use App\Models\CustomerLedgerEntry;
use App\Models\GlEntry;
use App\Models\VendorLedgerEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceDashboardService
{
    public function __construct(
        private readonly ReconciliationWarningService $reconciliationWarningService
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate ??= now()->startOfMonth();
        $endDate ??= now();

        $revenue = $this->glByAccountCategory(AccountCategory::REVENUE, $startDate, $endDate, creditMinusDebit: true);
        $cogs = $this->glByAccountCategory(AccountCategory::COGS, $startDate, $endDate);
        $trialBalanceDifference = $this->trialBalanceDifference($startDate, $endDate);
        $financeWarnings = $this->reconciliationWarningService->financeWarnings();

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'cash_bank_balance' => round($this->cashBankBalance(), 2),
            'receivables' => round($this->receivables(), 2),
            'payables' => round($this->payables(), 2),
            'revenue' => round($revenue, 2),
            'cogs' => round($cogs, 2),
            'gross_profit' => round($revenue - $cogs, 2),
            'trial_balance' => [
                'difference' => round($trialBalanceDifference, 2),
                'is_balanced' => abs($trialBalanceDifference) < 0.01,
            ],
            'reconciliation_warnings' => $financeWarnings,
        ];
    }

    private function cashBankBalance(): float
    {
        return (float) BankAccountLedgerEntry::query()
            ->whereNull('deleted_at')
            ->sum('amount');
    }

    private function receivables(): float
    {
        return (float) CustomerLedgerEntry::query()
            ->where('reversed', false)
            ->sum(DB::raw('CASE WHEN debit_amount > 0 THEN ABS(remaining_amount) ELSE -ABS(remaining_amount) END'));
    }

    private function payables(): float
    {
        return (float) VendorLedgerEntry::query()
            ->where('reversed', false)
            ->sum(DB::raw('CASE WHEN credit_amount > 0 THEN ABS(remaining_amount) ELSE -ABS(remaining_amount) END'));
    }

    private function glByAccountCategory(AccountCategory $category, Carbon $startDate, Carbon $endDate, bool $creditMinusDebit = false): float
    {
        $expression = $creditMinusDebit
            ? 'gl_entries.credit_amount - gl_entries.debit_amount'
            : 'gl_entries.debit_amount - gl_entries.credit_amount';

        return (float) GlEntry::query()
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'gl_entries.chart_of_account_id')
            ->where('coa.account_category', $category->value)
            ->whereBetween('gl_entries.posting_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum(DB::raw($expression));
    }

    private function trialBalanceDifference(Carbon $startDate, Carbon $endDate): float
    {
        return (float) GlEntry::query()
            ->whereBetween('posting_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->sum(DB::raw('debit_amount - credit_amount'));
    }
}
