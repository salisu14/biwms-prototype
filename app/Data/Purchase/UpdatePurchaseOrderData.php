<?php

namespace App\Data\Purchase;

use Carbon\Carbon;
use Spatie\LaravelData\Data;

class UpdatePurchaseOrderData extends Data
{
    public function __construct(
        public int $purchaseOrderId,
        public ?string $comment,
        public ?int $paymentTerms,
        public ?Carbon $deliveryDate,
    ) {}
}
