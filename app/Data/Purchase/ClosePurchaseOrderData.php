<?php

namespace App\Data\Purchase;

use Spatie\LaravelData\Data;

class ClosePurchaseOrderData extends Data
{
    public function __construct(
        public int $purchaseOrderId,
    ) {}
}
