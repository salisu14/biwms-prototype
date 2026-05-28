<?php

namespace App\Filament\Resources\JournalLines\Pages;

use App\Filament\Resources\JournalLines\JournalLineResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewJournalLine extends ViewRecord
{
    protected static string $resource = JournalLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
