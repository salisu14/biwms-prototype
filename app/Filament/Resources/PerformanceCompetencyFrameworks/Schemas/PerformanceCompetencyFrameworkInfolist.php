<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceCompetencyFrameworks\Schemas;

use App\Models\PerformanceCompetencyFramework;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Schemas\Schema;

class PerformanceCompetencyFrameworkInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return PerformanceResourceSchema::infolist($schema, PerformanceCompetencyFramework::class);
    }
}
