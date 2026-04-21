<?php

namespace App\Filament\Resources\ItemJournalTemplates\Pages;

use App\Filament\Resources\ItemJournalTemplates\ItemJournalTemplateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItemJournalTemplate extends ViewRecord
{
    protected static string $resource = ItemJournalTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
