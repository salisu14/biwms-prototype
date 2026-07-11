<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformanceRatingScales\Pages;

use App\Filament\Resources\PerformanceRatingScales\PerformanceRatingScaleResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPerformanceRatingScale extends EditRecord
{
    protected static string $resource = PerformanceRatingScaleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
