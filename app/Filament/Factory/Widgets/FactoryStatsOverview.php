<?php

namespace App\Filament\Factory\Widgets;

use App\Services\Dashboard\ManufacturingDashboardService;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FactoryStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        $summary = app(ManufacturingDashboardService::class)->summary();

        return [
            Stat::make('Open Orders', $summary['open_production_orders'])
                ->description('Planned, released, or in progress')
                ->color('warning'),
            Stat::make('WIP Value', number_format((float) $summary['wip_value'], 2))
                ->description('From production value entries')
                ->color('info'),
            Stat::make('Output Qty', number_format((float) $summary['output_quantity'], 4))
                ->description('Output item ledger quantity')
                ->color('success'),
            Stat::make('Component Shortages', count($summary['component_shortages']))
                ->description('Based on component demand vs ledger stock')
                ->color(count($summary['component_shortages']) > 0 ? 'danger' : 'success'),
            Stat::make('Production Variance', number_format((float) $summary['production_variance'], 2))
                ->description('From value entry variance amounts')
                ->color(abs((float) $summary['production_variance']) > 0.01 ? 'warning' : 'success'),
        ];
    }
}
