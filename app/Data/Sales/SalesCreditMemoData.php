<?php

namespace App\Data\Sales;

use App\Enums\ApprovalStatus;
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
        public ?int $sales_invoice_id = null,
        public ?string $memo_number = null,
        public ?\DateTime $effective_date = null,
        public ?string $currency_code = 'NGN',
        public ?string $reason = null,
        /** @var DataCollection<SalesCreditMemoLineData> */
        public DataCollection $items,
    ) {}
}
