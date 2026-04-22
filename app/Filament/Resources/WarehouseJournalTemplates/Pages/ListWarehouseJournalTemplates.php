<?php

namespace App\Filament\Resources\WarehouseJournalTemplates\Pages;

use App\Filament\Resources\WarehouseJournalTemplates\WarehouseJournalTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWarehouseJournalTemplates extends ListRecords
{
    protected static string $resource = WarehouseJournalTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
