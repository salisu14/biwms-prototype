<?php

namespace App\Filament\Resources\FAJournalTemplates\Pages;

use App\Filament\Resources\FAJournalTemplates\FAJournalTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditFAJournalTemplate extends EditRecord
{
    protected static string $resource = FAJournalTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
