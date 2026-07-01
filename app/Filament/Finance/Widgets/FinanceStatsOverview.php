<?php

namespace App\Filament\Finance\Widgets;

use App\Services\Dashboard\FinanceDashboardService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FinanceStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $summary = app(FinanceDashboardService::class)->summary();

        return [
            Stat::make('Cash / Bank', number_format((float) $summary['cash_bank_balance'], 2))
                ->description('From bank ledger entries')
                ->color('success'),
            Stat::make('Receivables', number_format((float) $summary['receivables'], 2))
                ->description('Open customer ledger remaining amount')
                ->color('warning'),
            Stat::make('Payables', number_format((float) $summary['payables'], 2))
                ->description('Open vendor ledger remaining amount')
                ->color('danger'),
            Stat::make('Gross Profit', number_format((float) $summary['gross_profit'], 2))
                ->description('Revenue less COGS from G/L')
                ->color((float) $summary['gross_profit'] >= 0 ? 'success' : 'danger'),
            Stat::make('Trial Balance', $summary['trial_balance']['is_balanced'] ? 'Balanced' : 'Out of Balance')
                ->description('G/L debit and credit health')
                ->color($summary['trial_balance']['is_balanced'] ? 'success' : 'danger'),
            Stat::make('Finance Reconcile', (string) $summary['reconciliation_warnings']['total'])
                ->description('Report-only warning count')
                ->color($summary['reconciliation_warnings']['critical'] > 0 ? 'danger' : 'success'),
        ];
    }
}
