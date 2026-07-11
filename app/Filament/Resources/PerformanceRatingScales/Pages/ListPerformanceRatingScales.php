<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceRatingScales\Pages;

use App\Filament\Resources\PerformanceRatingScales\PerformanceRatingScaleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPerformanceRatingScales extends ListRecords
{
    protected static string $resource = PerformanceRatingScaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
