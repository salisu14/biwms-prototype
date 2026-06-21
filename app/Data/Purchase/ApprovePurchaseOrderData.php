<?php

namespace App\Data\Purchase;

use Spatie\LaravelData\Data;

class ApprovePurchaseOrderData extends Data
{
    public function __construct(
        public int $purchaseOrderId,
        public int $approvedBy,
    ) {}
}
