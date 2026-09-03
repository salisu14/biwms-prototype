<?php

namespace App\Services\Dashboard;

use App\Enums\AccountCategory;
use App\Models\BankAccountLedgerEntry;
use App\Models\CustomerLedgerEntry;
use App\Models\GlEntry;
use App\Models\SubledgerOpeningBalance;
use App\Models\VendorLedgerEntry;
use App\Services\Business\BusinessContextService;
use App\Services\IncomeStatementService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class FinanceDashboardService
{
    public function __construct(
        private readonly ReconciliationWarningService $reconciliationWarningService,
        private readonly BusinessContextService $businessContext
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(?Carbon $startDate = null, ?Carbon $endDate = null, ?int $businessId = null): array
    {
        $startDate ??= now()->startOfMonth();
        $endDate ??= now();
        $businessId = $this->businessContext->resolveId($businessId);

        $revenue = $this->glByAccountCategory(AccountCategory::REVENUE, $startDate, $endDate, creditMinusDebit: true, businessId: $businessId);
        $cogs = $this->glByAccountCategory(AccountCategory::COGS, $startDate, $endDate, businessId: $businessId);
        $trialBalanceDifference = $this->trialBalanceDifference($startDate, $endDate, $businessId);
        $financeWarnings = $this->reconciliationWarningService->financeWarnings($businessId);
        $incomeStatement = app(IncomeStatementService::class)->generate(
            fromDate: $startDate,
            toDate: $endDate,
            businessId: $businessId,
        )->summary;
        $revenue = (float) ($incomeStatement['total_revenue'] ?? $revenue);
        $cogs = (float) ($incomeStatement['total_cogs'] ?? $cogs);
        $netIncome = (float) ($incomeStatement['net_income'] ?? ($revenue - $cogs));

        return [
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'cash_bank_balance' => round($this->cashBankBalance($businessId), 2),
            'receivables' => round($this->receivables($businessId), 2),
            'payables' => round($this->payables($businessId), 2),
            'revenue' => round($revenue, 2),
            'cogs' => round($cogs, 2),
            'gross_profit' => round($revenue - $cogs, 2),
            'gross_margin_percent' => $revenue > 0 ? round((($revenue - $cogs) / $revenue) * 100, 2) : null,
            'operating_expenses' => round((float) ($incomeStatement['operating_expenses'] ?? 0), 2),
            'operating_profit' => round((float) ($incomeStatement['operating_income'] ?? ($revenue - $cogs)), 2),
            'operating_margin_percent' => $revenue > 0
                ? round(((float) ($incomeStatement['operating_income'] ?? ($revenue - $cogs)) / $revenue) * 100, 2)
                : null,
            'net_profit_loss' => round($netIncome, 2),
            'net_margin_percent' => $revenue > 0 ? round(($netIncome / $revenue) * 100, 2) : null,
            'trial_balance' => [
                'difference' => round($trialBalanceDifference, 2),
                'is_balanced' => abs($trialBalanceDifference) < 0.01,
            ],
            'reconciliation_warnings' => $financeWarnings,
            'reconciliation_scope' => $financeWarnings['scope'] ?? 'global',
        ];
    }

    private function cashBankBalance(?int $businessId = null): float
    {
        return (float) BankAccountLedgerEntry::query()
            ->whereNull('deleted_at')
            ->when($businessId !== null, fn ($query) => $query->whereHas(
                'glEntry',
                fn ($glQuery) => $glQuery->where('business_id', $businessId)
            ))
            ->sum('amount_lcy');
    }

    private function receivables(?int $businessId = null): float
    {
        $remainingLcy = $this->remainingAmountLcyExpression('customer_ledger_entries');

        return (float) CustomerLedgerEntry::query()
            ->where('reversed', false)
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->sum(DB::raw("CASE WHEN debit_amount > 0 THEN {$remainingLcy} ELSE -({$remainingLcy}) END"));
    }

    private function payables(?int $businessId = null): float
    {
        $remainingLcy = $this->remainingAmountLcyExpression('vendor_ledger_entries');

        return (float) VendorLedgerEntry::query()
            ->where('reversed', false)
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->sum(DB::raw("CASE WHEN credit_amount > 0 THEN {$remainingLcy} ELSE -({$remainingLcy}) END"));
    }

    private function glByAccountCategory(AccountCategory $category, Carbon $startDate, Carbon $endDate, bool $creditMinusDebit = false, ?int $businessId = null): float
    {
        $expression = $creditMinusDebit
            ? 'gl_entries.credit_amount - gl_entries.debit_amount'
            : 'gl_entries.debit_amount - gl_entries.credit_amount';

        return (float) GlEntry::query()
            ->join('chart_of_accounts as coa', 'coa.id', '=', 'gl_entries.chart_of_account_id')
            ->where('coa.account_category', $category->value)
            ->whereBetween('gl_entries.posting_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($businessId !== null, fn ($query) => $query->where('gl_entries.business_id', $businessId))
            ->sum(DB::raw($expression));
    }

    private function trialBalanceDifference(Carbon $startDate, Carbon $endDate, ?int $businessId = null): float
    {
        return (float) GlEntry::query()
            ->whereBetween('posting_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->sum(DB::raw('COALESCE(debit_amount_lcy, debit_amount) - COALESCE(credit_amount_lcy, credit_amount)'));
    }

    private function remainingAmountLcyExpression(string $table): string
    {
        return "CASE WHEN {$table}.source_type = '".SubledgerOpeningBalance::class."' THEN ABS({$table}.remaining_amount) ELSE ABS({$table}.remaining_amount * COALESCE({$table}.currency_factor, 1)) END";
    }
}
