<?php

namespace App\Filament\Resources\ProductionJournalBatches\Pages;

use App\Filament\Resources\ProductionJournalBatches\ProductionJournalBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductionJournalBatches extends ListRecords
{
    protected static string $resource = ProductionJournalBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
