<?php

namespace App\Filament\Resources\RoutingVersions\Pages;

use App\Filament\Resources\RoutingVersions\RoutingVersionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRoutingVersion extends ViewRecord
{
    protected static string $resource = RoutingVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
