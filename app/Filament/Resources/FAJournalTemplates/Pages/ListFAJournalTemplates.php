<?php

namespace App\Filament\Resources\FAJournalTemplates\Pages;

use App\Filament\Resources\FAJournalTemplates\FAJournalTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFAJournalTemplates extends ListRecords
{
    protected static string $resource = FAJournalTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
