<?php

namespace App\Filament\Resources\PostedShipments\Pages;

use App\Filament\Resources\PostedShipments\PostedShipmentResource;
use Filament\Resources\Pages\ListRecords;

class ListPostedShipments extends ListRecords
{
    protected static string $resource = PostedShipmentResource::class;
}
