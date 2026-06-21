<?php

namespace App\Services\Print;

use App\Models\PostedSalesInvoice;
use App\Services\Company\CompanyInformationService;
use Barryvdh\DomPDF\Facade\Pdf;

class PostedSalesInvoicePrintService
{
    public function __construct(
        private readonly CompanyInformationService $companyInformationService
    ) {}

    public function generateTaxInvoice(PostedSalesInvoice $invoice)
    {
        $invoice->loadMissing(['lines', 'customer']);
        $header = $this->companyInformationService->getReportHeader();

        $data = [
            'title' => 'TAX INVOICE',
            'invoice_number' => $invoice->document_number,
            'order_number' => $invoice->order_number,
            'customer_name' => $invoice->customer_name ?: $invoice->customer?->name,
            'customer_address' => $invoice->customer_address ?: $invoice->customer?->address,
            'posting_date' => $invoice->posting_date?->format('d/m/Y'),
            'document_date' => $invoice->document_date?->format('d/m/Y'),
            'currency' => $invoice->currency_code ?: 'NGN',
            'lines' => $invoice->lines->map(fn ($line): array => [
                'item_code' => $line->item_code,
                'description' => $line->description,
                'qty' => (float) $line->quantity,
                'uom' => $line->unit_of_measure_code,
                'unit_price' => (float) $line->unit_price,
                'discount_amount' => (float) $line->line_discount_amount,
                'vat_amount' => (float) $line->vat_amount,
                'line_total' => (float) $line->amount_including_vat,
            ]),
            'totals' => [
                'subtotal' => (float) $invoice->total_amount,
                'discount' => (float) $invoice->line_discount_total,
                'vat' => (float) $invoice->total_vat,
                'grand_total' => (float) $invoice->grand_total,
            ],
            'company' => [
                'name' => $header['name'] ?? config('app.name', 'Bifli WMS'),
                'address' => implode(', ', $header['address_lines'] ?? []),
                'address_lines' => $header['address_lines'] ?? [],
                'email' => $header['email'] ?? null,
                'phone' => $header['phone'] ?? null,
                'website' => $header['website'] ?? null,
                'tax_no' => $header['tax_no'] ?? null,
                'registration_no' => $header['registration_no'] ?? null,
                'logo_url' => $header['logo_url'] ?? null,
                'invoice_footer' => $this->companyInformationService->getInvoiceFooter(),
            ],
        ];

        return Pdf::loadView('pdf.sales-tax-invoice', $data)->setPaper('a4', 'portrait');
    }
}
