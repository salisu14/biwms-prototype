<?php

namespace App\Filament\Resources\RecurringJournalBatches\Pages;

use App\Enums\JournalBatchStatus;
use App\Filament\Resources\RecurringJournalBatches\RecurringJournalBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRecurringJournalBatch extends EditRecord
{
    protected static string $resource = RecurringJournalBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->hidden(fn () => $this->record->status === JournalBatchStatus::POSTED),
        ];
    }
}
