<?php

namespace App\Filament\Resources\ProductionJournalTemplates\Pages;

use App\Filament\Resources\ProductionJournalTemplates\ProductionJournalTemplateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductionJournalTemplates extends ListRecords
{
    protected static string $resource = ProductionJournalTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
