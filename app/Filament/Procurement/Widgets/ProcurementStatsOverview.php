<?php

namespace App\Filament\Procurement\Widgets;

use App\Services\Dashboard\PurchaseDashboardService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProcurementStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $summary = app(PurchaseDashboardService::class)->summary();

        return [
            Stat::make('Posted Purchases', number_format((float) collect($summary['purchases_by_vendor'])->sum('amount'), 2))
                ->description(count($summary['purchases_by_vendor']).' vendors in period')
                ->color('info'),
            Stat::make('Outstanding Payables', number_format((float) $summary['outstanding_payables'], 2))
                ->description('From vendor ledger remaining amount')
                ->color('warning'),
            Stat::make('Receipts Not Invoiced', number_format((float) $summary['receipts_not_invoiced']['quantity'], 4))
                ->description(number_format((float) $summary['receipts_not_invoiced']['amount'], 2).' expected value')
                ->color('danger'),
            Stat::make('Invoices Not Paid', number_format((float) $summary['invoices_not_paid']['amount'], 2))
                ->description($summary['invoices_not_paid']['count'].' open posted invoices')
                ->color('success'),
        ];
    }
}
