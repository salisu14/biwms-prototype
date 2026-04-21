<?php

namespace App\Filament\Resources\GeneralJournalBatches\Pages;

use App\Filament\Resources\GeneralJournalBatches\GeneralJournalBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGeneralJournalBatches extends ListRecords
{
    protected static string $resource = GeneralJournalBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New Batch'),
        ];
    }
}
