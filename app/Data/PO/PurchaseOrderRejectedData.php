<?php
// app/Data/PO/PurchaseOrderRejectedData.php

namespace App\Data\PO;

use Spatie\LaravelData\Data;

class PurchaseOrderRejectedData extends Data
{
    public function __construct(
        public readonly bool $success,
        public readonly string $message,
        public readonly PurchaseOrderData $order,
        public readonly string $rejected_at,
        public readonly int $rejected_by,
        public readonly string $rejection_reason,
        public readonly ?string $suggested_action = null,
    ) {}

    public static function fromRejection(
        PurchaseOrderData $orderData,
        int $userId,
        string $reason,
        ?string $suggestedAction = null
    ): self {
        return new self(
            success: true,
            message: 'Purchase order rejected',
            order: $orderData,
            rejected_at: now()->format('Y-m-d H:i:s'),
            rejected_by: $userId,
            rejection_reason: $reason,
            suggested_action: $suggestedAction,
        );
    }
}
