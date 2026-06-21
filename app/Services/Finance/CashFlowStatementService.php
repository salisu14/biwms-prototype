<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\AccountCategory;
use App\Enums\AccountScheduleTotalingType;
use App\Models\AccountSchedule;
use App\Models\AccountScheduleLine;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\GlEntry;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class CashFlowStatementService
{
    public function generate(
        Carbon $startDate,
        Carbon $endDate,
        string $method = 'indirect',
        ?int $cashFlowScheduleId = null,
        ?int $profitAndLossScheduleId = null,
        ?int $balanceSheetScheduleId = null
    ): array {
        $resolvedMethod = in_array($method, ['direct', 'indirect'], true) ? $method : 'indirect';
        $cashAccountIds = $this->resolveCashAccountIds();
        $cashAccountNames = ChartOfAccount::query()
            ->whereIn('id', $cashAccountIds->all())
            ->orderBy('account_number')
            ->get()
            ->map(fn (ChartOfAccount $account): string => "{$account->account_number} - {$account->name}")
            ->values()
            ->all();

        $mapping = $this->resolveMapping(
            $cashAccountIds,
            $cashFlowScheduleId,
            $profitAndLossScheduleId,
            $balanceSheetScheduleId
        );

        $openingCash = $this->cashBalanceAt($cashAccountIds, $startDate->copy()->subDay());
        $endingCash = $this->cashBalanceAt($cashAccountIds, $endDate);
        $cashTransactions = $this->buildCashTransactions($cashAccountIds, $startDate, $endDate, $mapping);

        $directSections = $this->summarizeDirectSections($cashTransactions);
        $operatingTotal = $directSections['operating']['total'];
        $investingTotal = $directSections['investing']['total'];
        $financingTotal = $directSections['financing']['total'];

        $netIncome = $this->calculateNetIncome($mapping['income_statement_account_ids'], $startDate, $endDate);
        $depreciationAdjustment = $this->calculateDepreciationAdjustment($mapping['operating_expense_account_ids'], $startDate, $endDate);
        $receivablesAdjustment = $this->workingCapitalAdjustmentByAccountIds($mapping['receivable_account_ids'], $startDate, $endDate, false);
        $inventoryAdjustment = $this->workingCapitalAdjustmentByAccountIds($mapping['inventory_account_ids'], $startDate, $endDate, false);
        $payablesAdjustment = $this->workingCapitalAdjustmentByAccountIds($mapping['payable_account_ids'], $startDate, $endDate, true);
        $otherOperatingAdjustments = $operatingTotal - (
            $netIncome +
            $depreciationAdjustment +
            $receivablesAdjustment +
            $inventoryAdjustment +
            $payablesAdjustment
        );

        $indirectOperatingLines = collect([
            $this->makeLine('Net income for the period', $netIncome),
            $this->makeLine('Depreciation and amortization (non-cash)', $depreciationAdjustment),
            $this->makeLine('Change in receivables', $receivablesAdjustment),
            $this->makeLine('Change in inventory', $inventoryAdjustment),
            $this->makeLine('Change in payables', $payablesAdjustment),
        ])->when(abs($otherOperatingAdjustments) > 0.005, fn (Collection $lines): Collection => $lines->push(
            $this->makeLine('Other operating adjustments', $otherOperatingAdjustments)
        ))->values()->all();

        $sections = [
            'operating' => [
                'label' => 'Operating Activities',
                'lines' => $resolvedMethod === 'direct' ? $directSections['operating']['lines'] : $indirectOperatingLines,
                'total' => $operatingTotal,
            ],
            'investing' => [
                'label' => 'Investing Activities',
                'lines' => $directSections['investing']['lines'],
                'total' => $investingTotal,
            ],
            'financing' => [
                'label' => 'Financing Activities',
                'lines' => $directSections['financing']['lines'],
                'total' => $financingTotal,
            ],
        ];

        return [
            'method' => $resolvedMethod,
            'period' => [
                'start' => $startDate->toDateString(),
                'end' => $endDate->toDateString(),
            ],
            'printed_at' => now()->format('Y-m-d H:i'),
            'cash_accounts' => $cashAccountNames,
            'mapping' => [
                'mode' => $mapping['mode'],
                'cash_flow_schedule_id' => $mapping['cash_flow_schedule_id'],
                'cash_flow_schedule' => $mapping['cash_flow_schedule_name'],
                'profit_and_loss_schedule' => $mapping['profit_and_loss_schedule_name'],
                'balance_sheet_schedule' => $mapping['balance_sheet_schedule_name'],
            ],
            'opening_cash' => $openingCash,
            'net_income' => $netIncome,
            'sections' => $sections,
            'net_change_in_cash' => $operatingTotal + $investingTotal + $financingTotal,
            'ending_cash' => $endingCash,
        ];
    }

    public function generateComparison(
        Carbon $startDate,
        Carbon $endDate,
        ?Carbon $compareStartDate,
        ?Carbon $compareEndDate,
        string $method = 'indirect',
        ?int $cashFlowScheduleId = null,
        ?int $profitAndLossScheduleId = null,
        ?int $balanceSheetScheduleId = null
    ): array {
        $current = $this->generate(
            $startDate,
            $endDate,
            $method,
            $cashFlowScheduleId,
            $profitAndLossScheduleId,
            $balanceSheetScheduleId,
        );

        if ($compareStartDate === null || $compareEndDate === null) {
            return $current;
        }

        $compare = $this->generate(
            $compareStartDate,
            $compareEndDate,
            $method,
            $cashFlowScheduleId,
            $profitAndLossScheduleId,
            $balanceSheetScheduleId,
        );

        $current['compare_period'] = [
            'start' => $compare['period']['start'],
            'end' => $compare['period']['end'],
        ];
        $current['comparison_summary'] = [
            'opening_cash' => (float) $compare['opening_cash'],
            'net_change_in_cash' => (float) $compare['net_change_in_cash'],
            'ending_cash' => (float) $compare['ending_cash'],
        ];

        foreach (['opening_cash', 'net_change_in_cash', 'ending_cash'] as $field) {
            $current['comparison_summary'][$field.'_variance_amount'] = (float) $current[$field] - (float) $compare[$field];
            $current['comparison_summary'][$field.'_variance_percent'] = $this->calculateVariancePercent(
                (float) $current[$field],
                (float) $compare[$field],
            );
        }

        foreach ($current['sections'] as $sectionKey => &$section) {
            $compareSection = $compare['sections'][$sectionKey] ?? ['lines' => [], 'total' => 0.0];
            $section['compare_total'] = (float) $compareSection['total'];
            $section['variance_amount'] = (float) $section['total'] - (float) $compareSection['total'];
            $section['variance_percent'] = $this->calculateVariancePercent(
                (float) $section['total'],
                (float) $compareSection['total'],
            );

            $currentLines = collect($section['lines'])->keyBy('label');
            $compareLines = collect($compareSection['lines'])->keyBy('label');

            $section['lines'] = $currentLines
                ->keys()
                ->merge($compareLines->keys())
                ->unique()
                ->sort()
                ->map(function (string $label) use ($currentLines, $compareLines): array {
                    $currentAmount = (float) ($currentLines->get($label)['amount'] ?? 0.0);
                    $compareAmount = (float) ($compareLines->get($label)['amount'] ?? 0.0);

                    return [
                        'label' => $label,
                        'amount' => $currentAmount,
                        'compare_amount' => $compareAmount,
                        'variance_amount' => $currentAmount - $compareAmount,
                        'variance_percent' => $this->calculateVariancePercent($currentAmount, $compareAmount),
                    ];
                })
                ->values()
                ->all();
        }
        unset($section);

        return $current;
    }

    /**
     * @return Collection<int, int>
     */
    private function resolveCashAccountIds(): Collection
    {
        $bankBackedCashAccounts = BankAccount::query()
            ->where('active', true)
            ->whereNotNull('gl_account_id')
            ->pluck('gl_account_id')
            ->filter()
            ->map(fn (mixed $id): int => (int) $id);

        if ($bankBackedCashAccounts->isNotEmpty()) {
            return $bankBackedCashAccounts->unique()->values();
        }

        return ChartOfAccount::query()
            ->where(function ($query): void {
                $query->where('account_category', AccountCategory::LIQUID_ASSET->value)
                    ->orWhere(function ($subQuery): void {
                        $subQuery->where('account_category', AccountCategory::ASSET->value)
                            ->where(function ($nameQuery): void {
                                $nameQuery->where('name', 'ilike', '%cash%')
                                    ->orWhere('name', 'ilike', '%bank%');
                            });
                    });
            })
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * @param  Collection<int, int>  $cashAccountIds
     * @return array{
     *     mode: string,
     *     profit_and_loss_schedule_name: string|null,
     *     balance_sheet_schedule_name: string|null,
     *     income_statement_account_ids: Collection<int, int>,
     *     operating_expense_account_ids: Collection<int, int>,
     *     receivable_account_ids: Collection<int, int>,
     *     inventory_account_ids: Collection<int, int>,
     *     payable_account_ids: Collection<int, int>,
     *     investing_account_ids: Collection<int, int>,
     *     financing_account_ids: Collection<int, int>,
     *     equity_account_ids: Collection<int, int>
     * }
     */
    private function resolveMapping(
        Collection $cashAccountIds,
        ?int $cashFlowScheduleId,
        ?int $profitAndLossScheduleId,
        ?int $balanceSheetScheduleId
    ): array {
        $cashFlowSchedule = $this->resolveSchedule($cashFlowScheduleId, 'Default Cash Flow Statement');
        $profitAndLossSchedule = $this->resolveSchedule($profitAndLossScheduleId, 'Default Profit & Loss');
        $balanceSheetSchedule = $this->resolveSchedule($balanceSheetScheduleId, 'Default Balance Sheet');

        $incomeStatementAccountIds = $this->resolveIncomeStatementAccountIds($profitAndLossSchedule);
        $operatingExpenseAccountIds = $this->resolveOperatingExpenseAccountIds($profitAndLossSchedule);
        $balanceSheetGroups = $this->resolveBalanceSheetGroups($balanceSheetSchedule, $cashAccountIds);

        $receivableAccountIds = $this->accountIdsForCategories([AccountCategory::RECEIVABLE]);
        $inventoryAccountIds = $this->accountIdsForCategories([AccountCategory::INVENTORY]);
        $payableAccountIds = $this->accountIdsForCategories([AccountCategory::PAYABLE]);
        $cashFlowGroups = $this->resolveCashFlowGroups($cashFlowSchedule, $cashAccountIds);

        if ($cashFlowGroups['receivables']->isNotEmpty()) {
            $receivableAccountIds = $cashFlowGroups['receivables'];
        }
        if ($cashFlowGroups['inventory']->isNotEmpty()) {
            $inventoryAccountIds = $cashFlowGroups['inventory'];
        }
        if ($cashFlowGroups['payables']->isNotEmpty()) {
            $payableAccountIds = $cashFlowGroups['payables'];
        }
        $equityAccountIds = $balanceSheetGroups['equity']->isNotEmpty()
            ? $balanceSheetGroups['equity']
            : $this->accountIdsForCategories([AccountCategory::EQUITY]);

        $financingLiabilityIds = $balanceSheetGroups['non_current_liabilities']->isNotEmpty()
            ? $balanceSheetGroups['non_current_liabilities']
            : $this->accountIdsForCategories([AccountCategory::LIABILITY])
                ->diff($payableAccountIds)
                ->diff($cashAccountIds)
                ->values();

        $investingAccountIds = $cashFlowGroups['investing_assets']->isNotEmpty()
            ? $cashFlowGroups['investing_assets']
            : ($balanceSheetGroups['non_current_assets']->isNotEmpty()
                ? $balanceSheetGroups['non_current_assets']->diff($cashAccountIds)->values()
                : $this->accountIdsForCategories([AccountCategory::FIXED_ASSET]));

        $equityAccountIds = $cashFlowGroups['equity']->isNotEmpty()
            ? $cashFlowGroups['equity']
            : $equityAccountIds;
        $financingLiabilityIds = $cashFlowGroups['financing_debt']->isNotEmpty()
            ? $cashFlowGroups['financing_debt']
            : $financingLiabilityIds;

        return [
            'mode' => $cashFlowSchedule !== null
                ? 'cash_flow_schedule'
                : (($profitAndLossSchedule !== null || $balanceSheetSchedule !== null)
                    ? 'schedule'
                    : 'chart_of_accounts'),
            'cash_flow_schedule_id' => $cashFlowSchedule?->id,
            'cash_flow_schedule_name' => $cashFlowSchedule?->name,
            'profit_and_loss_schedule_name' => $profitAndLossSchedule?->name,
            'balance_sheet_schedule_name' => $balanceSheetSchedule?->name,
            'income_statement_account_ids' => $incomeStatementAccountIds,
            'operating_expense_account_ids' => $operatingExpenseAccountIds,
            'receivable_account_ids' => $receivableAccountIds,
            'inventory_account_ids' => $inventoryAccountIds,
            'payable_account_ids' => $payableAccountIds,
            'investing_account_ids' => $investingAccountIds,
            'financing_account_ids' => $financingLiabilityIds
                ->merge($equityAccountIds)
                ->diff($cashAccountIds)
                ->unique()
                ->values(),
            'equity_account_ids' => $equityAccountIds,
        ];
    }

    private function resolveSchedule(?int $scheduleId, string $defaultName): ?AccountSchedule
    {
        if ($scheduleId !== null) {
            return AccountSchedule::with('lines')->find($scheduleId);
        }

        return AccountSchedule::with('lines')
            ->where('name', $defaultName)
            ->first();
    }

    /**
     * @param  Collection<int, int>  $cashAccountIds
     * @return array{
     *     current_assets: Collection<int, int>,
     *     non_current_assets: Collection<int, int>,
     *     current_liabilities: Collection<int, int>,
     *     non_current_liabilities: Collection<int, int>,
     *     equity: Collection<int, int>
     * }
     */
    private function resolveBalanceSheetGroups(?AccountSchedule $schedule, Collection $cashAccountIds): array
    {
        if ($schedule === null) {
            return [
                'current_assets' => $this->accountIdsForCategories([
                    AccountCategory::RECEIVABLE,
                    AccountCategory::INVENTORY,
                    AccountCategory::ASSET,
                ])->diff($this->accountIdsForCategories([AccountCategory::FIXED_ASSET]))->diff($cashAccountIds)->values(),
                'non_current_assets' => $this->accountIdsForCategories([AccountCategory::FIXED_ASSET])->diff($cashAccountIds)->values(),
                'current_liabilities' => $this->accountIdsForCategories([AccountCategory::PAYABLE]),
                'non_current_liabilities' => $this->accountIdsForCategories([AccountCategory::LIABILITY])
                    ->diff($this->accountIdsForCategories([AccountCategory::PAYABLE]))
                    ->diff($cashAccountIds)
                    ->values(),
                'equity' => $this->accountIdsForCategories([AccountCategory::EQUITY]),
            ];
        }

        return [
            'current_assets' => $this->accountIdsFromScheduleLines($schedule->lines, fn (AccountScheduleLine $line): bool => $this->matchesScheduleDescription($line->description, ['current assets']) && ! $this->matchesScheduleDescription($line->description, ['non-current assets', 'non current assets']))->diff($cashAccountIds)->values(),
            'non_current_assets' => $this->accountIdsFromScheduleLines($schedule->lines, fn (AccountScheduleLine $line): bool => $this->matchesScheduleDescription($line->description, ['non-current assets', 'non current assets']))->diff($cashAccountIds)->values(),
            'current_liabilities' => $this->accountIdsFromScheduleLines($schedule->lines, fn (AccountScheduleLine $line): bool => $this->matchesScheduleDescription($line->description, ['current liabilities']) && ! $this->matchesScheduleDescription($line->description, ['non-current liabilities', 'non current liabilities']))->diff($cashAccountIds)->values(),
            'non_current_liabilities' => $this->accountIdsFromScheduleLines($schedule->lines, fn (AccountScheduleLine $line): bool => $this->matchesScheduleDescription($line->description, ['non-current liabilities', 'non current liabilities']))->diff($cashAccountIds)->values(),
            'equity' => $this->accountIdsFromScheduleLines($schedule->lines, fn (AccountScheduleLine $line): bool => $this->matchesScheduleDescription($line->description, ['equity'])),
        ];
    }

    /**
     * @param  Collection<int, int>  $cashAccountIds
     * @return array{
     *     receivables: Collection<int, int>,
     *     inventory: Collection<int, int>,
     *     payables: Collection<int, int>,
     *     investing_assets: Collection<int, int>,
     *     financing_debt: Collection<int, int>,
     *     equity: Collection<int, int>
     * }
     */
    private function resolveCashFlowGroups(?AccountSchedule $schedule, Collection $cashAccountIds): array
    {
        if ($schedule === null) {
            return [
                'receivables' => collect(),
                'inventory' => collect(),
                'payables' => collect(),
                'investing_assets' => collect(),
                'financing_debt' => collect(),
                'equity' => collect(),
            ];
        }

        return [
            'receivables' => $this->accountIdsFromScheduleLines($schedule->lines, fn (AccountScheduleLine $line): bool => $this->matchesScheduleDescription($line->description, ['receivables', 'customers']))->diff($cashAccountIds)->values(),
            'inventory' => $this->accountIdsFromScheduleLines($schedule->lines, fn (AccountScheduleLine $line): bool => $this->matchesScheduleDescription($line->description, ['inventory']))->diff($cashAccountIds)->values(),
            'payables' => $this->accountIdsFromScheduleLines($schedule->lines, fn (AccountScheduleLine $line): bool => $this->matchesScheduleDescription($line->description, ['payables', 'suppliers']))->diff($cashAccountIds)->values(),
            'investing_assets' => $this->accountIdsFromScheduleLines($schedule->lines, fn (AccountScheduleLine $line): bool => $this->matchesScheduleDescription($line->description, ['capital expenditures', 'investing assets', 'fixed assets']))->diff($cashAccountIds)->values(),
            'financing_debt' => $this->accountIdsFromScheduleLines($schedule->lines, fn (AccountScheduleLine $line): bool => $this->matchesScheduleDescription($line->description, ['debt', 'borrowings', 'long-term liabilities', 'non-current liabilities']))->diff($cashAccountIds)->values(),
            'equity' => $this->accountIdsFromScheduleLines($schedule->lines, fn (AccountScheduleLine $line): bool => $this->matchesScheduleDescription($line->description, ['equity', 'dividends'])),
        ];
    }

    private function matchesScheduleDescription(string $description, array $needles): bool
    {
        $normalized = str($description)->lower()->value();

        foreach ($needles as $needle) {
            if (str_contains($normalized, str($needle)->lower()->value())) {
                return true;
            }
        }

        return false;
    }

    private function resolveIncomeStatementAccountIds(?AccountSchedule $schedule): Collection
    {
        if ($schedule !== null) {
            $accountIds = $this->accountIdsFromScheduleLines(
                $schedule->lines,
                fn (AccountScheduleLine $line): bool => in_array(
                    $line->totaling_type,
                    [AccountScheduleTotalingType::POSTING_ACCOUNTS, AccountScheduleTotalingType::TOTAL_ACCOUNTS],
                    true
                )
            );

            if ($accountIds->isNotEmpty()) {
                return $accountIds;
            }
        }

        return $this->accountIdsForCategories([
            AccountCategory::REVENUE,
            AccountCategory::COGS,
            AccountCategory::DIRECT_EXPENSE,
            AccountCategory::INDIRECT_EXPENSE,
            AccountCategory::OPERATING_EXPENSE,
            AccountCategory::OTHER_INCOME_EXPENSE,
        ]);
    }

    private function resolveOperatingExpenseAccountIds(?AccountSchedule $schedule): Collection
    {
        $fallback = $this->accountIdsForCategories([
            AccountCategory::DIRECT_EXPENSE,
            AccountCategory::INDIRECT_EXPENSE,
            AccountCategory::OPERATING_EXPENSE,
            AccountCategory::OTHER_INCOME_EXPENSE,
        ]);

        if ($schedule === null) {
            return $fallback;
        }

        $operatingExpenseAccountIds = $this->accountIdsFromScheduleLines(
            $schedule->lines,
            fn (AccountScheduleLine $line): bool => $this->matchesScheduleDescription($line->description, ['operating expenses', 'expenses'])
        );

        return $operatingExpenseAccountIds->isNotEmpty() ? $operatingExpenseAccountIds : $fallback;
    }

    /**
     * @param  Collection<int, AccountScheduleLine>  $lines
     * @param  callable(AccountScheduleLine): bool  $matcher
     * @return Collection<int, int>
     */
    private function accountIdsFromScheduleLines(Collection $lines, callable $matcher): Collection
    {
        return $lines
            ->filter(function (AccountScheduleLine $line) use ($matcher): bool {
                if (! $matcher($line)) {
                    return false;
                }

                return in_array(
                    $line->totaling_type,
                    [AccountScheduleTotalingType::POSTING_ACCOUNTS, AccountScheduleTotalingType::TOTAL_ACCOUNTS],
                    true
                ) && filled($line->totaling);
            })
            ->flatMap(fn (AccountScheduleLine $line): array => $this->parseAccountNumbers((string) $line->totaling))
            ->unique()
            ->whenEmpty(fn (): Collection => collect())
            ->pipe(function (Collection $accountNumbers): Collection {
                if ($accountNumbers->isEmpty()) {
                    return collect();
                }

                return ChartOfAccount::query()
                    ->whereIn('account_number', $accountNumbers->all())
                    ->pluck('id')
                    ->map(fn (mixed $id): int => (int) $id)
                    ->unique()
                    ->values();
            });
    }

    /**
     * @param  array<int, AccountCategory>  $categories
     * @return Collection<int, int>
     */
    private function accountIdsForCategories(array $categories): Collection
    {
        return ChartOfAccount::query()
            ->whereIn('account_category', array_map(fn (AccountCategory $category): string => $category->value, $categories))
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * @return array<int, string>
     */
    private function parseAccountNumbers(string $totaling): array
    {
        if (str_contains($totaling, '|')) {
            return collect(explode('|', $totaling))
                ->flatMap(fn (string $part): array => $this->parseAccountNumbers(trim($part)))
                ->filter()
                ->unique()
                ->values()
                ->all();
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

    /**
     * @param  Collection<int, int>  $cashAccountIds
     */
    private function cashBalanceAt(Collection $cashAccountIds, Carbon $asOfDate): float
    {
        if ($cashAccountIds->isEmpty()) {
            return 0.0;
        }

        return (float) GlEntry::query()
            ->whereIn('chart_of_account_id', $cashAccountIds->all())
            ->whereDate('posting_date', '<=', $asOfDate)
            ->sum(DB::raw('debit_amount - credit_amount'));
    }

    /**
     * @param  Collection<int, int>  $cashAccountIds
     * @param  array<string, mixed>  $mapping
     * @return Collection<int, array{section:string,label:string,amount:float}>
     */
    private function buildCashTransactions(Collection $cashAccountIds, Carbon $startDate, Carbon $endDate, array $mapping): Collection
    {
        if ($cashAccountIds->isEmpty()) {
            return collect();
        }

        $transactionNumbers = GlEntry::query()
            ->whereIn('chart_of_account_id', $cashAccountIds->all())
            ->whereBetween('posting_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->pluck('transaction_number')
            ->filter()
            ->unique()
            ->values();

        if ($transactionNumbers->isEmpty()) {
            return collect();
        }

        return GlEntry::query()
            ->with('chartOfAccount')
            ->whereIn('transaction_number', $transactionNumbers->all())
            ->whereBetween('posting_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->orderBy('posting_date')
            ->orderBy('transaction_number')
            ->get()
            ->groupBy('transaction_number')
            ->map(function (Collection $entries) use ($cashAccountIds, $mapping): ?array {
                $cashDelta = (float) $entries
                    ->filter(fn (GlEntry $entry): bool => $cashAccountIds->contains((int) $entry->chart_of_account_id))
                    ->sum(fn (GlEntry $entry): float => (float) $entry->debit_amount - (float) $entry->credit_amount);

                if (abs($cashDelta) <= 0.005) {
                    return null;
                }

                $counterparts = $entries
                    ->reject(fn (GlEntry $entry): bool => $cashAccountIds->contains((int) $entry->chart_of_account_id))
                    ->filter(fn (GlEntry $entry): bool => $entry->chartOfAccount !== null)
                    ->values();

                if ($counterparts->isEmpty()) {
                    return [
                        'section' => 'operating',
                        'label' => $cashDelta >= 0 ? 'Other operating cash receipts' : 'Other operating cash payments',
                        'amount' => $cashDelta,
                    ];
                }

                $section = $this->classifySection($counterparts, $mapping);
                $label = $this->classifyLabel($section, $counterparts, $cashDelta, $mapping);

                return [
                    'section' => $section,
                    'label' => $label,
                    'amount' => $cashDelta,
                ];
            })
            ->filter()
            ->values();
    }

    /**
     * @param  Collection<int, GlEntry>  $counterparts
     * @param  array<string, mixed>  $mapping
     */
    private function classifySection(Collection $counterparts, array $mapping): string
    {
        $counterpartIds = $counterparts
            ->map(fn (GlEntry $entry): int => (int) $entry->chart_of_account_id)
            ->filter()
            ->unique()
            ->values();

        if ($counterpartIds->intersect($mapping['investing_account_ids'])->isNotEmpty()) {
            return 'investing';
        }

        if ($counterpartIds->intersect($mapping['financing_account_ids'])->isNotEmpty()) {
            return 'financing';
        }

        $categories = $counterparts
            ->map(fn (GlEntry $entry): ?string => $entry->chartOfAccount?->account_category?->value)
            ->filter()
            ->unique()
            ->values();

        if ($categories->contains(AccountCategory::FIXED_ASSET->value)) {
            return 'investing';
        }

        if ($categories->contains(AccountCategory::EQUITY->value)) {
            return 'financing';
        }

        if ($categories->contains(AccountCategory::LIABILITY->value) && ! $categories->contains(AccountCategory::PAYABLE->value)) {
            return 'financing';
        }

        return 'operating';
    }

    /**
     * @param  Collection<int, GlEntry>  $counterparts
     * @param  array<string, mixed>  $mapping
     */
    private function classifyLabel(string $section, Collection $counterparts, float $cashDelta, array $mapping): string
    {
        $counterpartIds = $counterparts
            ->map(fn (GlEntry $entry): int => (int) $entry->chart_of_account_id)
            ->filter()
            ->unique()
            ->values();

        $categories = $counterparts
            ->map(fn (GlEntry $entry): ?string => $entry->chartOfAccount?->account_category?->value)
            ->filter()
            ->unique()
            ->values();

        return match ($section) {
            'investing' => $cashDelta >= 0 ? 'Proceeds from disposal of long-term assets' : 'Capital expenditures',
            'financing' => $counterpartIds->intersect($mapping['equity_account_ids'])->isNotEmpty()
                ? ($cashDelta >= 0 ? 'Capital contributions' : 'Dividends and owner drawings')
                : ($cashDelta >= 0 ? 'Proceeds from borrowings' : 'Debt repayments'),
            default => $this->classifyOperatingLabel($counterpartIds, $categories, $cashDelta, $mapping),
        };
    }

    /**
     * @param  Collection<int, int>  $counterpartIds
     * @param  Collection<int, string>  $categories
     * @param  array<string, mixed>  $mapping
     */
    private function classifyOperatingLabel(Collection $counterpartIds, Collection $categories, float $cashDelta, array $mapping): string
    {
        if (
            $cashDelta >= 0 &&
            (
                $counterpartIds->intersect($mapping['receivable_account_ids'])->isNotEmpty() ||
                $categories->contains(AccountCategory::REVENUE->value)
            )
        ) {
            return 'Cash receipts from customers';
        }

        if (
            $cashDelta < 0 &&
            (
                $counterpartIds->intersect($mapping['payable_account_ids'])->isNotEmpty() ||
                $counterpartIds->intersect($mapping['inventory_account_ids'])->isNotEmpty() ||
                $categories->contains(AccountCategory::COGS->value)
            )
        ) {
            return 'Cash paid to suppliers';
        }

        if (
            $cashDelta < 0 &&
            (
                $counterpartIds->intersect($mapping['operating_expense_account_ids'])->isNotEmpty() ||
                $categories->contains(AccountCategory::DIRECT_EXPENSE->value) ||
                $categories->contains(AccountCategory::INDIRECT_EXPENSE->value) ||
                $categories->contains(AccountCategory::OPERATING_EXPENSE->value)
            )
        ) {
            return 'Cash paid for operating expenses';
        }

        return $cashDelta >= 0 ? 'Other operating cash receipts' : 'Other operating cash payments';
    }

    /**
     * @param  Collection<int, array{section:string,label:string,amount:float}>  $cashTransactions
     * @return array{
     *     operating: array{lines: array<int, array{label:string, amount:float}>, total: float},
     *     investing: array{lines: array<int, array{label:string, amount:float}>, total: float},
     *     financing: array{lines: array<int, array{label:string, amount:float}>, total: float}
     * }
     */
    private function summarizeDirectSections(Collection $cashTransactions): array
    {
        $sections = [];

        foreach (['operating', 'investing', 'financing'] as $section) {
            $lines = $cashTransactions
                ->where('section', $section)
                ->groupBy('label')
                ->map(fn (Collection $items, string $label): array => [
                    'label' => $label,
                    'amount' => (float) $items->sum('amount'),
                ])
                ->filter(fn (array $line): bool => abs($line['amount']) > 0.005)
                ->sortBy('label')
                ->values()
                ->all();

            $sections[$section] = [
                'lines' => $lines,
                'total' => (float) collect($lines)->sum('amount'),
            ];
        }

        return $sections;
    }

    /**
     * @param  Collection<int, int>  $incomeStatementAccountIds
     */
    private function calculateNetIncome(Collection $incomeStatementAccountIds, Carbon $startDate, Carbon $endDate): float
    {
        if ($incomeStatementAccountIds->isEmpty()) {
            return 0.0;
        }

        $entries = GlEntry::query()
            ->with('chartOfAccount')
            ->whereBetween('posting_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereIn('chart_of_account_id', $incomeStatementAccountIds->all())
            ->get();

        return (float) $entries->sum(function (GlEntry $entry): float {
            $category = $entry->chartOfAccount?->account_category?->value;
            $debitMinusCredit = (float) $entry->debit_amount - (float) $entry->credit_amount;

            return match ($category) {
                AccountCategory::REVENUE->value => $debitMinusCredit * -1,
                default => $debitMinusCredit * -1,
            };
        });
    }

    /**
     * @param  Collection<int, int>  $operatingExpenseAccountIds
     */
    private function calculateDepreciationAdjustment(Collection $operatingExpenseAccountIds, Carbon $startDate, Carbon $endDate): float
    {
        if ($operatingExpenseAccountIds->isEmpty()) {
            return 0.0;
        }

        $entries = GlEntry::query()
            ->with('chartOfAccount')
            ->whereBetween('posting_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->whereIn('chart_of_account_id', $operatingExpenseAccountIds->all())
            ->where(function ($query): void {
                $query->whereHas('chartOfAccount', function ($accountQuery): void {
                    $accountQuery->where(function ($nameQuery): void {
                        $nameQuery->where('name', 'ilike', '%depreci%')
                            ->orWhere('name', 'ilike', '%amort%');
                    });
                })->orWhere(function ($documentQuery): void {
                    $documentQuery
                        ->where('document_type', 'ilike', '%depreciation%')
                        ->orWhere('document_type', 'ilike', '%amortization%')
                        ->orWhere('description', 'ilike', 'depreciation %')
                        ->orWhere('description', 'ilike', '% amortization%')
                        ->orWhere('sourceable_type', 'App\\Models\\FixedAsset');
                });
            })
            ->get();

        return (float) $entries->sum(fn (GlEntry $entry): float => (float) $entry->debit_amount - (float) $entry->credit_amount);
    }

    /**
     * @param  Collection<int, int>  $accountIds
     */
    private function workingCapitalAdjustmentByAccountIds(Collection $accountIds, Carbon $startDate, Carbon $endDate, bool $liabilityStyle): float
    {
        if ($accountIds->isEmpty()) {
            return 0.0;
        }

        $opening = $this->balanceForAccountIds($accountIds, $startDate->copy()->subDay());
        $closing = $this->balanceForAccountIds($accountIds, $endDate);

        return $liabilityStyle ? $closing - $opening : $opening - $closing;
    }

    /**
     * @param  Collection<int, int>  $accountIds
     */
    private function balanceForAccountIds(Collection $accountIds, Carbon $asOfDate): float
    {
        if ($accountIds->isEmpty()) {
            return 0.0;
        }

        return (float) GlEntry::query()
            ->whereIn('chart_of_account_id', $accountIds->all())
            ->whereDate('posting_date', '<=', $asOfDate)
            ->sum(DB::raw('debit_amount - credit_amount'));
    }

    /**
     * @return array{label:string, amount:float}
     */
    private function makeLine(string $label, float $amount): array
    {
        return [
            'label' => $label,
            'amount' => $amount,
        ];
    }

    private function calculateVariancePercent(float $current, float $compare): ?float
    {
        if (abs($compare) <= 0.00001) {
            return null;
        }

        return (($current - $compare) / abs($compare)) * 100;
    }
}
