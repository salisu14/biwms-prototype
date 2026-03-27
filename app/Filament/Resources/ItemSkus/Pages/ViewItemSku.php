<?php

namespace App\Filament\Resources\ItemSkus\Pages;

use App\Filament\Resources\ItemSkus\ItemSkuResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItemSku extends ViewRecord
{
    protected static string $resource = ItemSkuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
