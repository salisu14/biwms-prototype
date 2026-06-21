<?php

namespace App\Filament\Resources\ItemJournalBatches\Pages;

use App\Filament\Resources\ItemJournalBatches\ItemJournalBatchResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItemJournalBatch extends ViewRecord
{
    protected static string $resource = ItemJournalBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
