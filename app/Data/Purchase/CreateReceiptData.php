<?php

namespace App\Data\Purchase;

use Spatie\LaravelData\Data;

class CreateReceiptData extends Data
{
    public function __construct(
        public int $purchaseOrderId,
        public ?int $userId,
    ) {}
}
