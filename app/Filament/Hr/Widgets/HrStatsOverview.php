<?php

namespace App\Filament\Hr\Widgets;

use App\Models\Employee;
use App\Models\PayrollDocument;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class HrStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Active Employees', Employee::query()->where('is_active', true)->count())
                ->description('Current active workforce')
                ->color('success'),
            Stat::make('New Hires (MTD)', Employee::query()->whereMonth('created_at', now()->month)->count())
                ->description('Added this month')
                ->color('info'),
            Stat::make('Payroll Docs (Draft)', PayrollDocument::query()->where('status', 'DRAFT')->count())
                ->description('Awaiting processing')
                ->color('warning'),
        ];
    }
}
