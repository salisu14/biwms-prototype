<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalRecommendations\Schemas;

use App\Models\PerformanceAppraisalRecommendation;
use App\Support\Filament\PerformanceResourceSchema;
use Filament\Schemas\Schema;

class PerformanceAppraisalRecommendationForm
{
    public static function configure(Schema $schema): Schema
    {
        return PerformanceResourceSchema::form($schema, PerformanceAppraisalRecommendation::class);
    }
}
