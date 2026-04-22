<?php

namespace App\Filament\Resources\ProductionJournalBatches\Pages;

use App\Filament\Resources\ProductionJournalBatches\ProductionJournalBatchResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProductionJournalBatch extends EditRecord
{
    protected static string $resource = ProductionJournalBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
