<?php

namespace App\Filament\Resources\MachineCenters\Pages;

use App\Filament\Resources\MachineCenters\MachineCenterResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewMachineCenter extends ViewRecord
{
    protected static string $resource = MachineCenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
