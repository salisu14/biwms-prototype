<?php

namespace App\Filament\Resources\ItemTrackingCodes\Pages;

use App\Filament\Resources\ItemTrackingCodes\ItemTrackingCodeResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItemTrackingCode extends ViewRecord
{
    protected static string $resource = ItemTrackingCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
