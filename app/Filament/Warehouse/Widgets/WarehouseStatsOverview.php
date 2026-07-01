<?php

namespace App\Filament\Warehouse\Widgets;

use App\Services\Dashboard\InventoryDashboardService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WarehouseStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $summary = app(InventoryDashboardService::class)->summary();

        return [
            Stat::make('Ledger Stock Qty', number_format((float) $summary['stock_quantity'], 4))
                ->description('From item ledger entries')
                ->color('info'),
            Stat::make('Stock Value', number_format((float) $summary['stock_value'], 2))
                ->description('From value entries')
                ->color('success'),
            Stat::make('Negative Stock', $summary['negative_stock_count'])
                ->description('Ledger item/location/tracking groups')
                ->color($summary['negative_stock_count'] > 0 ? 'danger' : 'success'),
            Stat::make('Stock Mismatches', $summary['stock_mismatch_warnings'])
                ->description('Inventory reconcile warnings')
                ->color($summary['stock_mismatch_warnings'] > 0 ? 'warning' : 'success'),
            Stat::make('Low Stock Items', count($summary['low_stock_items']))
                ->description('Ledger quantity at or below reorder point')
                ->color(count($summary['low_stock_items']) > 0 ? 'warning' : 'success'),
        ];
    }
}
