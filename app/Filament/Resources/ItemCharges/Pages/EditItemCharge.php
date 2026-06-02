<?php

namespace App\Filament\Resources\ItemCharges\Pages;

use App\Filament\Resources\ItemCharges\ItemChargeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditItemCharge extends EditRecord
{
    protected static string $resource = ItemChargeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
