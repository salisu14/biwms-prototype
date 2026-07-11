<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalRecommendations\Pages;

use App\Filament\Resources\PerformanceAppraisalRecommendations\PerformanceAppraisalRecommendationResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPerformanceAppraisalRecommendations extends ListRecords
{
    protected static string $resource = PerformanceAppraisalRecommendationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
