<?php

namespace App\Actions\Purchase;

use App\Data\Purchase\ClosePurchaseOrderData;
use App\Models\PurchaseOrder;

class ClosePurchaseOrderAction
{
    public function execute(ClosePurchaseOrderData $data): PurchaseOrder
    {
        $order = PurchaseOrder::findOrFail($data->purchaseOrderId);

        $order->close();

        return $order;
    }
}
