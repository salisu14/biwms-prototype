<?php

// app/Data/PO/RejectPurchaseOrderData.php

namespace App\Data\PO;

use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\Required;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class RejectPurchaseOrderData extends Data
{
    public function __construct(
        #[Required]
        #[StringType]
        public readonly string $rejection_reason,

        #[Nullable]
        #[StringType]
        public readonly ?string $suggested_action = null,
    ) {}

    public static function rules(): array
    {
        return [
            'rejection_reason' => ['required', 'string', 'max:1000'],
            'suggested_action' => ['nullable', 'string', 'max:500'],
        ];
    }
}
