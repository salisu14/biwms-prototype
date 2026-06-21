<?php

namespace App\Filament\Resources\PurchasePrices\Pages;

use App\Filament\Resources\PurchasePrices\PurchasePriceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPurchasePrice extends EditRecord
{
    protected static string $resource = PurchasePriceResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();

        return ($record->vendor?->vendor_code ?? 'Purchase Price')
            .' • Scope '.($record->item?->item_code ?? '—')
            .' • Attribute '.number_format((float) $record->direct_unit_cost, 2);
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();

        return ($record->vendor?->vendor_name ?? 'Unknown Vendor')
            .' • '.($record->item?->description ?? 'Unknown Item')
            .' • '.($record->unit_of_measure_code ?? 'Base UoM');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();
        $itemCode = $record->item?->item_code ?? 'Item';

        return $record->vendor
            ? "{$record->vendor->vendor_code} - {$itemCode}"
            : 'Purchase Price';
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
