<?php

namespace App\Filament\Resources\LocationMasters\Pages;

use App\Filament\Resources\LocationMasters\LocationMasterResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLocationMaster extends ViewRecord
{
    protected static string $resource = LocationMasterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
