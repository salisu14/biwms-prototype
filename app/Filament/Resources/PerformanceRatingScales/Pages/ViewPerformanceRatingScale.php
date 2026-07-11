<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceRatingScales\Pages;

use App\Filament\Resources\PerformanceRatingScales\PerformanceRatingScaleResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPerformanceRatingScale extends ViewRecord
{
    protected static string $resource = PerformanceRatingScaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
