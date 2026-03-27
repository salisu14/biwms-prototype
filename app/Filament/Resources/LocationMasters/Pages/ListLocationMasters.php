<?php

namespace App\Filament\Resources\LocationMasters\Pages;

use App\Filament\Resources\LocationMasters\LocationMasterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLocationMasters extends ListRecords
{
    protected static string $resource = LocationMasterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
