<?php

namespace App\Filament\Resources\RoutingVersions\Pages;

use App\Filament\Resources\RoutingVersions\RoutingVersionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRoutingVersions extends ListRecords
{
    protected static string $resource = RoutingVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
