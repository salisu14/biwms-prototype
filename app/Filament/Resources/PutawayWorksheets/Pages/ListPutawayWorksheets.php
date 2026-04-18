<?php

namespace App\Filament\Resources\PutawayWorksheets\Pages;

use App\Filament\Resources\PutawayWorksheets\PutawayWorksheetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPutawayWorksheets extends ListRecords
{
    protected static string $resource = PutawayWorksheetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
