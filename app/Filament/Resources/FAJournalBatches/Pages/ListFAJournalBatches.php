<?php

namespace App\Filament\Resources\FAJournalBatches\Pages;

use App\Filament\Resources\FAJournalBatches\FAJournalBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFAJournalBatches extends ListRecords
{
    protected static string $resource = FAJournalBatchResource::class;

    protected static ?string $title = 'FA Journal Batches';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Create FAJ Batch'),
        ];
    }
}
