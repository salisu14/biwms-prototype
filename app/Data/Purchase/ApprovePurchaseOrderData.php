<?php

namespace App\Data\Purchase;

use App\Enums\PurchaseOrderStatus;
use Spatie\LaravelData\Data;

class ApprovePurchaseOrderData extends Data
{
    public function __construct(
        public int $purchaseOrderId,
        public int $approvedBy,
    ) {}
}
