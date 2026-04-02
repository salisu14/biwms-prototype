<?php

namespace App\Filament\Resources\Routings\Pages;

use App\Filament\Resources\Routings\RoutingResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRouting extends EditRecord
{
    protected static string $resource = RoutingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
