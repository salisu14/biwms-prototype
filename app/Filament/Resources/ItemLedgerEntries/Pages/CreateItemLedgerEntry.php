<?php

namespace App\Filament\Resources\ItemLedgerEntries\Pages;

use App\Filament\Resources\ItemLedgerEntries\ItemLedgerEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateItemLedgerEntry extends CreateRecord
{
    protected static string $resource = ItemLedgerEntryResource::class;
}
