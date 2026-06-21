<?php

// app/Filament/Resources/PhysicalInventoryJournalResource/Pages/ViewPhysicalInventoryJournal.php

namespace App\Filament\Resources\PhysicalInventoryJournals\Pages;

use App\Filament\Resources\PhysicalInventoryJournals\PhysicalInventoryJournalResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewPhysicalInventoryJournal extends ViewRecord
{
    protected static string $resource = PhysicalInventoryJournalResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->visible(fn ($record) => $record->status !== 'Posted'),
            Actions\Action::make('print')
                ->label('Print Counting Sheet')
                ->icon('heroicon-o-printer')
                ->url(fn ($record) => route('physical-inventory.print', ['journal' => $record]))
                ->openUrlInNewTab(),
        ];
    }
}
