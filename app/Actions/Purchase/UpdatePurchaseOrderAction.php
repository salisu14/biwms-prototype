<?php

namespace App\Actions\Purchase;

use App\Data\Purchase\UpdatePurchaseOrderData;
use App\Models\PurchaseOrder;

class UpdatePurchaseOrderAction
{
    public function execute(UpdatePurchaseOrderData $data): PurchaseOrder
    {
        $order = PurchaseOrder::findOrFail($data->purchaseOrderId);

        if (! $order->can_edit) {
            throw new \Exception('Cannot edit this purchase order.');
        }

        $order->update([
            'comment' => $data->comment,
            'payment_terms' => $data->paymentTerms,
            'delivery_date' => $data->deliveryDate,
        ]);

        return $order;
    }
}
