<?php

namespace App\Filament\Resources\ItemSkus\Pages;

use App\Filament\Resources\ItemSkus\ItemSkuResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditItemSku extends EditRecord
{
    protected static string $resource = ItemSkuResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();
        $itemCode = $record->item?->item_code ?? 'Item';
        $locationCode = $record->location?->code ?? 'Location';

        return ($record->sku_code ?: 'Item SKU')
            .' • Scope '.$itemCode
            .' • Attribute '.$locationCode;
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();
        $itemCode = $record->item?->item_code ?? 'Item';
        $locationCode = $record->location?->code
            ? "{$record->location->code} - {$record->location->name}"
            : ($record->location?->name ?? 'Location');

        return "{$itemCode} • {$locationCode} • ".($record->is_active ? 'Active' : 'Inactive');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return ($record->sku_code ?: 'Item SKU').($record->item?->item_code ? ' - '.$record->item->item_code : '');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
