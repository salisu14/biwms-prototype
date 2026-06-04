<?php

namespace App\Filament\Resources\ItemLots\Pages;

use App\Filament\Resources\ItemLots\ItemLotResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewItemLot extends ViewRecord
{
    protected static string $resource = ItemLotResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();

        return $record->lot_number ?: 'Item Lot';
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();
        $itemCode = $record->item?->item_code ?? 'Item';

        return "{$itemCode} • {$record->status}";
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return $record->lot_number ?: 'Item Lot';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
