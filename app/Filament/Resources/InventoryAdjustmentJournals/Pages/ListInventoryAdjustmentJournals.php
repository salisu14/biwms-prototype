<?php

namespace App\Filament\Resources\InventoryAdjustmentJournals\Pages;

use App\Filament\Resources\InventoryAdjustmentJournals\InventoryAdjustmentJournalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInventoryAdjustmentJournals extends ListRecords
{
    protected static string $resource = InventoryAdjustmentJournalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
