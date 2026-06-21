<?php

namespace App\Filament\Warehouse\Widgets;

use App\Models\WarehousePick;
use App\Models\WarehousePutaway;
use App\Models\WarehouseShipment;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class WarehouseStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Pending Picks', WarehousePick::query()->whereIn('status', ['OPEN', 'RELEASED'])->count())
                ->description('Ready for picking')
                ->color('warning'),
            Stat::make('Pending Put-aways', WarehousePutaway::query()->whereIn('status', ['OPEN', 'RELEASED'])->count())
                ->description('Awaiting put-away')
                ->color('info'),
            Stat::make('Open Shipments', WarehouseShipment::query()->whereIn('status', ['OPEN', 'RELEASED'])->count())
                ->description('To be shipped')
                ->color('success'),
        ];
    }
}
