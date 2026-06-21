<?php

namespace App\Filament\Resources\PostedShipments\Pages;

use App\Filament\Resources\PostedShipments\PostedShipmentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPostedShipment extends ViewRecord
{
    protected static string $resource = PostedShipmentResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
