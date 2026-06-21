<?php

namespace App\Actions\Purchase;

use App\Data\Purchase\ApprovePurchaseOrderData;
use App\Models\PurchaseOrder;

class ApprovePurchaseOrderAction
{
    public function execute(ApprovePurchaseOrderData $data): PurchaseOrder
    {
        $order = PurchaseOrder::findOrFail($data->purchaseOrderId);

        $order->approve($data->approvedBy);

        return $order;
    }
}
