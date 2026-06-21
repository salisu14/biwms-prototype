<?php

namespace App\Filament\Resources\CustomerPriceOverrides\Pages;

use App\Filament\Resources\CustomerPriceOverrides\CustomerPriceOverrideResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Number;

class ViewCustomerPriceOverride extends ViewRecord
{
    protected static string $resource = CustomerPriceOverrideResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();
        $currency = $record->item?->currency_code ?? config('app.default_currency', 'USD');

        return ($record->customer?->customer_number ?? 'Customer Override')
            .' • '.($record->item?->item_code ?? 'Item')
            .' • '.Number::currency((float) $record->override_price, $currency);
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();

        return trim(implode(' • ', array_filter([
            $record->customer?->name ?? 'Unknown Customer',
            $record->item?->description ?? 'Unknown Item',
            'Override '.Number::currency((float) $record->override_price, $record->item?->currency_code ?? config('app.default_currency', 'USD')),
        ])));
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return $record->customer
            ? "{$record->customer->customer_number} - ".($record->item?->item_code ?? 'Item')
            : 'Customer Price Override';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
