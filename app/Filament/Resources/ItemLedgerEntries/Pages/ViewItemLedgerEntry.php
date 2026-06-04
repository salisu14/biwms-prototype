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

        return "#{$record->entry_number}";
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();
        $itemCode = $record->item?->item_code ?? 'Item';
        $locationCode = $record->location?->code ?? 'Location';

        return "{$itemCode} • {$locationCode} • {$record->entry_type->value}";
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return "#{$record->entry_number}";
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
