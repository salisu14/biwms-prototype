<?php

namespace App\Filament\Resources\FAJournalBatches\Pages;

use App\Filament\Resources\FAJournalBatches\FAJournalBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditFAJournalBatch extends EditRecord
{
    protected static string $resource = FAJournalBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
