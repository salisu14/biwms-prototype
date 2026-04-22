<?php

namespace App\Filament\Resources\ProductionJournalBatches\Pages;

use App\Filament\Resources\ProductionJournalBatches\ProductionJournalBatchResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProductionJournalBatch extends ViewRecord
{
    protected static string $resource = ProductionJournalBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
