<?php

namespace App\Actions\Purchase;

use App\Data\Purchase\PostInvoiceData;
use App\Enums\PurchaseLineType;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Services\PostingService;
use Illuminate\Support\Facades\DB;

class PostPurchaseInvoiceAction
{
    public function execute(PostInvoiceData $data): PurchaseInvoice
    {
        $order = PurchaseOrder::with(['lines', 'vendor'])->findOrFail($data->purchaseOrderId);

        return DB::transaction(function () use ($order, $data) {

            $invoice = PurchaseInvoice::create([
                'document_number' => $data->documentNumber ?? PurchaseInvoice::generateNumber(),
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
                    'type' => $poLine->type,
                    'asset_id' => $poLine->asset_id,
                    'quantity' => $lineData['quantity'],
                    'item_id' => $poLine->item_id,
                    'item_code' => $poLine->item_code,
                    'item_description' => $poLine->description,
                    'unit_cost' => $poLine->unit_cost,
                    'unit_of_measure_code' => $poLine->unit_of_measure,
                    'line_total' => $lineTotal,
                    'vat_amount' => $vat,
                    'total_amount' => $lineTotal + $vat,
                    'general_product_posting_group_id' => $poLine->general_product_posting_group_id,
                    'line_number' => $poLine->line_number,
                    'po_line_number' => $poLine->line_number,
                    'quantity_base' => $lineData['quantity'], // Simplified
                    'unit_cost_lcy' => $poLine->unit_cost, // Simplified
                    'amount_including_vat' => $lineTotal + $vat,
                    'amount_including_vat_lcy' => $lineTotal + $vat, // Simplified
                ]);

                if ($poLine->type === PurchaseLineType::FIXED_ASSET) {
                    $postingService->postFixedAssetPurchase(
                        vendor: $order->vendor,
                        asset: $poLine->asset,
                        quantity: $lineData['quantity'],
                        unitCost: $poLine->unit_cost,
                        lineTotal: $lineTotal,
                        postingDate: $data->postingDate,
                        documentNumber: $invoice->document_number,
                        description: $poLine->description
                    );
                } else {
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
                }

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
