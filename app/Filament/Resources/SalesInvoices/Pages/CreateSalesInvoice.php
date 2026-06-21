<?php

namespace App\Filament\Resources\SalesInvoices\Pages;

use App\Data\Sales\SalesInvoiceData;
use App\Filament\Resources\SalesInvoices\SalesInvoiceResource;
use App\Filament\Resources\SalesInvoices\Schemas\SalesInvoiceForm;
use App\Models\SalesOrder;
use App\Services\Sales\SalesInvoiceService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateSalesInvoice extends CreateRecord
{
    protected static string $resource = SalesInvoiceResource::class;

    public function mount(): void
    {
        parent::mount();

        $salesOrderId = request()->integer('sales_order_id');
        if (! $salesOrderId) {
            return;
        }

        /** @var SalesOrder|null $salesOrder */
        $salesOrder = SalesOrder::query()
            ->with('lines')
            ->find($salesOrderId);

        if (! $salesOrder) {
            return;
        }

        $this->form->fill([
            'sales_order_id' => $salesOrder->id,
            'customer_id' => $salesOrder->customer_id,
            'currency_code' => $salesOrder->currency_code ?: 'NGN',
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'lines' => $lines = SalesInvoiceForm::buildLinesFromSalesOrder($salesOrder),
            'total_amount' => number_format(collect($lines)->sum(fn (array $line): float => (float) ($line['line_total'] ?? 0)), 2, '.', ''),
        ]);
    }

    /**
     * Use the service to handle the creation logic, including transactions and lines.
     */
    protected function handleRecordCreation(array $data): Model
    {
        // Map form data to your DTO
        $invoiceData = SalesInvoiceData::from($data);

        return app(SalesInvoiceService::class)->create($invoiceData);
    }
}
