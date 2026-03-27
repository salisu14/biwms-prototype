<?php

namespace App\Filament\Resources\ItemSkus\Pages;

use App\Filament\Resources\ItemSkus\ItemSkuResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditItemSku extends EditRecord
{
    protected static string $resource = ItemSkuResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
