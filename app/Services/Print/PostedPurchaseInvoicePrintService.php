<?php

namespace App\Services\Print;

use App\Models\PaymentApplication;
use App\Models\PostedPurchaseInvoice;
use App\Services\Company\CompanyInformationService;
use Barryvdh\DomPDF\Facade\Pdf;

class PostedPurchaseInvoicePrintService
{
    public function __construct(
        private readonly CompanyInformationService $companyInformationService
    ) {}

    public function generatePurchaseInvoice(PostedPurchaseInvoice $invoice)
    {
        $invoice->loadMissing(['lines', 'vendor']);
        $header = $this->companyInformationService->getReportHeader();
        $applications = PaymentApplication::query()
            ->active()
            ->forDocument('PURCHASE_INVOICE', $invoice->id)
            ->with('payment')
            ->latest('applied_at')
            ->get()
            ->map(fn (PaymentApplication $application): array => [
                'applied_at' => optional($application->applied_at)->format('d/m/Y H:i'),
                'payment_number' => $application->payment?->payment_number,
                'reference' => $application->payment?->external_reference ?: $application->payment?->memo,
                'amount_applied' => (float) $application->amount_applied,
                'document_remaining_after' => (float) $application->document_remaining_after,
            ]);

        $data = [
            'title' => 'PURCHASE INVOICE',
            'invoice_number' => $invoice->document_number,
            'order_number' => $invoice->order_number,
            'vendor_name' => $invoice->vendor_name ?: $invoice->vendor?->vendor_name,
            'vendor_address' => $invoice->vendor_address ?: $invoice->vendor?->address,
            'posting_date' => $invoice->posting_date?->format('d/m/Y'),
            'document_date' => $invoice->document_date?->format('d/m/Y'),
            'currency' => $invoice->currency_code ?: 'NGN',
            'lines' => $invoice->lines->map(fn ($line): array => [
                'item_code' => $line->item_code,
                'description' => $line->item_description,
                'qty' => (float) $line->quantity,
                'uom' => $line->unit_of_measure_code,
                'unit_cost' => (float) $line->unit_cost,
                'vat_amount' => (float) $line->vat_amount,
                'line_total' => (float) $line->amount_including_vat,
            ]),
            'totals' => [
                'subtotal' => (float) $invoice->total_amount,
                'vat' => (float) $invoice->total_vat,
                'grand_total' => (float) $invoice->grand_total,
            ],
            'applications' => $applications,
            'company' => [
                'name' => $header['name'] ?? config('app.name', 'Bifli WMS'),
                'address_lines' => $header['address_lines'] ?? [],
                'email' => $header['email'] ?? null,
                'phone' => $header['phone'] ?? null,
                'website' => $header['website'] ?? null,
                'tax_no' => $header['tax_no'] ?? null,
                'logo_data_uri' => $header['logo_data_uri'] ?? null,
                'invoice_footer' => $this->companyInformationService->getInvoiceFooter(),
            ],
        ];

        return Pdf::loadView('pdf.purchase-invoice', $data)->setPaper('a4', 'portrait');
    }
}
