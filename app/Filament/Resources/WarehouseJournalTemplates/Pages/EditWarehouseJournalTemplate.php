<?php

namespace App\Filament\Resources\WarehouseJournalTemplates\Pages;

use App\Filament\Resources\WarehouseJournalTemplates\WarehouseJournalTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWarehouseJournalTemplate extends EditRecord
{
    protected static string $resource = WarehouseJournalTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
