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

        return $record->sku_code ?: 'Edit Item SKU';
    }

    public function getSubheading(): ?string
    {
        $record = $this->getRecord();
        $itemCode = $record->item?->item_code ?? 'Item';
        $locationCode = $record->location?->code ?? 'Location';

        return "{$itemCode} • {$locationCode}";
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return $record->sku_code ?: 'Item SKU';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
