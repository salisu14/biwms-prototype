<?php

namespace App\Filament\Resources\FAJournalBatches\Pages;

use App\Filament\Resources\FAJournalBatches\FAJournalBatchResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFAJournalBatch extends ViewRecord
{
    protected static string $resource = FAJournalBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
