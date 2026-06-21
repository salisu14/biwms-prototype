<?php

namespace App\Filament\Resources\WarehouseJournalBatches\Pages;

use App\Enums\JournalBatchStatus;
use App\Filament\Resources\WarehouseJournalBatches\WarehouseJournalBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditWarehouseJournalBatch extends EditRecord
{
    protected static string $resource = WarehouseJournalBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->hidden(fn () => $this->record->status === JournalBatchStatus::POSTED),
        ];
    }
}
