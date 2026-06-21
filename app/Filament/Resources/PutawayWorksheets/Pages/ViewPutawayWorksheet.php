<?php

namespace App\Filament\Resources\PutawayWorksheets\Pages;

use App\Filament\Resources\PutawayWorksheets\PutawayWorksheetResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewPutawayWorksheet extends ViewRecord
{
    protected static string $resource = PutawayWorksheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
