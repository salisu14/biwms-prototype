<?php

namespace App\Data\Purchase;

use Spatie\LaravelData\Attributes\Validation\Numeric;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class PurchaseOrderLineData extends Data
{
    public function __construct(
        #[Required]
        public int $itemId,

        #[Required]
        public string $description,

        #[Required, Numeric]
        public float $quantity,

        #[Required, Numeric]
        public float $unitCost,

        #[Required]
        public string $unitOfMeasure,

        #[Required, Numeric]
        public float $vatPercentage,
    ) {}
}
