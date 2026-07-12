<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceRatingScales\Schemas;

use App\Models\PerformanceRatingScale;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class PerformanceRatingScaleForm
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::form($schema, PerformanceRatingScale::class);
    }
}
