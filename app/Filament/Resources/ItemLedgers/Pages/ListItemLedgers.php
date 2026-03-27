<?php

namespace App\Filament\Resources\ItemLedgers\Pages;

use App\Filament\Resources\ItemLedgers\ItemLedgerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItemLedgers extends ListRecords
{
    protected static string $resource = ItemLedgerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
