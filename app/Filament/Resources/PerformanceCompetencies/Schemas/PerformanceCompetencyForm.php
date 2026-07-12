<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceCompetencies\Schemas;

use App\Models\PerformanceCompetency;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class PerformanceCompetencyForm
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::form($schema, PerformanceCompetency::class);
    }
}
