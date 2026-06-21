<?php

namespace App\Filament\Service\Widgets;

use App\Models\MaintenanceContract;
use App\Models\MaintenanceContractSchedule;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ServiceStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Active Contracts', MaintenanceContract::query()->active()->count())
                ->description('In-service contracts')
                ->color('info'),
            Stat::make('Dispatches Due', MaintenanceContractSchedule::query()->whereDate('next_service_date', '<=', now())->where('is_active', true)->count())
                ->description('Schedules due for execution')
                ->color('warning'),
            Stat::make('Contracts Expiring 30d', MaintenanceContract::query()->expiringSoon(30)->count())
                ->description('Renewal pipeline')
                ->color('success'),
        ];
    }
}
