<?php

namespace App\Filament\Resources\ProductionJournalTemplates\Pages;

use App\Filament\Resources\ProductionJournalTemplates\ProductionJournalTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProductionJournalTemplate extends EditRecord
{
    protected static string $resource = ProductionJournalTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
