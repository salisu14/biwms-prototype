<?php

namespace App\Actions\Purchase;

use App\Data\Purchase\RecalculatePurchaseOrderTotalsData;
use App\Models\PurchaseOrder;

class RecalculatePurchaseOrderTotalsAction
{
    public function execute(RecalculatePurchaseOrderTotalsData $data): void
    {
        $order = PurchaseOrder::findOrFail($data->purchaseOrderId);

        $order->recalculateTotals();
    }
}
