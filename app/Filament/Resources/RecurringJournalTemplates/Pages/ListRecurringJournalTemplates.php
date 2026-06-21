<?php

namespace App\Filament\Resources\RecurringJournalTemplates\Pages;

use App\Filament\Resources\RecurringJournalTemplates\RecurringJournalTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecurringJournalTemplates extends ListRecords
{
    protected static string $resource = RecurringJournalTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
