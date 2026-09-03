<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\IncomeBalanceType;
use App\Models\ChartOfAccount;
use App\Models\GlEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GroupSummaryService
{
    /** @var array<string, array{label:string, statement:string, normal:string, account_categories:list<string>}> */
    public const CATEGORIES = [
        'CAPITAL_ACCOUNT' => ['label' => 'Capital Account', 'statement' => 'BALANCE_SHEET', 'normal' => 'CREDIT', 'account_categories' => ['equity']],
        'CURRENT_LIABILITIES' => ['label' => 'Current Liabilities', 'statement' => 'BALANCE_SHEET', 'normal' => 'CREDIT', 'account_categories' => ['liability', 'payable']],
        'FIXED_ASSETS' => ['label' => 'Fixed Assets', 'statement' => 'BALANCE_SHEET', 'normal' => 'DEBIT', 'account_categories' => ['fixed_asset']],
        'INVESTMENTS' => ['label' => 'Investments', 'statement' => 'BALANCE_SHEET', 'normal' => 'DEBIT', 'account_categories' => []],
        'CURRENT_ASSETS' => ['label' => 'Current Assets', 'statement' => 'BALANCE_SHEET', 'normal' => 'DEBIT', 'account_categories' => ['asset', 'liquid_asset', 'receivable', 'inventory']],
        'BRANCH_DIVISIONS' => ['label' => 'Branch / Divisions', 'statement' => 'BALANCE_SHEET', 'normal' => 'MIXED', 'account_categories' => []],
        'SUSPENSE' => ['label' => 'Suspense A/c', 'statement' => 'BALANCE_SHEET', 'normal' => 'MIXED', 'account_categories' => []],
        'SALES_ACCOUNTS' => ['label' => 'Sales Accounts', 'statement' => 'INCOME_STATEMENT', 'normal' => 'CREDIT', 'account_categories' => ['revenue']],
        'PURCHASE_ACCOUNTS' => ['label' => 'Purchase Accounts', 'statement' => 'INCOME_STATEMENT', 'normal' => 'DEBIT', 'account_categories' => ['cogs']],
        'DIRECT_EXPENSES' => ['label' => 'Direct Expenses', 'statement' => 'INCOME_STATEMENT', 'normal' => 'DEBIT', 'account_categories' => ['direct_expense']],
        'INDIRECT_INCOMES' => ['label' => 'Indirect Incomes', 'statement' => 'INCOME_STATEMENT', 'normal' => 'CREDIT', 'account_categories' => ['other_income_expense']],
        'INDIRECT_EXPENSES' => ['label' => 'Indirect Expenses', 'statement' => 'INCOME_STATEMENT', 'normal' => 'DEBIT', 'account_categories' => ['indirect_expense', 'operating_expense']],
        'PROFIT_LOSS' => ['label' => 'Profit & Loss A/c', 'statement' => 'BALANCE_SHEET', 'normal' => 'CREDIT', 'account_categories' => []],
        'FOREX_GAIN_LOSS' => ['label' => 'Unadjusted Forex Gain/Loss', 'statement' => 'INCOME_STATEMENT', 'normal' => 'MIXED', 'account_categories' => []],
        'OPENING_BALANCE_DIFF' => ['label' => 'Difference in opening balances', 'statement' => 'BALANCE_SHEET', 'normal' => 'MIXED', 'account_categories' => []],
    ];

    public const DISPLAY_ORDER = [
        'CAPITAL_ACCOUNT', 'CURRENT_LIABILITIES', 'FIXED_ASSETS', 'INVESTMENTS',
        'CURRENT_ASSETS', 'BRANCH_DIVISIONS', 'SUSPENSE', 'SALES_ACCOUNTS',
        'PURCHASE_ACCOUNTS', 'DIRECT_EXPENSES', 'INDIRECT_INCOMES',
        'INDIRECT_EXPENSES', 'PROFIT_LOSS', 'FOREX_GAIN_LOSS', 'OPENING_BALANCE_DIFF',
    ];

    public function __construct(
        private readonly GeneralLedgerService $generalLedgerService,
    ) {}

    public function generate(Carbon $startDate, Carbon $endDate, ?string $filterCategory = null, bool $includeSubLedgers = true, ?int $businessId = null): array
    {
        $categoriesToProcess = $filterCategory ? [$filterCategory] : self::DISPLAY_ORDER;

        $groups = [];
        $assignedAccountIds = [];
        $selectedAccountIds = [];

        foreach ($categoriesToProcess as $category) {
            if (! isset(self::CATEGORIES[$category])) {
                continue;
            }

            $groupData = $this->calculateGroup(
                $category,
                $startDate,
                $endDate,
                $includeSubLedgers,
                $businessId,
                $filterCategory ? [] : $assignedAccountIds,
            );

            if (! $groupData['has_activity'] && $filterCategory) {
                $groupData['has_activity'] = true;
            }

            if ($groupData['has_activity']) {
                $groups[] = array_merge(
                    ['category' => $category],
                    self::CATEGORIES[$category],
                    $groupData
                );

                if (! $filterCategory) {
                    $assignedAccountIds = array_values(array_unique([
                        ...$assignedAccountIds,
                        ...$groupData['account_ids'],
                    ]));
                } else {
                    $selectedAccountIds = $groupData['account_ids'];
                }
            }
        }

        $rawTotals = $this->generalLedgerService->trialBalanceTotals(
            $startDate,
            $endDate,
            array_filter([
                'business_id' => $businessId,
                'account_ids' => $filterCategory ? $selectedAccountIds : null,
            ], static fn (mixed $value): bool => $value !== null),
        );

        return [
            'report_type' => $filterCategory ? 'GROUP_SUMMARY' : 'TRIAL_BALANCE',
            'filter_category' => $filterCategory,
            'groups' => $groups,
            // Grand Total is an accounting total, not the sum of presentation
            // subtotals. Account-normalized display amounts remain on each row.
            'grand_total' => $rawTotals,
            'raw_gl_totals' => $rawTotals,
            'display_totals' => [
                'debit' => round((float) collect($groups)->sum('debit'), 2),
                'credit' => round((float) collect($groups)->sum('credit'), 2),
            ],
            'period' => [
                'start' => $startDate->format('d-M-Y'),
                'end' => $endDate->format('d-M-Y'),
            ],
            'is_balanced' => $rawTotals['is_balanced'],
            'company_name' => config('app.company_name', config('app.name', 'BIWMS')),
            'active_categories' => $this->getActiveCategories($startDate, $endDate, $businessId),
        ];
    }

    /** @return array{debit:float,credit:float,net_balance:float,ledgers:array<int,array<string,mixed>>,ledger_count:int,has_activity:bool} */
    private function calculateGroup(string $category, Carbon $startDate, Carbon $endDate, bool $includeLedgers, ?int $businessId = null, array $excludedAccountIds = []): array
    {
        $accounts = $this->resolveAccountsForCategory($category, $excludedAccountIds);

        $groupDebit = 0.0;
        $groupCredit = 0.0;
        $ledgers = [];
        $accountIds = [];
        $hasActivity = false;

        foreach ($accounts as $account) {
            $accountIds[] = $account->id;
            $balance = $this->getAccountBalance($account->id, $startDate, $endDate, $businessId);
            $display = $this->formatForDisplay($balance['closing_balance'], (string) (self::CATEGORIES[$category]['normal'] ?? 'MIXED'));

            $groupDebit += $display['debit'];
            $groupCredit += $display['credit'];

            if ($balance['closing_balance'] != 0.0 || $balance['debit'] != 0.0 || $balance['credit'] != 0.0) {
                $hasActivity = true;
            }

            if ($includeLedgers) {
                $ledgers[] = [
                    'account_no' => $account->account_number,
                    'name' => $account->name,
                    'description' => $account->search_name,
                    'opening_balance' => $balance['opening_balance'],
                    'debit' => $balance['debit'],
                    'credit' => $balance['credit'],
                    'net_change' => $balance['net_change'],
                    'closing_balance' => $balance['closing_balance'],
                    'display_debit' => $display['debit'],
                    'display_credit' => $display['credit'],
                ];
            }
        }

        return [
            'debit' => $groupDebit,
            'credit' => $groupCredit,
            'net_balance' => $groupDebit - $groupCredit,
            'ledgers' => $ledgers,
            'ledger_count' => count($ledgers),
            'account_ids' => $accountIds,
            'has_activity' => $hasActivity,
        ];
    }

    private function resolveAccountsForCategory(string $category, array $excludedAccountIds = [])
    {
        $definition = self::CATEGORIES[$category] ?? null;

        $query = ChartOfAccount::query()
            ->where('blocked', false)
            ->where('structural_type', 'posting')
            ->when($excludedAccountIds !== [], fn ($query) => $query->whereNotIn('id', $excludedAccountIds))
            ->orderBy('account_number');

        $categories = $definition['account_categories'] ?? [];

        if ($categories !== []) {
            $query->whereIn('account_category', $categories);
        } else {
            $statement = $definition['statement'] ?? null;
            if ($statement === 'INCOME_STATEMENT') {
                $query->where('income_balance', IncomeBalanceType::INCOME_STATEMENT);
            } elseif ($statement === 'BALANCE_SHEET') {
                $query->where('income_balance', IncomeBalanceType::BALANCE_SHEET);
            }

            // Heuristic narrowing for special buckets without explicit categories.
            if ($category === 'FOREX_GAIN_LOSS') {
                $query->where(function ($q): void {
                    $q->where('name', 'ilike', '%forex%')
                        ->orWhere('name', 'ilike', '%exchange%')
                        ->orWhere('search_name', 'ilike', '%forex%')
                        ->orWhere('search_name', 'ilike', '%exchange%');
                });
            }

            if ($category === 'SUSPENSE') {
                $query->where(function ($q): void {
                    $q->where('name', 'ilike', '%suspense%')
                        ->orWhere('search_name', 'ilike', '%suspense%');
                });
            }

            if ($category === 'INVESTMENTS') {
                $query->where(function ($q): void {
                    $q->where('name', 'ilike', '%investment%')
                        ->orWhere('search_name', 'ilike', '%investment%')
                        ->orWhere('account_number', 'like', '14%');
                });
            }

            if ($category === 'PROFIT_LOSS') {
                $query->where(function ($q): void {
                    $q->where('name', 'ilike', '%profit%')
                        ->orWhere('name', 'ilike', '%loss%')
                        ->orWhere('search_name', 'ilike', '%profit%')
                        ->orWhere('search_name', 'ilike', '%loss%')
                        ->orWhere('account_number', 'like', '30%');
                });
            }

            if ($category === 'OPENING_BALANCE_DIFF') {
                $query->where(function ($q): void {
                    $q->where('name', 'ilike', '%opening balance%')
                        ->orWhere('name', 'ilike', '%difference%')
                        ->orWhere('search_name', 'ilike', '%opening balance%')
                        ->orWhere('search_name', 'ilike', '%difference%');
                });
            }
        }

        return $query->get();
    }

    /** @return array{opening_balance:float,debit:float,credit:float,net_change:float,closing_balance:float} */
    private function getAccountBalance(int $accountId, Carbon $startDate, Carbon $endDate, ?int $businessId = null): array
    {
        $openingBalance = (float) GlEntry::query()
            ->where('chart_of_account_id', $accountId)
            ->whereDate('posting_date', '<', $startDate)
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->sum(DB::raw('COALESCE(debit_amount_lcy, debit_amount) - COALESCE(credit_amount_lcy, credit_amount)'));

        $periodTotals = GlEntry::query()
            ->where('chart_of_account_id', $accountId)
            ->whereBetween('posting_date', [$startDate, $endDate])
            ->selectRaw('COALESCE(SUM(COALESCE(debit_amount_lcy, debit_amount)), 0) as debit_total, COALESCE(SUM(COALESCE(credit_amount_lcy, credit_amount)), 0) as credit_total')
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->first();

        $debit = (float) ($periodTotals->debit_total ?? 0.0);
        $credit = (float) ($periodTotals->credit_total ?? 0.0);
        $netChange = $debit - $credit;
        $closingBalance = $openingBalance + $netChange;

        return [
            'opening_balance' => $openingBalance,
            'debit' => $debit,
            'credit' => $credit,
            'net_change' => $netChange,
            'closing_balance' => $closingBalance,
        ];
    }

    /** @return array{debit:float,credit:float} */
    private function formatForDisplay(float $closingBalance, string $normalBalance): array
    {
        if ($normalBalance === 'DEBIT') {
            return [
                'debit' => $closingBalance > 0 ? $closingBalance : 0.0,
                'credit' => $closingBalance < 0 ? abs($closingBalance) : 0.0,
            ];
        }

        if ($normalBalance === 'CREDIT') {
            return [
                'credit' => $closingBalance > 0 ? $closingBalance : 0.0,
                'debit' => $closingBalance < 0 ? abs($closingBalance) : 0.0,
            ];
        }

        return [
            'debit' => $closingBalance < 0 ? abs($closingBalance) : 0.0,
            'credit' => $closingBalance > 0 ? $closingBalance : 0.0,
        ];
    }

    /** @return array<string,string> */
    public function categoryOptions(): array
    {
        $options = ['' => 'Trial Balance (All Groups)'];

        foreach (self::DISPLAY_ORDER as $category) {
            $options[$category] = self::CATEGORIES[$category]['label'];
        }

        return $options;
    }

    /** @return array<string, string> */
    public function getActiveCategories(Carbon $startDate, Carbon $endDate, ?int $businessId = null): array
    {
        $active = [];

        foreach (self::DISPLAY_ORDER as $category) {
            $groupData = $this->calculateGroup($category, $startDate, $endDate, false, $businessId);
            if ($groupData['has_activity']) {
                $active[$category] = self::CATEGORIES[$category]['label'];
            }
        }

        return $active;
    }
}
