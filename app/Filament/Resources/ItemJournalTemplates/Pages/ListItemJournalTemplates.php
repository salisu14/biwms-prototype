<?php

namespace App\Filament\Resources\ItemJournalTemplates\Pages;

use App\Filament\Resources\ItemJournalTemplates\ItemJournalTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListItemJournalTemplates extends ListRecords
{
    protected static string $resource = ItemJournalTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
