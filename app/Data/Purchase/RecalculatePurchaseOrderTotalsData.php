<?php

namespace App\Data\Purchase;

use Spatie\LaravelData\Data;

class RecalculatePurchaseOrderTotalsData extends Data
{
    public function __construct(
        public int $purchaseOrderId,
    ) {}
}
