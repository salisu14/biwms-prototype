<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceGoals\Schemas;

use App\Models\PerformanceGoal;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Schemas\Schema;

class PerformanceGoalForm
{
    public static function configure(Schema $schema): Schema
    {
        return PerformanceResourceSchema::form($schema, PerformanceGoal::class);
    }
}
