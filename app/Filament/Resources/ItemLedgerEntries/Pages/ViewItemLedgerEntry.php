<?php

namespace App\Filament\Resources\ItemLedgerEntries\Pages;

use App\Filament\Resources\ItemLedgerEntries\ItemLedgerEntryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItemLedgerEntry extends ViewRecord
{
    protected static string $resource = ItemLedgerEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
