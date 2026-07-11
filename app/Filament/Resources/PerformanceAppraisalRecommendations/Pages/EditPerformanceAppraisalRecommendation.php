<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceAppraisalRecommendations\Pages;

use App\Filament\Resources\PerformanceAppraisalRecommendations\PerformanceAppraisalRecommendationResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPerformanceAppraisalRecommendation extends EditRecord
{
    protected static string $resource = PerformanceAppraisalRecommendationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
