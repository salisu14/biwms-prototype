<?php

namespace App\Filament\Resources\ItemJournalBatches\Pages;

use App\Filament\Resources\ItemJournalBatches\ItemJournalBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItemJournalBatches extends ListRecords
{
    protected static string $resource = ItemJournalBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
