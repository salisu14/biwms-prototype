<?php

namespace App\Filament\Resources\Routings\Pages;

use App\Filament\Resources\Routings\RoutingResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRoutings extends ListRecords
{
    protected static string $resource = RoutingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
