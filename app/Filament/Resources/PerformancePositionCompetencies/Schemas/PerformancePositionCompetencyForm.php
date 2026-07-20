<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformancePositionCompetencies\Schemas;

use App\Models\PerformancePositionCompetency;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Schemas\Schema;

class PerformancePositionCompetencyForm
{
    public static function configure(Schema $schema): Schema
    {
        return PerformanceResourceSchema::form($schema, PerformancePositionCompetency::class);
    }
}
