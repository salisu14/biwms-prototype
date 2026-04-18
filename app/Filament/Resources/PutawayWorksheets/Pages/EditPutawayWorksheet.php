<?php

namespace App\Filament\Resources\PutawayWorksheets\Pages;

use App\Filament\Resources\PutawayWorksheets\PutawayWorksheetResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPutawayWorksheet extends EditRecord
{
    protected static string $resource = PutawayWorksheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
