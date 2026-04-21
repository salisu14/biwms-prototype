<?php

namespace App\Filament\Resources\FAJournalTemplates\Pages;

use App\Filament\Resources\FAJournalTemplates\FAJournalTemplateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewFAJournalTemplate extends ViewRecord
{
    protected static string $resource = FAJournalTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
