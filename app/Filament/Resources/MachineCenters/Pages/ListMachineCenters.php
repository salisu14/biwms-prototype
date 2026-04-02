<?php

namespace App\Filament\Resources\MachineCenters\Pages;

use App\Filament\Resources\MachineCenters\MachineCenterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMachineCenters extends ListRecords
{
    protected static string $resource = MachineCenterResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
