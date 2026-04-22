<?php

namespace App\Filament\Resources\RecurringJournalTemplates\Pages;

use App\Filament\Resources\RecurringJournalTemplates\RecurringJournalTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRecurringJournalTemplate extends EditRecord
{
    protected static string $resource = RecurringJournalTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
