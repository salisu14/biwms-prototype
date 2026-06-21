<?php

namespace App\Filament\Resources\Routings\Pages;

use App\Filament\Resources\Routings\RoutingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRouting extends ViewRecord
{
    protected static string $resource = RoutingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
