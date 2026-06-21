<?php

namespace App\Filament\Resources\VendorItems\Pages;

use App\Filament\Resources\VendorItems\VendorItemResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVendorItem extends ViewRecord
{
    protected static string $resource = VendorItemResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();

        return ($record->vendor?->vendor_code ?? 'Vendor Item')
            .' • '.($record->item?->item_code ?? 'Item')
            .' • '.number_format((float) $record->unit_cost, 2);
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();

        return trim(implode(' • ', array_filter([
            $record->vendor?->vendor_name,
            $record->item?->description,
            'Lead '.($record->lead_time_days ?? 0).' days',
        ])));
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return $record->vendor?->vendor_code
            ? "{$record->vendor->vendor_code} - ".($record->item?->item_code ?? 'Item')
            : 'Vendor Item';
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
