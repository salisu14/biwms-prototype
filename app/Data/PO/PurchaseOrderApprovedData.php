<?php

// app/Data/PO/PurchaseOrderApprovedData.php

namespace App\Data\PO;

use Spatie\LaravelData\Data;

class PurchaseOrderApprovedData extends Data
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly PurchaseOrderData $order,
        public readonly string $approved_at,
        public readonly int $approved_by,
        public readonly ?string $approval_notes = null,
    ) {}

    public static function fromApproval(
        PurchaseOrderData $orderData,
        int $userId,
        ?string $notes = null
    ): self {
        return new self(
            success: true,
            message: 'Purchase order approved successfully',
            order: $orderData,
            approved_at: now()->format('Y-m-d H:i:s'),
            approved_by: $userId,
            approval_notes: $notes,
        );
    }
}
