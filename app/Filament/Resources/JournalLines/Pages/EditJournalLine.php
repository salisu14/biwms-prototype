<?php

namespace App\Filament\Resources\JournalLines\Pages;

use App\Filament\Resources\JournalLines\JournalLineResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditJournalLine extends EditRecord
{
    protected static string $resource = JournalLineResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
