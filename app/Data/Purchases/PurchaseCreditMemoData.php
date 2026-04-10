<?php

namespace App\Data\Purchases;

use App\Enums\ApprovalStatus;
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
        public ?int $corrects_invoice_id = null,
        public ?string $external_document_number = null,
        public ?\DateTime $posting_date = null,
        public ?\DateTime $document_date = null,
        public ?int $location_id = null,
        public ?string $currency_code = 'USD',
        public ?string $reason_code = null,
        public ?string $description = null,
        /** @var DataCollection<PurchaseCreditMemoLineData> */
        public DataCollection $lines,
    ) {}
}
