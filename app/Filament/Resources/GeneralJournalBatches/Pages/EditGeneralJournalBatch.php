<?php

namespace App\Filament\Resources\GeneralJournalBatches\Pages;

use App\Enums\JournalBatchStatus;
use App\Filament\Resources\GeneralJournalBatches\GeneralJournalBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditGeneralJournalBatch extends EditRecord
{
    protected static string $resource = GeneralJournalBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->hidden(fn () => $this->record->status === JournalBatchStatus::POSTED),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        // Prevent editing a posted batch's header
        if ($this->record->status === JournalBatchStatus::POSTED) {
            $this->halt();
        }

        return $data;
    }
}
