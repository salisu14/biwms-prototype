<?php

namespace App\Data\Purchase;

use App\Data\PO\PurchaseOrderLineData;
use App\Enums\PurchaseOrderType;
use Carbon\Carbon;
use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Data;

class CreatePurchaseOrderData extends Data
{
    public function __construct(
        #[Nullable]
        public ?int $businessId,

        #[Required]
        public PurchaseOrderType $orderType,

        #[Required]
        public int $vendorId,

        //        #[Nullable]
        //        public ?string $vendorName,

        #[Required]
        public Carbon $orderDate,

        #[Required]
        public int $locationId,

        #[Nullable]
        public ?Carbon $postingDate,

        #[Nullable]
        public ?Carbon $dueDate,

        #[Nullable]
        public ?Carbon $deliveryDate,

        #[Nullable]
        public ?string $paymentTerms,

        #[Nullable]
        public ?string $comment,

        #[Required]
        public int $createdBy,

        /** @var PurchaseOrderLineData[] */
        public array $lines,

        /**
         * Explicitly chosen document currency. When null the vendor's configured
         * currency is used, falling back to LCY (NGN).
         */
        #[Nullable]
        public ?string $currencyCode = null,

        /** LCY-per-FCY rate. Required (non-null) for foreign-currency documents. */
        #[Nullable]
        public ?float $currencyFactor = null,
    ) {}
}
