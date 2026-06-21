<?php

namespace App\Filament\Resources\ProductionJournalTemplates\Pages;

use App\Filament\Resources\ProductionJournalTemplates\ProductionJournalTemplateResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProductionJournalTemplate extends ViewRecord
{
    protected static string $resource = ProductionJournalTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
