<?php

namespace App\Filament\Resources\WorkCenters\Pages;

use App\Filament\Resources\WorkCenters\WorkCenterResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkCenter extends ViewRecord
{
    protected static string $resource = WorkCenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
