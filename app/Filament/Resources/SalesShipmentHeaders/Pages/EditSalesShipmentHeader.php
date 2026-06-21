<?php

namespace App\Filament\Resources\SalesShipmentHeaders\Pages;

use App\Filament\Resources\SalesShipmentHeaders\SalesShipmentHeaderResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSalesShipmentHeader extends EditRecord
{
    protected static string $resource = SalesShipmentHeaderResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();
        $customer = $record->customer?->customer_name ?? $record->sell_to_customer_name ?? 'Unknown Customer';

        return ($record->shipment_no ?? 'Sales Shipment')
            .' • '.$customer
            .' • '.($record->status?->label() ?? $record->status?->value ?? 'Unknown Status');
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();

        return ($record->shipment_date?->format('d/m/Y') ?? 'No shipment date')
            .' • '.($record->location?->name ?? 'Unknown Location')
            .' • '.($record->shipment_method_code ?: 'No method');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();

        return ($record->shipment_no ?? 'Sales Shipment').' - '.($record->customer?->customer_name ?? $record->sell_to_customer_name ?? 'Unknown Customer');
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
