<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformancePositionCompetencies\Schemas;

use App\Models\PerformancePositionCompetency;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class PerformancePositionCompetencyForm
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::form($schema, PerformancePositionCompetency::class);
    }
}
