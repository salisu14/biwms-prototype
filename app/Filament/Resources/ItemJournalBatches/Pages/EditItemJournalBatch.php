<?php

namespace App\Filament\Resources\ItemJournalBatches\Pages;

use App\Filament\Resources\ItemJournalBatches\ItemJournalBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditItemJournalBatch extends EditRecord
{
    protected static string $resource = ItemJournalBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
