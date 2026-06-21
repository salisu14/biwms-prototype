<?php

namespace App\Data\Purchase;

use Spatie\LaravelData\Data;

class PostInvoiceData extends Data
{
    public function __construct(
        public int $purchaseOrderId,
        public array $lines, // [PostInvoiceLineData]
        public \DateTime $postingDate,
        public ?string $documentNumber,
    ) {}
}
