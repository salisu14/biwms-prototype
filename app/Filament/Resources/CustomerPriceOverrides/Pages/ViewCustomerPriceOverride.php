<?php

namespace App\Filament\Resources\CustomerPriceOverrides\Pages;

use App\Filament\Resources\CustomerPriceOverrides\CustomerPriceOverrideResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCustomerPriceOverride extends ViewRecord
{
    protected static string $resource = CustomerPriceOverrideResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();

        return ($record->customer?->customer_number ?? 'Customer Price Override')
            .' • Scope '.($record->item?->item_code ?? '—')
            .' • Attribute '.number_format((float) $record->override_price, 2);
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();

        return ($record->customer?->name ?? 'Unknown Customer')
            .' • '.($record->item?->description ?? 'Unknown Item')
            .' • Override '.number_format((float) $record->override_price, 2);
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();
        $itemCode = $record->item?->item_code ?? 'Item';

        return $record->customer
            ? "{$record->customer->customer_number} - {$itemCode}"
            : 'Customer Price Override';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
