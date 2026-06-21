<?php

namespace App\Actions\Purchase;

use App\Data\Purchase\CreateReceiptData;
use App\Models\PurchaseOrder;
use App\Models\WarehouseReceipt;
use Illuminate\Support\Facades\DB;

class CreateWarehouseReceiptAction
{
    public function execute(CreateReceiptData $data): WarehouseReceipt
    {
        $order = PurchaseOrder::with('lines')->findOrFail($data->purchaseOrderId);

        if (! $order->can_receive) {
            throw new \Exception('Purchase Order cannot be received.');
        }

        return DB::transaction(function () use ($order, $data) {

            $receipt = WarehouseReceipt::create([
                'document_number' => WarehouseReceipt::generateNumber(),
                'location_id' => $order->location_id,
                'source_document' => 'PURCHASE_ORDER',
                'source_document_id' => $order->id,
                'source_document_number' => $order->order_number,
                'vendor_id' => $order->vendor_id,
                'status' => 'OPEN',
                'assigned_user_id' => $data->userId,
                'receipt_date' => now(),
                'expected_receipt_date' => $order->delivery_date,
            ]);

            foreach ($order->lines->where('remaining_quantity', '>', 0) as $line) {
                $receipt->lines()->create([
                    'line_number' => $line->line_number,
                    'item_id' => $line->item_id,
                    'description' => $line->description,
                    'quantity' => $line->remaining_quantity,
                    'unit_of_measure_code' => $line->unit_of_measure,
                    'source_line_id' => $line->id,
                ]);
            }

            $order->update(['status' => 'PARTIALLY_RECEIVED']);

            return $receipt;
        });
    }
}
