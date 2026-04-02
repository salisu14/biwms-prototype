<?php

namespace App\Actions\Purchase;

use App\Data\Purchase\PostInvoiceData;
use App\Models\PostedPurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Services\PostingService;
use Illuminate\Support\Facades\DB;

class PostPurchaseInvoiceAction
{
    public function execute(PostInvoiceData $data): PostedPurchaseInvoice
    {
        $order = PurchaseOrder::with(['lines', 'vendor'])->findOrFail($data->purchaseOrderId);

        return DB::transaction(function () use ($order, $data) {

            $invoice = PostedPurchaseInvoice::create([
                'document_number' => $data->documentNumber ?? PostedPurchaseInvoice::generateNumber(),
                'order_id' => $order->id,
                'vendor_id' => $order->vendor_id,
                'posting_date' => $data->postingDate,
                'general_business_posting_group_id' => $order->general_business_posting_group_id,
                'vendor_posting_group_id' => $order->vendor_posting_group_id,
                'vat_bus_posting_group' => $order->vat_bus_posting_group,
            ]);

            $postingService = app(PostingService::class);

            $totalAmount = 0;
            $totalVat = 0;

            foreach ($data->lines as $lineData) {

                $poLine = $order->lines->firstWhere('id', $lineData['poLineId']);

                $lineTotal = $lineData['quantity'] * $poLine->unit_cost;
                $vat = $lineTotal * ($poLine->vat_percentage / 100);

                $invoice->lines()->create([
                    'po_line_id' => $poLine->id,
                    'quantity' => $lineData['quantity'],
                    'unit_cost' => $poLine->unit_cost,
                    'unit_of_measure' => $poLine->unit_of_measure,
                    'line_total' => $lineTotal,
                    'vat_amount' => $vat,
                    'total_amount' => $lineTotal + $vat,
                ]);

                $postingService->postPurchaseLine(
                    vendor: $order->vendor,
                    item: $poLine->item,
                    quantity: $lineData['quantity'],
                    unitCost: $poLine->unit_cost,
                    lineTotal: $lineTotal,
                    postingDate: $data->postingDate,
                    documentNumber: $invoice->document_number,
                    description: $poLine->description
                );

                $totalAmount += $lineTotal;
                $totalVat += $vat;
            }

            $postingService->postVendorPayable(
                vendor: $order->vendor,
                amount: $totalAmount + $totalVat,
                postingDate: $data->postingDate,
                documentNumber: $invoice->document_number
            );

            $invoice->update([
                'total_amount' => $totalAmount,
                'total_vat' => $totalVat,
                'grand_total' => $totalAmount + $totalVat,
            ]);

            return $invoice;
        });
    }
}
