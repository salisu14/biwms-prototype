<?php

namespace App\Filament\Resources\InventoryAdjustmentJournals\Pages;

use App\Filament\Resources\InventoryAdjustmentJournals\InventoryAdjustmentJournalResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewInventoryAdjustmentJournal extends ViewRecord
{
    protected static string $resource = InventoryAdjustmentJournalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
