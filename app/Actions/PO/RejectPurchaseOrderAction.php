<?php

// app/Actions/PO/RejectPurchaseOrder.php

namespace App\Actions\PO;

use App\Data\PO\PurchaseOrderData;
use App\Data\PO\PurchaseOrderRejectedData;
use App\Data\PO\RejectPurchaseOrderData;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class RejectPurchaseOrderAction
{
    public function execute(
        PurchaseOrder $order,
        RejectPurchaseOrderData $data,
        int $userId
    ): PurchaseOrderRejectedData {
        return DB::transaction(function () use ($order, $data, $userId) {
            // Validate can reject
            if (! in_array($order->status, [PurchaseOrderStatus::PENDING, PurchaseOrderStatus::APPROVED])) {
                throw new \InvalidArgumentException(
                    "Cannot reject order with status: {$order->status->label()}"
                );
            }

            // Update order
            $order->status = PurchaseOrderStatus::CANCELLED;
            $order->save();

            // Create rejection record
            $this->logRejection($order, $data, $userId);

            // Return result data
            return PurchaseOrderRejectedData::fromRejection(
                orderData: PurchaseOrderData::fromModel($order->fresh()),
                userId: $userId,
                reason: $data->rejection_reason,
                suggestedAction: $data->suggested_action
            );
        });
    }

    private function logRejection(
        PurchaseOrder $order,
        RejectPurchaseOrderData $data,
        int $userId
    ): void {
        // Implement rejection logging
        // PurchaseOrderRejection::create([...]);
    }
}
