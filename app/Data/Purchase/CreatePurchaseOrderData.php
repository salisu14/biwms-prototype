<?php

namespace App\Data\Purchase;

use App\Data\PO\PurchaseOrderLineData;
use App\Enums\PurchaseOrderType;
use Carbon\Carbon;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Attributes\Validation\{Required, Nullable};

class CreatePurchaseOrderData extends Data
{
    public function __construct(
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
    ) {}
}
