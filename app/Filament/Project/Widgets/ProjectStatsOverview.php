<?php

namespace App\Filament\Project\Widgets;

use App\Models\Manufacturing\CapExProject;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProjectStatsOverview extends BaseWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('Active Projects', CapExProject::query()->active()->count())
                ->description('Approved / in progress / on hold')
                ->color('info'),
            Stat::make('Pending Approval', CapExProject::query()->pendingApproval()->count())
                ->description('Awaiting authorization')
                ->color('warning'),
            Stat::make('Over Budget', CapExProject::query()->overBudget()->count())
                ->description('Actual exceeds budget')
                ->color('danger'),
        ];
    }
}
