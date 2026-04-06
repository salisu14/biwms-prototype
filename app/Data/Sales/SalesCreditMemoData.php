<?php

namespace App\Data\Sales;

use Spatie\LaravelData\Data;

class SalesCreditMemoData extends Data
{
    public function __construct(
        public int $customer_id,
        public string $memo_number,
        public float $total_amount,
        public string $status, // draft or posted
        public ?string $reason,
        public \DateTime $effective_date,
        public array $items // array of SalesCreditMemoItemData
    ) {}
}
