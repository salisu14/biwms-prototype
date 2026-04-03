<?php

namespace App\Filament\Resources\RoutingVersions\Pages;

use App\Filament\Resources\RoutingVersions\RoutingVersionResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRoutingVersion extends EditRecord
{
    protected static string $resource = RoutingVersionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
