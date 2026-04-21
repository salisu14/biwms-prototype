<?php

namespace App\Filament\Resources\GeneralJournalTemplates\Pages;

use App\Filament\Resources\GeneralJournalTemplates\GeneralJournalTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGeneralJournalTemplates extends ListRecords
{
    protected static string $resource = GeneralJournalTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
