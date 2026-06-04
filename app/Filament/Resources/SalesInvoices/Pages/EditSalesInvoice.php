<?php

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Number;

class EditSalesInvoice extends EditRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    public function getHeading(): string
    {
        $record = $this->getRecord();
        $customer = $record->customer?->customer_name ?? $record->customer?->name ?? 'Unknown Customer';
        $amount = Number::currency((float) $record->total_amount, $record->currency_code ?: config('app.default_currency', 'USD'));

        return ($record->invoice_number ?? 'Sales Invoice')
            .' • '.$customer
            .' • '.$amount;
    }

    public function getSubheading(): string
    {
        $record = $this->getRecord();
        $location = $record->location?->code
            ? "{$record->location->code} - {$record->location->name}"
            : ($record->location?->name ?? 'Unknown Location');

        return ($record->salesOrder?->order_number ?: 'No sales order')
            .' • '.$location
            .' • '.number_format((float) $record->total_amount, 2).' '.($record->currency_code ?: '');
    }

    public function getBreadcrumb(): string
    {
        $record = $this->getRecord();
        $customer = $record->customer?->customer_name ?? $record->customer?->name ?? 'Unknown Customer';

        return ($record->invoice_number ?? 'Sales Invoice').' - '.$customer;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
