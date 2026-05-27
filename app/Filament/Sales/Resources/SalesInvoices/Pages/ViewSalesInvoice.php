<?php

namespace App\Filament\Sales\Resources\SalesInvoices\Pages;

use App\Filament\Sales\Resources\SalesInvoices\SalesInvoiceResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSalesInvoice extends ViewRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
