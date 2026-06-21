<?php

namespace App\Filament\Resources\ItemLedgerEntries\Pages;

use App\Filament\Resources\ItemLedgerEntries\ItemLedgerEntryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItemLedgerEntry extends ViewRecord
{
    protected static string $resource = ItemLedgerEntryResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();
        $itemCode = $record->item?->item_code ?? 'Item';
        $locationCode = $record->location?->code ?? 'Location';
        $entryType = $record->entry_type?->label() ?? (string) $record->entry_type;

        return "#{$record->entry_number}"
            .' • Scope '.$itemCode
            .' • Attribute '.$entryType;
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();
        $itemCode = $record->item?->item_code ?? 'Item';
        $locationCode = $record->location?->code
            ? "{$record->location->code} - {$record->location->name}"
            : ($record->location?->name ?? 'Location');

        return "{$itemCode} • {$locationCode} • ".($record->open ? 'Open' : 'Closed');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return "#{$record->entry_number}".($record->item?->item_code ? ' - '.$record->item->item_code : '');
    }

    protected function getHeaderActions(): array
    {
        return [
//            EditAction::make(),
        ];
    }
}
