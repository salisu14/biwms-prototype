<?php

namespace App\Services\Print;

use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Services\Company\CompanyInformationService;
use Barryvdh\DomPDF\Facade\Pdf;

class ProformaInvoiceService
{
    public function __construct(
        private readonly CompanyInformationService $companyInformationService
    ) {}

    /**
     * Generate a Proforma Invoice PDF for a Sales Order.
     */
    public function generateSalesProforma(SalesOrder $order)
    {
        $data = [
            'type' => 'Sales',
            'title' => 'PROFORMA SALES INVOICE',
            'order_number' => $order->order_number,
            'client_label' => 'Customer',
            'client_name' => $order->customer_name ?? $order->customer?->name,
            'client_address' => $order->customer_address ?? $order->customer?->address,
            'date' => $order->order_date?->format('d/m/Y'),
            'currency' => $order->currency_code ?? 'USD',
            'lines' => $order->lines->map(function ($line) {
                return [
                    'item_code' => $line->item_code,
                    'description' => $line->description,
                    'qty' => (float) $line->quantity,
                    'uom' => $line->unit_of_measure_code,
                    'price' => (float) $line->unit_price,
                    'discount_pct' => (float) $line->line_discount_percent,
                    'discount_amount' => (float) $line->line_discount_amount,
                    'vat_amount' => (float) $line->vat_amount,
                    'net_amount' => (float) $line->amount_including_vat,
                ];
            }),
            'totals' => [
                'subtotal' => (float) $order->total_amount,
                'discount' => (float) $order->line_discount_total,
                'vat' => (float) $order->total_vat,
                'grand_total' => (float) $order->grand_total,
                'total_qty' => (float) $order->lines->sum('quantity'),
                'total_qty_display' => $this->formatTotalQuantityByUom($order->lines, 'unit_of_measure_code'),
            ],
            'company' => $this->getCompanyInfo(),
        ];

        return Pdf::loadView('pdf.proforma-invoice', $data)
            ->setPaper('a4', 'portrait');
    }

    /**
     * Generate a Purchase Order (PO) PDF for a Purchase Order.
     */
    public function generatePurchaseProforma(PurchaseOrder $order)
    {
        $data = [
            'type' => 'Purchase',
            'title' => 'PURCHASE ORDER (PO)',
            'order_number' => $order->order_number,
            'client_label' => 'Vendor',
            'client_name' => $order->vendor_name ?? $order->vendor?->name,
            'client_address' => $order->vendor?->address,
            'date' => $order->order_date?->format('d/m/Y'),
            'currency' => $order->currency_code ?? $order->vendor?->currency ?? 'USD',
            'lines' => $order->lines->map(function ($line) {
                return [
                    'item_code' => $line->item_code,
                    'description' => $line->description,
                    'qty' => (float) $line->quantity,
                    'uom' => $line->unit_of_measure,
                    'price' => (float) $line->unit_cost,
                    'discount_pct' => 0, // Purchase lines in this schema don't have explicit disc %
                    'discount_amount' => 0,
                    'vat_amount' => (float) $line->vat_amount,
                    'net_amount' => (float) $line->total_amount,
                ];
            }),
            'totals' => [
                'subtotal' => (float) $order->total_amount,
                'discount' => 0,
                'vat' => (float) $order->total_vat,
                'grand_total' => (float) $order->grand_total,
                'total_qty' => (float) $order->lines->sum('quantity'),
                'total_qty_display' => $this->formatTotalQuantityByUom($order->lines, 'unit_of_measure'),
            ],
            'company' => $this->getCompanyInfo(),
        ];

        return Pdf::loadView('pdf.proforma-invoice', $data)
            ->setPaper('a4', 'portrait');
    }

    /**
     * Get Company Information (Fallback search)
     */
    protected function getCompanyInfo(): array
    {
        $header = $this->companyInformationService->getReportHeader();

        return [
            'name' => $header['name'] ?? config('app.name', 'Bifli WMS'),
            'trading_name' => $header['trading_name'] ?? null,
            'address' => implode(', ', $header['address_lines'] ?? []),
            'address_lines' => $header['address_lines'] ?? [],
            'email' => $header['email'] ?? null,
            'phone' => $header['phone'] ?? null,
            'website' => $header['website'] ?? null,
            'tax_no' => $header['tax_no'] ?? null,
            'registration_no' => $header['registration_no'] ?? null,
            'logo_url' => $header['logo_url'] ?? null,
            'logo_data_uri' => $header['logo_data_uri'] ?? null,
            'logo_abs_path' => $header['logo_abs_path'] ?? null,
            'invoice_footer' => $this->companyInformationService->getInvoiceFooter(),
        ];
    }

    protected function formatTotalQuantityByUom($lines, string $uomField): string
    {
        $grouped = $lines
            ->groupBy(fn ($line) => $line->{$uomField} ?: 'N/A')
            ->map(function ($uomLines, $uomCode) {
                $totalQty = (float) $uomLines->sum('quantity');

                return number_format($totalQty, 2).' '.$uomCode;
            })
            ->values()
            ->implode(' + ');

        return $grouped !== '' ? $grouped : '0.00';
    }
}
