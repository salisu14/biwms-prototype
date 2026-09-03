<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Enums\AccountScheduleAmountType;
use App\Enums\AccountScheduleRowType;
use App\Enums\AccountScheduleTotalingType;
use App\Enums\AccountType;
use App\Enums\IncomeBalanceType;
use App\Models\AccountSchedule;
use App\Models\AccountScheduleLine;
use App\Models\ChartOfAccount;
use App\Models\GlEntry;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class BalanceSheetService
{
    public function generate(Carbon $asOfDate, ?int $businessId = null): array
    {
        $accounts = ChartOfAccount::query()
            ->where('income_balance', IncomeBalanceType::BALANCE_SHEET)
            ->where('blocked', false)
            ->orderBy('account_number')
            ->get();

        $lines = $accounts->map(function (ChartOfAccount $account) use ($asOfDate, $businessId): array {
            $rawBalance = $this->calculateBalanceAtDate($account, $asOfDate, $businessId);
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

        $currentEarnings = $this->calculateCurrentEarnings($asOfDate, $businessId);
        if (abs($currentEarnings) > 0.005) {
            $lines->push([
                'account_no' => 'CURRENT_EARNINGS',
                'description' => 'Current Period Earnings',
                'account_type' => AccountType::EQUITY->value,
                'account_category' => 'equity',
                'indentation' => 0,
                'bold' => true,
                'is_total_account' => false,
                'amount' => $currentEarnings,
            ]);
        }

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

    public function generateFromSchedule(int $scheduleId, Carbon $asOfDate, ?int $businessId = null): array
    {
        $schedule = AccountSchedule::with('lines')->findOrFail($scheduleId);
        $results = collect();
        $linesByRow = $schedule->lines
            ->filter(fn (AccountScheduleLine $line): bool => filled($line->row_no))
            ->keyBy(fn (AccountScheduleLine $line): string => (string) $line->row_no);
        $resolving = [];

        $resolveLine = function (AccountScheduleLine $line) use (&$resolveLine, &$resolving, $linesByRow, $asOfDate, $businessId): float {
            $rowNo = filled($line->row_no) ? (string) $line->row_no : null;
            if ($rowNo !== null && isset($resolving[$rowNo])) {
                throw new \InvalidArgumentException("Circular account schedule reference [{$rowNo}].");
            }

            if ($rowNo !== null) {
                $resolving[$rowNo] = true;
            }

            try {
                $amount = match ($line->totaling_type) {
                    AccountScheduleTotalingType::POSTING_ACCOUNTS,
                    AccountScheduleTotalingType::TOTAL_ACCOUNTS => $this->sumAccountsByRowType(
                        (string) $line->totaling,
                        $asOfDate,
                        $line->row_type ?? AccountScheduleRowType::BALANCE_AT_DATE,
                        $businessId,
                        $line->amount_type ?? AccountScheduleAmountType::NET_AMOUNT,
                    ),
                    AccountScheduleTotalingType::FORMULA => app(AccountScheduleFormulaEvaluator::class)->evaluate(
                        (string) $line->totaling,
                        function (string $reference) use (&$resolveLine, $linesByRow): float {
                            $referencedLine = $linesByRow->get($reference);
                            if (! $referencedLine instanceof AccountScheduleLine) {
                                throw new \InvalidArgumentException("Unknown account schedule row reference [{$reference}].");
                            }

                            return $resolveLine($referencedLine);
                        },
                        $linesByRow->keys()->map(fn ($key): string => (string) $key)->all(),
                    ),
                    default => 0.0,
                };

                return $line->show_opposite_sign ? $amount * -1 : $amount;
            } finally {
                if ($rowNo !== null) {
                    unset($resolving[$rowNo]);
                }
            }
        };

        foreach ($schedule->lines as $line) {
            try {
                $amount = $resolveLine($line);
            } catch (\InvalidArgumentException $exception) {
                logger()->warning('Invalid account schedule formula.', [
                    'schedule_id' => $schedule->id,
                    'line_id' => $line->id,
                    'formula' => $line->totaling,
                    'error' => $exception->getMessage(),
                ]);
                $amount = 0.0;
            }

            $results->push([
                'account_no' => $line->row_no ?: '',
                'description' => $line->description,
                'account_type' => null,
                'indentation' => $line->indentation ?? 0,
                'bold' => (bool) $line->bold,
                'is_total_account' => false,
                'amount' => $amount,
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

    private function calculateBalanceAtDate(ChartOfAccount $account, Carbon $asOfDate, ?int $businessId = null): float
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
                ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
                ->sum(DB::raw('COALESCE(debit_amount_lcy, debit_amount) - COALESCE(credit_amount_lcy, credit_amount)'));
        }

        if ($account->isTotalAccount() && ! empty($account->totaling)) {
            $accountCodes = $this->parseTotaling($account->totaling);

            return (float) GlEntry::query()
                ->whereHas('chartOfAccount', function ($query) use ($accountCodes): void {
                    $query->whereIn('account_number', $accountCodes);
                })
                ->whereDate('posting_date', '<=', $asOfDate)
                ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
                ->sum(DB::raw('COALESCE(debit_amount_lcy, debit_amount) - COALESCE(credit_amount_lcy, credit_amount)'));
        }

        return (float) GlEntry::query()
            ->where('chart_of_account_id', $account->id)
            ->whereDate('posting_date', '<=', $asOfDate)
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->sum(DB::raw('COALESCE(debit_amount_lcy, debit_amount) - COALESCE(credit_amount_lcy, credit_amount)'));
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

    private function calculateCurrentEarnings(Carbon $asOfDate, ?int $businessId = null): float
    {
        $incomeBalance = (float) GlEntry::query()
            ->whereHas('chartOfAccount', function ($query): void {
                $query->where('income_balance', IncomeBalanceType::INCOME_STATEMENT);
            })
            ->whereBetween('posting_date', [$asOfDate->copy()->startOfYear()->toDateString(), $asOfDate->toDateString()])
            ->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId))
            ->sum(DB::raw('COALESCE(debit_amount_lcy, debit_amount) - COALESCE(credit_amount_lcy, credit_amount)'));

        return round($incomeBalance * -1, 2);
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

    private function sumAccountsByRowType(string $totaling, Carbon $asOfDate, AccountScheduleRowType|string|null $rowType, ?int $businessId = null, AccountScheduleAmountType|string|null $amountType = null): float
    {
        $accountCodes = $this->parseTotaling($totaling);
        $query = GlEntry::query()->whereHas('chartOfAccount', function ($query) use ($accountCodes): void {
            $query->whereIn('account_number', $accountCodes);
        })->when($businessId !== null, fn ($query) => $query->where('business_id', $businessId));

        $resolvedRowType = $rowType instanceof AccountScheduleRowType
            ? $rowType
            : AccountScheduleRowType::tryFrom((string) $rowType);

        $query = match ($resolvedRowType) {
            AccountScheduleRowType::NET_CHANGE => $query->whereBetween('posting_date', [$asOfDate->copy()->startOfYear(), $asOfDate]),
            AccountScheduleRowType::BEGINNING_BALANCE => $query->whereDate('posting_date', '<', $asOfDate->copy()->startOfYear()),
            default => $query->whereDate('posting_date', '<=', $asOfDate),
        };

        $resolvedAmountType = $amountType instanceof AccountScheduleAmountType
            ? $amountType
            : AccountScheduleAmountType::tryFrom((string) $amountType) ?? AccountScheduleAmountType::NET_AMOUNT;

        return (float) $query->sum(DB::raw(match ($resolvedAmountType) {
            AccountScheduleAmountType::DEBIT_AMOUNT => 'COALESCE(debit_amount_lcy, debit_amount)',
            AccountScheduleAmountType::CREDIT_AMOUNT => 'COALESCE(credit_amount_lcy, credit_amount)',
            default => 'COALESCE(debit_amount_lcy, debit_amount) - COALESCE(credit_amount_lcy, credit_amount)',
        }));
    }
}
