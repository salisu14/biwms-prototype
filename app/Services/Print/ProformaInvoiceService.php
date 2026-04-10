<?php

namespace App\Services\Print;

use App\Models\SalesOrder;
use App\Models\PurchaseOrder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\View;

class ProformaInvoiceService
{
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
                    'qty' => (float)$line->quantity,
                    'uom' => $line->unit_of_measure_code,
                    'price' => (float)$line->unit_price,
                    'discount_pct' => (float)$line->line_discount_percent,
                    'discount_amount' => (float)$line->line_discount_amount,
                    'vat_amount' => (float)$line->vat_amount,
                    'net_amount' => (float)$line->amount_including_vat,
                ];
            }),
            'totals' => [
                'subtotal' => (float)$order->total_amount,
                'discount' => (float)$order->line_discount_total,
                'vat' => (float)$order->total_vat,
                'grand_total' => (float)$order->grand_total,
                'total_qty' => (float)$order->lines->sum('quantity'),
            ],
            'company' => $this->getCompanyInfo(),
        ];

        return Pdf::loadView('pdf.proforma-invoice', $data)
            ->setPaper('a4', 'portrait');
    }

    /**
     * Generate a Proforma Invoice PDF for a Purchase Order.
     */
    public function generatePurchaseProforma(PurchaseOrder $order)
    {
        $data = [
            'type' => 'Purchase',
            'title' => 'PROFORMA PURCHASE INVOICE',
            'order_number' => $order->order_number,
            'client_label' => 'Vendor',
            'client_name' => $order->vendor_name ?? $order->vendor?->name,
            'client_address' => $order->vendor?->address,
            'date' => $order->order_date?->format('d/m/Y'),
            'currency' => 'USD', // Purchase base usually USD in this system
            'lines' => $order->lines->map(function ($line) {
                return [
                    'item_code' => $line->item_code,
                    'description' => $line->description,
                    'qty' => (float)$line->quantity,
                    'uom' => $line->unit_of_measure,
                    'price' => (float)$line->unit_cost,
                    'discount_pct' => 0, // Purchase lines in this schema don't have explicit disc %
                    'discount_amount' => 0,
                    'vat_amount' => (float)$line->vat_amount,
                    'net_amount' => (float)$line->total_amount,
                ];
            }),
            'totals' => [
                'subtotal' => (float)$order->total_amount,
                'discount' => 0,
                'vat' => (float)$order->total_vat,
                'grand_total' => (float)$order->grand_total,
                'total_qty' => (float)$order->lines->sum('quantity'),
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
        // Try to dynamic find company info or fallback to config
        return [
            'name' => config('app.name', 'Bifli WMS'),
            'address' => 'Factory Road, Lagos, Nigeria',
            'email' => 'sales@bifli.com',
            'phone' => '+234 800 000 0000',
        ];
    }
}
