<?php

namespace App\Filament\Resources\VendorInvoices\Pages;

use App\Filament\Resources\VendorInvoices\VendorInvoiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVendorInvoices extends ListRecords
{
    protected static string $resource = VendorInvoiceResource::class;

    protected static ?string $title = 'Vendor Invoices';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
