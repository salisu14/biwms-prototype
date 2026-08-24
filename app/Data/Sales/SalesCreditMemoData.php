<?php

declare(strict_types=1);

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
        public ?string $unit_of_measure_code = null,
        public float $line_discount_percent = 0,
        public float $line_discount_amount = 0,
        public ?int $sales_invoice_line_id = null,
        public ?int $posted_sales_invoice_line_id = null,
    ) {}
}

class SalesCreditMemoData extends Data
{
    public function __construct(
        public int $customer_id,
        public ?int $sales_invoice_id,
        public ?int $posted_sales_invoice_id,
        public ?string $memo_number,
        public ?string $effective_date,
        public ?string $currency_code,
        public ?string $reason,
        /** @var DataCollection<SalesCreditMemoLineData> */
        public DataCollection $items,
    ) {}
}
