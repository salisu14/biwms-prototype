<?php

namespace App\Filament\Resources\ItemJournalTemplates\Pages;

use App\Filament\Resources\ItemJournalTemplates\ItemJournalTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditItemJournalTemplate extends EditRecord
{
    protected static string $resource = ItemJournalTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
