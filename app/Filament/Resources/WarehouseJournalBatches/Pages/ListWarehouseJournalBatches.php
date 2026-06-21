<?php

namespace App\Filament\Resources\WarehouseJournalBatches\Pages;

use App\Filament\Resources\WarehouseJournalBatches\WarehouseJournalBatchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWarehouseJournalBatches extends ListRecords
{
    protected static string $resource = WarehouseJournalBatchResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('New Batch')];
    }
}
