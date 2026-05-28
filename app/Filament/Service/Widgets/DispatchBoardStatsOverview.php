<?php

namespace App\Filament\Service\Widgets;

use App\Models\MaintenanceContractSchedule;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DispatchBoardStatsOverview extends BaseWidget
{
    use InteractsWithPageFilters;

    protected function getStats(): array
    {
        $dateFrom = $this->filters['date_from'] ?? now()->toDateString();
        $dateTo = $this->filters['date_to'] ?? now()->addDays(14)->toDateString();
        $technicianId = $this->filters['technician_id'] ?? null;

        $baseQuery = MaintenanceContractSchedule::query()
            ->where('is_active', true)
            ->when($technicianId, fn ($query) => $query->whereHas('maintenanceContract', fn ($contractQuery) => $contractQuery->where('responsible_employee_id', $technicianId)));

        return [
            Stat::make('Overdue', (clone $baseQuery)
                ->whereDate('next_service_date', '<', now()->toDateString())
                ->count())
                ->description('Past due dispatches')
                ->color('danger'),
            Stat::make('Due Today', (clone $baseQuery)
                ->whereDate('next_service_date', now()->toDateString())
                ->count())
                ->description('Must be serviced today')
                ->color('warning'),
            Stat::make('In Selected Range', (clone $baseQuery)
                ->whereBetween('next_service_date', [$dateFrom, $dateTo])
                ->count())
                ->description('Dispatch load in filter range')
                ->color('info'),
        ];
    }
}
