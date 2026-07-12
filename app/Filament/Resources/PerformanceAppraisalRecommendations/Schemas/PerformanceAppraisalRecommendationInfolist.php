<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalRecommendations\Schemas;

use App\Models\PerformanceAppraisalRecommendation;
use App\Support\Filament\CompletedResourceSchema;
use Filament\Schemas\Schema;

class PerformanceAppraisalRecommendationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return CompletedResourceSchema::infolist($schema, PerformanceAppraisalRecommendation::class);
    }
}
