<?php

declare(strict_types=1);

namespace App\Filament\Resources\PerformancePositionCompetencies\Pages;

use App\Filament\Resources\PerformancePositionCompetencies\PerformancePositionCompetencyResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPerformancePositionCompetency extends EditRecord
{
    protected static string $resource = PerformancePositionCompetencyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
