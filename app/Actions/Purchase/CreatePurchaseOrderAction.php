<?php

namespace App\Actions\Purchase;

use App\Data\Purchase\CreatePurchaseOrderData;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class CreatePurchaseOrderAction
{
    public function execute(CreatePurchaseOrderData $data): PurchaseOrder
    {
        return DB::transaction(function () use ($data) {

            $order = PurchaseOrder::create([
                'order_type' => $data->orderType,
                'vendor_id' => $data->vendorId,
                'vendor_name' => $data->vendorName,
                'order_date' => $data->orderDate,
                'location_id' => $data->locationId,
                'posting_date' => $data->postingDate,
                'due_date' => $data->dueDate,
                'delivery_date' => $data->deliveryDate,
                'payment_terms' => $data->paymentTerms,
                'comment' => $data->comment,
                'created_by' => $data->createdBy,
            ]);

            foreach ($data->lines as $index => $line) {
                $order->lines()->create([
                    'line_number' => $index + 1,
                    'item_id' => $line->itemId,
                    'description' => $line->description,
                    'quantity' => $line->quantity,
                    'unit_cost' => $line->unitCost,
                    'unit_of_measure' => $line->unitOfMeasure,
                    'vat_percentage' => $line->vatPercentage,
                ]);
            }

            $order->recalculateTotals();

            return $order->load('lines');
        });
    }
}
