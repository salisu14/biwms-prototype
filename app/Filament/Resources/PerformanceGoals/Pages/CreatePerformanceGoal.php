<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceGoals\Pages;

use App\Filament\Resources\PerformanceGoals\PerformanceGoalResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePerformanceGoal extends CreateRecord
{
    protected static string $resource = PerformanceGoalResource::class;
}
