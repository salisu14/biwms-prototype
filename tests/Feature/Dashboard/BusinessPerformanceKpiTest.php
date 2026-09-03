<?php

use App\Enums\AccountCategory;
use App\Enums\IncomeBalanceType;
use App\Enums\SourceType;
use App\Models\Business;
use App\Models\ChartOfAccount;
use App\Models\GlEntry;
use App\Services\Dashboard\FinanceDashboardService;
use App\Services\IncomeStatementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

it('uses authoritative pnl signs and reconciles every business performance kpi', function (): void {
    $business = Business::query()->create([
        'code' => 'KPI-SIGN',
        'name' => 'KPI Sign Test',
        'is_active' => true,
    ]);

    $revenue = businessPerformanceAccount('48001', 'KPI Revenue', AccountCategory::REVENUE);
    $cogs = businessPerformanceAccount('58001', 'KPI COGS', AccountCategory::COGS);
    $expense = businessPerformanceAccount('68001', 'KPI Expense', AccountCategory::OPERATING_EXPENSE);
    $offset = businessPerformanceAccount('98001', 'KPI Offset', AccountCategory::EQUITY);

    businessPerformanceGl('KPI-001', [
        [$revenue, 0, 350000],
        [$cogs, 200000, 0],
        [$expense, 50000, 0],
        [$offset, 100000, 0],
    ], $business->id, '2026-08-15');

    $from = Carbon::parse('2026-08-01');
    $to = Carbon::parse('2026-08-31');
    $dashboard = app(FinanceDashboardService::class)->summary($from, $to, $business->id);
    $pnl = app(IncomeStatementService::class)->generate($from, $to, businessId: $business->id)->summary;

    expect($dashboard['revenue'])->toBe(350000.0)
        ->and($dashboard['cogs'])->toBe(200000.0)
        ->and($dashboard['gross_profit'])->toBe(150000.0)
        ->and($dashboard['operating_expenses'])->toBe(50000.0)
        ->and($dashboard['operating_profit'])->toBe(100000.0)
        ->and($dashboard['net_profit_loss'])->toBe(100000.0)
        ->and($dashboard['gross_margin_percent'])->toBe(42.86)
        ->and($dashboard['operating_margin_percent'])->toBe(28.57)
        ->and($dashboard['net_margin_percent'])->toBe(28.57)
        ->and($dashboard['revenue'])->toBe(round((float) $pnl['total_revenue'], 2))
        ->and($dashboard['cogs'])->toBe(round((float) $pnl['total_cogs'], 2))
        ->and($dashboard['gross_profit'])->toBe(round((float) $pnl['gross_profit'], 2))
        ->and($dashboard['operating_expenses'])->toBe(round((float) $pnl['operating_expenses'], 2))
        ->and($dashboard['operating_profit'])->toBe(round((float) $pnl['operating_income'], 2))
        ->and($dashboard['net_profit_loss'])->toBe(round((float) $pnl['net_income'], 2));
});

it('preserves contra revenue and isolates business and date periods', function (): void {
    $businessA = Business::query()->create(['code' => 'KPI-A', 'name' => 'KPI A', 'is_active' => true]);
    $businessB = Business::query()->create(['code' => 'KPI-B', 'name' => 'KPI B', 'is_active' => true]);
    $revenue = businessPerformanceAccount('48002', 'KPI Revenue 2', AccountCategory::REVENUE);
    $expense = businessPerformanceAccount('68002', 'KPI Expense 2', AccountCategory::OPERATING_EXPENSE);
    $offset = businessPerformanceAccount('98002', 'KPI Offset 2', AccountCategory::EQUITY);

    businessPerformanceGl('KPI-A-001', [[$revenue, 0, 350000], [$revenue, 50000, 0], [$offset, 300000, 0]], $businessA->id, '2026-08-15');
    businessPerformanceGl('KPI-B-001', [[$revenue, 0, 900000], [$offset, 900000, 0]], $businessB->id, '2026-08-15');
    businessPerformanceGl('KPI-A-002', [[$revenue, 0, 100000]], $businessA->id, '2026-07-15');
    $businessC = Business::query()->create(['code' => 'KPI-C', 'name' => 'KPI C', 'is_active' => true]);
    businessPerformanceGl('KPI-C-001', [[$revenue, 0, 100000], [$expense, 130000, 0], [$offset, 0, 30000]], $businessC->id, '2026-08-20');

    $service = app(FinanceDashboardService::class);
    $august = $service->summary(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), $businessA->id);
    $businessBSummary = $service->summary(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), $businessB->id);
    $july = $service->summary(Carbon::parse('2026-07-01'), Carbon::parse('2026-07-31'), $businessA->id);
    $loss = $service->summary(Carbon::parse('2026-08-01'), Carbon::parse('2026-08-31'), $businessC->id);
    $zeroRevenue = $service->summary(Carbon::parse('2026-09-01'), Carbon::parse('2026-09-30'), $businessA->id);

    expect($august['revenue'])->toBe(300000.0)
        ->and($businessBSummary['revenue'])->toBe(900000.0)
        ->and($july['revenue'])->toBe(100000.0)
        ->and($loss['net_profit_loss'])->toBe(-30000.0)
        ->and($zeroRevenue['gross_margin_percent'])->toBeNull()
        ->and($zeroRevenue['net_margin_percent'])->toBeNull();
});

function businessPerformanceAccount(string $number, string $name, AccountCategory $category): ChartOfAccount
{
    return ChartOfAccount::factory()->create([
        'account_number' => $number,
        'name' => $name,
        'account_category' => $category,
        'account_type' => match ($category) {
            AccountCategory::REVENUE => 'REVENUE',
            AccountCategory::COGS => 'COGS',
            AccountCategory::EQUITY => 'EQUITY',
            default => 'EXPENSE',
        },
        'income_balance' => $category->isIncomeStatement()
            ? IncomeBalanceType::INCOME_STATEMENT
            : IncomeBalanceType::BALANCE_SHEET,
        'blocked' => false,
    ]);
}

function businessPerformanceGl(string $documentNumber, array $lines, int $businessId, string $postingDate): void
{
    $transactionNumber = ((int) GlEntry::query()->max('transaction_number')) + 1;
    foreach ($lines as [$account, $debit, $credit]) {
        GlEntry::query()->create([
            'entry_number' => ((int) GlEntry::query()->max('entry_number')) + 1,
            'transaction_number' => $transactionNumber,
            'chart_of_account_id' => $account->id,
            'debit_amount' => $debit,
            'credit_amount' => $credit,
            'debit_amount_lcy' => $debit,
            'credit_amount_lcy' => $credit,
            'amount' => $debit - $credit,
            'source_type' => SourceType::GENERAL_JOURNAL,
            'source_number' => $documentNumber,
            'document_type' => 'KPI_TEST',
            'document_number' => $documentNumber,
            'document_date' => $postingDate,
            'posting_date' => $postingDate,
            'business_id' => $businessId,
            'description' => 'Business performance KPI test',
        ]);
    }
}
