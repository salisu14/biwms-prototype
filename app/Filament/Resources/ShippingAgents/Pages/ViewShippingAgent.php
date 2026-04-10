<?php

namespace App\Filament\Resources\ShippingAgents\Pages;

use App\Filament\Resources\ShippingAgents\ShippingAgentResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewShippingAgent extends ViewRecord
{
    protected static string $resource = ShippingAgentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
