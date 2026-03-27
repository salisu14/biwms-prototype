<?php

namespace App\Filament\Resources\ItemLots\Pages;

use App\Filament\Resources\ItemLots\ItemLotResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditItemLot extends EditRecord
{
    protected static string $resource = ItemLotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
