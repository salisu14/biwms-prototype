<?php

namespace App\Filament\Resources\PhysicalInventoryJournals\Pages;

use App\Filament\Resources\PhysicalInventoryJournals\PhysicalInventoryJournalResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditPhysicalInventoryJournal extends EditRecord
{
    protected static string $resource = PhysicalInventoryJournalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->visible(fn ($record) => $record->status === 'Open'),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
