<?php

namespace App\Filament\Resources\GeneralJournalTemplates\Pages;

use App\Filament\Resources\GeneralJournalTemplates\GeneralJournalTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGeneralJournalTemplate extends EditRecord
{
    protected static string $resource = GeneralJournalTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
