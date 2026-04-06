<?php

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Data\Sales\SalesInvoiceData;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Services\Sales\SalesInvoiceService;
use Filament\Resources\Pages\CreateRecord;

class CreateSalesInvoice extends CreateRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    /**
     * Use the service to handle the creation logic, including transactions and lines.
     */
    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        // Map form data to your DTO
        $invoiceData = SalesInvoiceData::from($data);

        return app(SalesInvoiceService::class)->create($invoiceData);
    }
}
