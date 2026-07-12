<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceGoals\Schemas;

use App\Models\PerformanceGoal;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class PerformanceGoalForm
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::form($schema, PerformanceGoal::class);
    }
}
