<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\AccountScheduleTotalingType;
use App\Enums\AccountType;
use App\Enums\IncomeBalanceType;
use App\Models\AccountSchedule;
use App\Models\ChartOfAccount;
use App\Models\ExpenseBudget;
use App\Models\GlEntry;
use App\Services\Finance\GeneralLedgerService;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class IncomeStatementService
{
    public function __construct(
        private readonly GeneralLedgerService $glService
    ) {}

    /**
     * Generate BC-style P&L Report
     */
    public function generate(
        Carbon $fromDate,
        Carbon $toDate,
        ?string $globalDimension1 = null,  // BC Shortcut Dimension 1 (Department)
        ?string $globalDimension2 = null,  // BC Shortcut Dimension 2 (Project)
        ?Carbon $compareFrom = null,       // Comparative period
        ?Carbon $compareTo = null,
        bool $showBudget = false,
        int $fiscalYear = 0
    ): IncomeStatementReport {
        // Get all income statement accounts ordered by G/L Account No.
        $accounts = $this->getIncomeStatementAccounts();

        $rows = collect();

        foreach ($accounts as $account) {
            $netChange = $this->calculateNetChange(
                $account,
                $fromDate,
                $toDate,
                $globalDimension1,
                $globalDimension2
            );

            $compareAmount = ($compareFrom && $compareTo)
                ? $this->calculateNetChange($account, $compareFrom, $compareTo, $globalDimension1, $globalDimension2)
                : null;

            $budgetAmount = $showBudget
                ? $this->getBudgetAmount($account, $fromDate, $toDate, $fiscalYear)
                : null;

            $rows->push([
                'account_no' => $account->account_number,
                'account_name' => $account->name,
                'account_type' => $account->account_type,
                'indentation' => $account->indentation,
                'bold' => $account->bold || $account->isTotalAccount(),
                'is_total_account' => $account->isTotalAccount(),
                'net_change' => $this->applySign($netChange, $account),
                'compare_amount' => $compareAmount !== null ? $this->applySign($compareAmount, $account) : null,
                'budget_amount' => $budgetAmount,
                'variance' => $budgetAmount !== null ? $this->applySign($netChange, $account) - $budgetAmount : null,
                'variance_percent' => $this->calculateVariancePercent($netChange, $budgetAmount, $account),
            ]);
        }

        // Calculate totals (Revenue, COGS, Gross Profit, etc.)
        $summary = $this->calculateSummary($rows);

        return new IncomeStatementReport(
            rows: $rows,
            summary: $summary,
            period: "{$fromDate->format('Y-m-d')}..{$toDate->format('Y-m-d')}",
            dimensions: [
                'global_dimension_1' => $globalDimension1,
                'global_dimension_2' => $globalDimension2,
            ]
        );
    }

    /**
     * BC-Style Account Schedule P&L (Custom row definitions)
     */
    public function generateFromSchedule(
        int $scheduleId,
        Carbon $fromDate,
        Carbon $toDate,
        ?string $dim1 = null,
        ?string $dim2 = null
    ): Collection {
        $schedule = AccountSchedule::with('lines')->findOrFail($scheduleId);
        $results = collect();

        foreach ($schedule->lines as $line) {
            $amount = match ($line->totaling_type) {
                AccountScheduleTotalingType::POSTING_ACCOUNTS,
                AccountScheduleTotalingType::TOTAL_ACCOUNTS => $this->sumAccounts($line->totaling, $fromDate, $toDate, $dim1, $dim2),
                AccountScheduleTotalingType::FORMULA => $this->calculateFormula($line->totaling, $results),
                default => 0,
            };

            $result = [
                'row_no' => $line->row_no,
                'description' => $line->description,
                'bold' => $line->bold,
                'italic' => $line->italic,
                'underline' => $line->underline,
                'indentation' => $line->indentation,
                'new_page' => $line->new_page,
                'amount' => $line->show_opposite_sign ? $amount * -1 : $amount,
            ];

            $results->push($result);
        }

        return $results;
    }

    // Private methods...

    private function getIncomeStatementAccounts(): Collection
    {
        return ChartOfAccount::query()
            ->where('blocked', false)
            ->where(function ($query): void {
                $query->where('income_balance', IncomeBalanceType::INCOME_STATEMENT)
                    // Defensive fallback: include known P&L account types even if income_balance is misclassified.
                    ->orWhereIn('account_type', [
                        'REVENUE',
                        'COGS',
                        'EXPENSE',
                        'INTEREST',
                        'TAX',
                        'direct_expense',
                        'indirect_expense',
                        'other_income',
                        'other_expense',
                    ]);
            })
            ->orderBy('account_number')
            ->get();
    }

    private function calculateNetChange(
        ChartOfAccount $account,
        Carbon $from,
        Carbon $to,
        ?string $dim1,
        ?string $dim2
    ): float {
        // Total accounts calculate from totaling range
        if ($account->isTotalAccount()) {
            return $this->calculateTotalAccount($account, $from, $to, $dim1, $dim2);
        }

        $query = GlEntry::where('chart_of_account_id', $account->id)
            ->whereBetween('posting_date', [$from, $to]);

        if ($dim1) {
            $query->where('shortcut_dimension_1_code', $dim1);
        }
        if ($dim2) {
            $query->where('shortcut_dimension_2_code', $dim2);
        }

        // Net Change = Sum(Debit) - Sum(Credit)
        return (float) $query->sum(DB::raw('debit_amount - credit_amount'));
    }

    private function calculateTotalAccount(
        ChartOfAccount $account,
        Carbon $from,
        Carbon $to,
        ?string $dim1,
        ?string $dim2
    ): float {
        if (empty($account->totaling)) {
            return 0;
        }

        // Parse totaling range (e.g., "4100..4199" or "4100|4200|4300")
        $accountCodes = $this->parseTotaling($account->totaling);

        $query = GlEntry::whereHas('chartOfAccount', function ($q) use ($accountCodes) {
            $q->whereIn('account_number', $accountCodes);
        })->whereBetween('posting_date', [$from, $to]);

        if ($dim1) {
            $query->where('shortcut_dimension_1_code', $dim1);
        }
        if ($dim2) {
            $query->where('shortcut_dimension_2_code', $dim2);
        }

        return (float) $query->sum(DB::raw('debit_amount - credit_amount'));
    }

    private function parseTotaling(?string $totaling): array
    {
        if (! $totaling) {
            return [];
        }

        $codes = [];

        // Handle lists (4100|4200|4300)
        if (str_contains($totaling, '|')) {
            $codes = array_map('trim', explode('|', $totaling));
        }
        // Handle ranges (4100..4199)
        elseif (str_contains($totaling, '..')) {
            [$start, $end] = explode('..', $totaling);
            $codes = ChartOfAccount::whereBetween('account_number', [trim($start), trim($end)])
                ->pluck('account_number')
                ->toArray();
        }
        // Single account
        else {
            $codes = [trim($totaling)];
        }

        return $codes;
    }

    private function sumAccounts(string $totaling, Carbon $from, Carbon $to, ?string $dim1, ?string $dim2): float
    {
        $accountCodes = $this->parseTotaling($totaling);

        $query = GlEntry::whereHas('chartOfAccount', function ($q) use ($accountCodes) {
            $q->whereIn('account_number', $accountCodes);
        })->whereBetween('posting_date', [$from, $to]);

        if ($dim1) {
            $query->where('shortcut_dimension_1_code', $dim1);
        }
        if ($dim2) {
            $query->where('shortcut_dimension_2_code', $dim2);
        }

        return (float) $query->sum(DB::raw('debit_amount - credit_amount'));
    }

    private function calculateFormula(string $formula, Collection $previousResults): float
    {
        // Simple formula parser for ROW NOs like "10 + 20"
        // Replace ROWIDs with their values
        $expression = $formula;
        foreach ($previousResults as $res) {
            if ($res['row_no']) {
                $expression = str_replace($res['row_no'], (string) $res['amount'], $expression);
            }
        }

        // Security check: only allow numbers and basic operators
        if (preg_match('/[^0-9\+\-\*\/\(\)\. ]/', $expression)) {
            return 0;
        }

        try {
            return (float) eval("return {$expression};");
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function calculateVariancePercent(float $actual, ?float $budget, ChartOfAccount $account): ?float
    {
        if ($budget === null || $budget == 0) {
            return null;
        }
        $actualSigned = $this->applySign($actual, $account);

        return (($actualSigned - $budget) / abs($budget)) * 100;
    }

    private function applySign(float $amount, ChartOfAccount $account): float
    {
        // BC logic: Revenue accounts normally credit (negative),
        // but P&L shows them as positive
        if ($account->show_opposite_sign || $this->isRevenueAccount($account)) {
            return $amount * -1;
        }

        return $amount;
    }

    private function calculateSummary(Collection $rows): array
    {
        $postingRows = $rows->filter(fn ($row) => ! ($row['is_total_account'] ?? false));

        $currentRevenue = $postingRows->filter(fn ($r) => $this->classifyRowType($r) === 'REVENUE')->sum('net_change');
        $currentCogs = $postingRows->filter(fn ($r) => $this->classifyRowType($r) === 'COGS')->sum('net_change');
        $currentExp = $postingRows->filter(fn ($r) => $this->classifyRowType($r) === 'EXPENSE')->sum('net_change');

        $compareRevenue = $postingRows->filter(fn ($r) => $this->classifyRowType($r) === 'REVENUE')->sum('compare_amount');
        $compareCogs = $postingRows->filter(fn ($r) => $this->classifyRowType($r) === 'COGS')->sum('compare_amount');
        $compareExp = $postingRows->filter(fn ($r) => $this->classifyRowType($r) === 'EXPENSE')->sum('compare_amount');

        return [
            'total_revenue' => $currentRevenue,
            'total_cogs' => $currentCogs,
            'gross_profit' => $currentRevenue - $currentCogs,
            'gross_profit_margin' => $currentRevenue > 0 ? (($currentRevenue - $currentCogs) / $currentRevenue) * 100 : 0,
            'operating_expenses' => $currentExp,
            'operating_income' => ($currentRevenue - $currentCogs) - $currentExp,
            'net_income' => ($currentRevenue - $currentCogs) - $currentExp,

            'compare_total_revenue' => $compareRevenue,
            'compare_total_cogs' => $compareCogs,
            'compare_gross_profit' => $compareRevenue - $compareCogs,
            'compare_operating_expenses' => $compareExp,
            'compare_net_income' => ($compareRevenue - $compareCogs) - $compareExp,
        ];
    }

    private function classifyRowType(array $row): string
    {
        $accountType = $row['account_type'] ?? null;
        $accountNo = (string) ($row['account_no'] ?? '');

        if ($accountType instanceof AccountType) {
            if ($accountType === AccountType::REVENUE || $accountType === AccountType::OTHER_INCOME) {
                return 'REVENUE';
            }

            if ($accountType === AccountType::COGS) {
                return 'COGS';
            }

            if (in_array($accountType, [AccountType::EXPENSE, AccountType::DIRECT_EXPENSE, AccountType::INDIRECT_EXPENSE, AccountType::OTHER_EXPENSE, AccountType::INTEREST, AccountType::TAX], true)) {
                return 'EXPENSE';
            }
        }

        $accountTypeString = strtoupper((string) $accountType);

        if (in_array($accountTypeString, ['REVENUE', 'SALES', 'OTHER_INCOME'], true)) {
            return 'REVENUE';
        }

        if ($accountTypeString === 'COGS') {
            return 'COGS';
        }

        if (in_array($accountTypeString, ['EXPENSE', 'DIRECT_EXPENSE', 'INDIRECT_EXPENSE', 'OTHER_EXPENSE', 'INTEREST', 'TAX'], true)) {
            return 'EXPENSE';
        }

        // Defensive fallback by account number band when account_type is dirty.
        if (str_starts_with($accountNo, '4')) {
            return 'REVENUE';
        }

        if (str_starts_with($accountNo, '5')) {
            return 'COGS';
        }

        if (str_starts_with($accountNo, '6') || str_starts_with($accountNo, '7') || str_starts_with($accountNo, '8')) {
            return 'EXPENSE';
        }

        return 'OTHER';
    }

    private function isRevenueAccount(ChartOfAccount $account): bool
    {
        if ($account->account_type instanceof AccountType) {
            return in_array($account->account_type, [AccountType::REVENUE, AccountType::OTHER_INCOME], true);
        }

        $typeString = strtoupper((string) $account->account_type);
        if (in_array($typeString, ['REVENUE', 'SALES', 'OTHER_INCOME'], true)) {
            return true;
        }

        return str_starts_with((string) $account->account_number, '4');
    }

    private function getBudgetAmount(
        ChartOfAccount $account,
        Carbon $from,
        Carbon $to,
        int $fiscalYear
    ): ?float {
        // Basic lookup in ExpenseBudget if exists for this account category/code
        // This is a simplified implementation
        $budget = ExpenseBudget::where('category_code', $account->account_number)
            ->where('fiscal_year', $fiscalYear ?: $from->year)
            ->first();

        if (! $budget) {
            return null;
        }

        $amount = 0;
        $startMonth = $from->month;
        $endMonth = $to->month;

        for ($m = $startMonth; $m <= $endMonth; $m++) {
            $amount += $budget->getMonthValue($m);
        }

        return (float) $amount;
    }
}
