<?php

namespace App\Data\Purchase;

use Spatie\LaravelData\Data;

class PostInvoiceLineData extends Data
{
    public function __construct(
        public int $poLineId,
        public float $quantity,
    ) {}
}
