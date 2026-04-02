<?php

namespace App\Actions\Purchase;

use App\Data\Purchase\CancelPurchaseOrderData;
use App\Models\PurchaseOrder;

class CancelPurchaseOrderAction
{
    public function execute(CancelPurchaseOrderData $data): PurchaseOrder
    {
        $order = PurchaseOrder::findOrFail($data->purchaseOrderId);

        $order->cancel();

        return $order;
    }
}
