<?php

namespace App\Data\Purchase;

use Spatie\LaravelData\Data;

class CancelPurchaseOrderData extends Data
{
    public function __construct(
        public int $purchaseOrderId,
    ) {}
}
