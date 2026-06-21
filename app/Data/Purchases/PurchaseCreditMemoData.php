<?php

namespace App\Data\Purchases;

use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;

class PurchaseCreditMemoLineData extends Data
{
    public function __construct(
        public int $item_id,
        public float $quantity,
        public float $unit_cost,
        public float $tax_percent = 0,
        public ?string $description = null,
    ) {}
}

class PurchaseCreditMemoData extends Data
{
    public function __construct(
        public int $vendor_id,
        public ?int $corrects_invoice_id,
        public ?string $external_document_number,
        public ?\DateTime $posting_date,
        public ?\DateTime $document_date,
        public ?int $location_id,
        public ?string $currency_code,
        public ?string $reason_code,
        public ?string $description,
        /** @var DataCollection<PurchaseCreditMemoLineData> */
        public DataCollection $lines,
    ) {}
}
