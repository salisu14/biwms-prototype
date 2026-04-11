<?php

namespace App\Data\Sales;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class SalesCreditMemoLineData extends Data
{
    public function __construct(
        public int $item_id,
        public float $quantity,
        public float $unit_price,
        public float $vat_percent = 0,
        public ?string $description = null,
    ) {}
}

class SalesCreditMemoData extends Data
{
    public function __construct(
        public int $customer_id,
        public ?int $sales_invoice_id,
        public ?string $memo_number,
        public ?\DateTime $effective_date,
        public ?string $currency_code,
        public ?string $reason,
        /** @var DataCollection<SalesCreditMemoLineData> */
        public DataCollection $items,
    ) {}
}
