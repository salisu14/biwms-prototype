<?php

namespace App\Filament\Resources\ItemLedgerEntries\Pages;

use App\Filament\Resources\ItemLedgerEntries\ItemLedgerEntryResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditItemLedgerEntry extends EditRecord
{
    protected static string $resource = ItemLedgerEntryResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();

        return "Edit #{$record->entry_number}";
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
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
