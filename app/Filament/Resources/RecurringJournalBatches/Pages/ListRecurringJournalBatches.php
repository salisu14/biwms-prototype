<?php

namespace App\Filament\Resources\RecurringJournalBatches\Pages;

use App\Filament\Resources\RecurringJournalBatches\RecurringJournalBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecurringJournalBatches extends ListRecords
{
    protected static string $resource = RecurringJournalBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New Batch')];
    }
}
