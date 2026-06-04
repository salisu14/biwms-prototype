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
        $itemCode = $record->item?->item_code ?? 'Item';
        $status = $record->status ?: 'Unknown Status';

        return ($record->lot_number ?: 'Item Lot')
            .' • Scope '.$itemCode
            .' • Attribute '.$status;
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();
        $itemCode = $record->item?->item_code ?? 'Item';
        $expiry = $record->expiry_date?->format('d/m/Y') ?? 'No expiry';

        return "{$itemCode} • Expiry {$expiry} • Qty Remaining ".number_format((float) $record->quantity_remaining, 2);
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return ($record->lot_number ?: 'Item Lot').($record->item?->item_code ? ' - '.$record->item->item_code : '');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
