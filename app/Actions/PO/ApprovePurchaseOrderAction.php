<?php
// app/Actions/PO/ApprovePurchaseOrder.php

namespace App\Actions\PO;

use App\Data\PO\ApprovePurchaseOrderData;
use App\Data\PO\PurchaseOrderApprovedData;
use App\Data\PO\PurchaseOrderData;
use App\Enums\PurchaseOrderStatus;
use App\Models\PurchaseOrder;
use Illuminate\Support\Facades\DB;

class ApprovePurchaseOrderAction
{
    public function execute(
        PurchaseOrder $order,
        ApprovePurchaseOrderData $data,
        int $userId
    ): PurchaseOrderApprovedData {
        return DB::transaction(function () use ($order, $data, $userId) {
            // Validate can approve
            if (!$order->status->canEdit()) {
                throw new \InvalidArgumentException(
                    "Cannot approve order with status: {$order->status->label()}"
                );
            }

            // Update order
            $order->status = PurchaseOrderStatus::APPROVED;
            $order->approved_by = $userId;
            $order->approved_at = now();

            // Store approval notes if provided (add column or use audit log)
            // $order->approval_notes = $data->approval_notes;

            $order->save();

            // Create audit log entry
            $this->logApproval($order, $data, $userId);

            // Return result data
            return PurchaseOrderApprovedData::fromApproval(
                orderData: PurchaseOrderData::fromModel($order->fresh()),
                userId: $userId,
                notes: $data->approval_notes
            );
        });
    }

    private function logApproval(
        PurchaseOrder $order,
        ApprovePurchaseOrderData $data,
        int $userId
    ): void {
        // Implement audit logging
        // PurchaseOrderAuditLog::create([...]);
    }
}
