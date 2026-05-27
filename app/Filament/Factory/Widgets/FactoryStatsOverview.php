<?php

namespace App\Filament\Factory\Widgets;

use App\Models\Manufacturing\ProductionOrder;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FactoryStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Released Orders', ProductionOrder::query()->where('status', 'RELEASED')->count())
                ->description('Awaiting execution')
                ->color('warning'),
            Stat::make('In Progress', ProductionOrder::query()->where('status', 'IN_PROGRESS')->count())
                ->description('Currently on shop floor')
                ->color('info'),
            Stat::make('Finished (MTD)', ProductionOrder::query()
                ->where('status', 'FINISHED')
                ->whereMonth('updated_at', now()->month)
                ->count())
                ->description('Completed this month')
                ->color('success'),
        ];
    }
}
