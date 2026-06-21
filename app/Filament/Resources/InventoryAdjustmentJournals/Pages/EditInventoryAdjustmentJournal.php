<?php

namespace App\Filament\Resources\InventoryAdjustmentJournals\Pages;

use App\Filament\Resources\InventoryAdjustmentJournals\InventoryAdjustmentJournalResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditInventoryAdjustmentJournal extends EditRecord
{
    protected static string $resource = InventoryAdjustmentJournalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
