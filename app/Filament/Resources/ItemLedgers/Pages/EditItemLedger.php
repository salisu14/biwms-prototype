<?php

namespace App\Filament\Resources\ItemLedgers\Pages;

use App\Filament\Resources\ItemLedgers\ItemLedgerResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditItemLedger extends EditRecord
{
    protected static string $resource = ItemLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
