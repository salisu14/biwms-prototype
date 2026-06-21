<?php

namespace App\Filament\Service\Widgets;

use App\Models\MaintenanceContractSchedule;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class TechnicianWorkloadWidget extends BaseWidget
{
    use InteractsWithPageFilters;

    protected static ?int $sort = 4;

    protected function getStats(): array
    {
        $dateFrom = $this->filters['date_from'] ?? now()->toDateString();
        $dateTo = $this->filters['date_to'] ?? now()->addDays(14)->toDateString();
        $technicianId = $this->filters['technician_id'] ?? null;
        $unassignedOnly = (bool) ($this->filters['unassigned_only'] ?? false);

        $query = MaintenanceContractSchedule::query()
            ->with('maintenanceContract.responsibleEmployee')
            ->where('is_active', true)
            ->whereBetween('next_service_date', [$dateFrom, $dateTo])
            ->when($unassignedOnly, fn ($builder) => $builder->whereHas('maintenanceContract', fn ($contractBuilder) => $contractBuilder->whereNull('responsible_employee_id')))
            ->when(! $unassignedOnly && $technicianId, fn ($builder) => $builder->whereHas('maintenanceContract', fn ($contractBuilder) => $contractBuilder->where('responsible_employee_id', $technicianId)));

        $grouped = $query
            ->get()
            ->groupBy(fn (MaintenanceContractSchedule $schedule): string => (string) ($schedule->maintenanceContract?->responsible_employee_id ?? 0))
            ->map(function ($group, string $employeeId): array {
                $first = $group->first();
                $employee = $first?->maintenanceContract?->responsibleEmployee;
                $name = $employee
                    ? trim(($employee->first_name ?? '').' '.($employee->last_name ?? ''))
                    : 'Unassigned';

                return [
                    'name' => $name,
                    'count' => $group->count(),
                ];
            })
            ->sortByDesc('count')
            ->take(4)
            ->values();

        if ($grouped->isEmpty()) {
            return [
                Stat::make('No Dispatches', 0)
                    ->description('No workload in selected range')
                    ->color('gray'),
            ];
        }

        return $grouped
            ->map(fn (array $row): Stat => Stat::make($row['name'], $row['count'])->description('Dispatches')->color('info'))
            ->all();
    }
}
