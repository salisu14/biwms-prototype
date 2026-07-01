<?php

namespace App\Filament\Sales\Widgets;

use App\Services\Dashboard\SalesDashboardService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $summary = app(SalesDashboardService::class)->summary();

        return [
            Stat::make('Posted Sales', number_format((float) $summary['posted_invoices']['amount'], 2))
                ->description($summary['posted_invoices']['count'].' posted invoices')
                ->color('success'),
            Stat::make('Payments', number_format((float) $summary['payments']['amount'], 2))
                ->description($summary['payments']['count'].' customer ledger payments')
                ->color('info'),
            Stat::make('Outstanding Receivables', number_format((float) $summary['outstanding_receivables'], 2))
                ->description('From customer ledger remaining amount')
                ->color('warning'),
            Stat::make('Credit Memos / Returns', number_format((float) $summary['credit_memos_returns']['amount'], 2))
                ->description($summary['credit_memos_returns']['count'].' posted credit memos')
                ->color('danger'),
        ];
    }
}
