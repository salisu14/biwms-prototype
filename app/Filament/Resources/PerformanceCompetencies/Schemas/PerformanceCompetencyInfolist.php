<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceCompetencies\Schemas;

use App\Models\PerformanceCompetency;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Schemas\Schema;

class PerformanceCompetencyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return PerformanceResourceSchema::infolist($schema, PerformanceCompetency::class);
    }
}
