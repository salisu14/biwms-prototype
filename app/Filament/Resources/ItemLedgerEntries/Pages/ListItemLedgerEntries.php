<?php

namespace App\Filament\Resources\ItemLedgerEntries\Pages;

use App\Filament\Resources\ItemLedgerEntries\ItemLedgerEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItemLedgerEntries extends ListRecords
{
    protected static string $resource = ItemLedgerEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
