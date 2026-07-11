<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalRecommendations\Pages;

use App\Filament\Resources\PerformanceAppraisalRecommendations\PerformanceAppraisalRecommendationResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePerformanceAppraisalRecommendation extends CreateRecord
{
    protected static string $resource = PerformanceAppraisalRecommendationResource::class;
}
