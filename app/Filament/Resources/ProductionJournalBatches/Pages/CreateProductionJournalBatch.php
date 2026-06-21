<?php

namespace App\Filament\Resources\ProductionJournalBatches\Pages;

use App\Filament\Resources\ProductionJournalBatches\ProductionJournalBatchResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductionJournalBatch extends CreateRecord
{
    protected static string $resource = ProductionJournalBatchResource::class;
}
