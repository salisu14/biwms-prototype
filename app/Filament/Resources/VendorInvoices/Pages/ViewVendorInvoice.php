<?php

namespace App\Filament\Resources\VendorInvoices\Pages;

use App\Filament\Resources\VendorInvoices\VendorInvoiceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewVendorInvoice extends ViewRecord
{
    protected static string $resource = VendorInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
