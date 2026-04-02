<?php

namespace App\Filament\Resources\WorkCenterGroups\Pages;

use App\Filament\Resources\WorkCenterGroups\WorkCenterGroupResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewWorkCenterGroup extends ViewRecord
{
    protected static string $resource = WorkCenterGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
