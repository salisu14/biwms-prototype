<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceGoals\Schemas;

use App\Models\PerformanceGoal;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Schemas\Schema;

class PerformanceGoalInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return PerformanceResourceSchema::infolist($schema, PerformanceGoal::class);
    }
}
