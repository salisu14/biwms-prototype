<?php

declare(strict_types=1);

use App\Enums\AccountCategory;
use App\Enums\AccountScheduleAmountType;
use App\Enums\AccountScheduleRowType;
use App\Enums\AccountScheduleTotalingType;
use App\Enums\IncomeBalanceType;
use App\Enums\SourceType;
use App\Models\AccountSchedule;
use App\Models\AccountScheduleLine;
use App\Models\Business;
use App\Models\ChartOfAccount;
use App\Models\GlEntry;
use App\Services\Finance\AccountScheduleFormulaEvaluator;
use App\Services\Finance\BalanceSheetService;
use App\Services\IncomeStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('evaluates arithmetic and exact row references without executing PHP', function (): void {
    $evaluator = app(AccountScheduleFormulaEvaluator::class);

    expect($evaluator->evaluate('A1 + A10 * 2', fn (string $reference): float => ['A1' => 10.0, 'A10' => 3.0][$reference], ['A1', 'A10']))
        ->toBe(16.0);
    expect($evaluator->evaluate('100 / 400 * 100', fn (): float => 0.0))->toBe(25.0);

    expect(fn () => $evaluator->evaluate('phpinfo()', fn (): float => 0.0))
        ->toThrow(InvalidArgumentException::class);
});

it('rejects missing references, malformed expressions, and division by zero', function (): void {
    $evaluator = app(AccountScheduleFormulaEvaluator::class);

    expect(fn () => $evaluator->validate('A1 + MISSING', ['A1']))
        ->toThrow(InvalidArgumentException::class, 'Unknown account schedule row reference');
    expect(fn () => $evaluator->validate('A1 +', ['A1']))
        ->toThrow(InvalidArgumentException::class);
    expect(fn () => $evaluator->evaluate('10 / 0', fn (): float => 0.0))
        ->toThrow(InvalidArgumentException::class, 'Division by zero');
});

it('evaluates forward references and detects circular references in schedules', function (): void {
    $schedule = scheduleForFormulaTests('Forward and circular');
    scheduleLine($schedule, 'TOTAL', 'A1 + 5', AccountScheduleTotalingType::FORMULA, 20);
    scheduleLine($schedule, 'A1', '10000', AccountScheduleTotalingType::POSTING_ACCOUNTS, 10);

    $cash = formulaAccount('10000', AccountCategory::LIQUID_ASSET);
    formulaGl($cash, 7, 0, '2026-01-10');

    $report = app(BalanceSheetService::class)->generateFromSchedule($schedule->id, Carbon::parse('2026-01-31'));
    expect(collect($report['lines'])->firstWhere('account_no', 'TOTAL')['amount'])->toBe(12.0);

    $circular = scheduleForFormulaTests('Circular');
    scheduleLine($circular, 'A', 'B + 1', AccountScheduleTotalingType::FORMULA, 10);
    scheduleLine($circular, 'B', 'A + 1', AccountScheduleTotalingType::FORMULA, 20);

    $circularReport = app(BalanceSheetService::class)->generateFromSchedule($circular->id, Carbon::parse('2026-01-31'));
    expect(collect($circularReport['lines'])->pluck('amount')->all())->toBe([0.0, 0.0]);
});

it('uses LCY values, explicit business scope, and date scope for schedule rows', function (): void {
    $businessA = Business::query()->create(['code' => 'FR-E-A', 'name' => 'FR-E A', 'is_active' => true]);
    $businessB = Business::query()->create(['code' => 'FR-E-B', 'name' => 'FR-E B', 'is_active' => true]);
    $account = formulaAccount('41000', AccountCategory::REVENUE, IncomeBalanceType::INCOME_STATEMENT);
    formulaGl($account, 0, 100, '2026-01-15', $businessA->id, null, 15000);
    formulaGl($account, 0, 200, '2026-01-15', $businessB->id, null, 30000);
    formulaGl($account, 0, 50, '2025-12-31', $businessA->id, null, 7000);

    $schedule = scheduleForFormulaTests('Business and date');
    scheduleLine($schedule, 'REV', '41000', AccountScheduleTotalingType::POSTING_ACCOUNTS, 10, AccountScheduleRowType::NET_CHANGE);

    $report = app(IncomeStatementService::class)->generateFromSchedule(
        $schedule->id,
        Carbon::parse('2026-01-01'),
        Carbon::parse('2026-01-31'),
        businessId: $businessA->id,
    );

    expect($report->first()['amount'])->toBe(15000.0);
});

it('honors debit and credit amount types for schedule rows', function (): void {
    $account = formulaAccount('42000', AccountCategory::REVENUE, IncomeBalanceType::INCOME_STATEMENT);
    formulaGl($account, 40, 100, '2026-02-10');
    $schedule = scheduleForFormulaTests('Amount types');
    scheduleLine($schedule, 'DEBIT', '42000', AccountScheduleTotalingType::POSTING_ACCOUNTS, 10, AccountScheduleRowType::NET_CHANGE, AccountScheduleAmountType::DEBIT_AMOUNT);
    scheduleLine($schedule, 'CREDIT', '42000', AccountScheduleTotalingType::POSTING_ACCOUNTS, 20, AccountScheduleRowType::NET_CHANGE, AccountScheduleAmountType::CREDIT_AMOUNT);

    $report = app(IncomeStatementService::class)->generateFromSchedule(
        $schedule->id,
        Carbon::parse('2026-02-01'),
        Carbon::parse('2026-02-28'),
    );

    expect($report->pluck('amount')->all())->toBe([40.0, 100.0]);
});

function scheduleForFormulaTests(string $name): AccountSchedule
{
    return AccountSchedule::query()->create(['name' => $name, 'description' => 'FR-E test schedule']);
}

function scheduleLine(
    AccountSchedule $schedule,
    string $rowNo,
    string $totaling,
    AccountScheduleTotalingType $type,
    int $lineNo,
    AccountScheduleRowType $rowType = AccountScheduleRowType::BALANCE_AT_DATE,
    AccountScheduleAmountType $amountType = AccountScheduleAmountType::NET_AMOUNT,
): AccountScheduleLine {
    return $schedule->lines()->create([
        'line_no' => $lineNo,
        'row_no' => $rowNo,
        'description' => $rowNo,
        'totaling_type' => $type,
        'totaling' => $totaling,
        'row_type' => $rowType,
        'amount_type' => $amountType,
        'show_opposite_sign' => $rowNo === 'REV',
    ]);
}

function formulaAccount(string $number, AccountCategory $category, IncomeBalanceType $incomeBalance = IncomeBalanceType::BALANCE_SHEET): ChartOfAccount
{
    return ChartOfAccount::factory()->create([
        'account_number' => $number,
        'account_category' => $category,
        'account_type' => $category === AccountCategory::REVENUE ? 'REVENUE' : 'ASSET',
        'income_balance' => $incomeBalance,
        'blocked' => false,
    ]);
}

function formulaGl(ChartOfAccount $account, float $debit, float $credit, string $date, ?int $businessId = null, ?float $debitLcy = null, ?float $creditLcy = null): void
{
    GlEntry::query()->create([
        'entry_number' => ((int) GlEntry::query()->max('entry_number')) + 1,
        'transaction_number' => ((int) GlEntry::query()->max('transaction_number')) + 1,
        'chart_of_account_id' => $account->id,
        'debit_amount' => $debit,
        'debit_amount_lcy' => $debitLcy ?? $debit,
        'credit_amount' => $credit,
        'credit_amount_lcy' => $creditLcy ?? $credit,
        'amount' => $debit - $credit,
        'amount_lcy' => ($debitLcy ?? $debit) - ($creditLcy ?? $credit),
        'source_type' => SourceType::GENERAL_JOURNAL,
        'source_number' => 'FR-E-TEST',
        'document_type' => 'GENERAL_JOURNAL',
        'document_number' => 'FR-E-TEST',
        'document_date' => $date,
        'posting_date' => $date,
        'description' => 'FR-E formula test',
        'business_id' => $businessId,
    ]);
}
