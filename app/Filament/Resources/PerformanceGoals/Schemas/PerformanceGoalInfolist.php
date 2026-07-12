<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceGoals\Schemas;

use App\Models\PerformanceGoal;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class PerformanceGoalInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::infolist($schema, PerformanceGoal::class);
    }
}
