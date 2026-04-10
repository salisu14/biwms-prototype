<?php

namespace App\Filament\Resources\ShippingAgents\Pages;

use App\Filament\Resources\ShippingAgents\ShippingAgentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShippingAgents extends ListRecords
{
    protected static string $resource = ShippingAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
