<?php

namespace App\Filament\Resources\ItemLots\Pages;

use App\Filament\Resources\ItemLots\ItemLotResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditItemLot extends EditRecord
{
    protected static string $resource = ItemLotResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();

        return $record->lot_number ?: 'Edit Item Lot';
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
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
