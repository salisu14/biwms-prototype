<?php

namespace App\Filament\Resources\PostedShipments\Pages;

use App\Filament\Resources\PostedShipments\PostedShipmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPostedShipments extends ListRecords
{
    protected static string $resource = PostedShipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
