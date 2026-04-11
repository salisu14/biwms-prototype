<?php

// app/Data/PO/ApprovePurchaseOrderData.php

namespace App\Data\PO;

use Spatie\LaravelData\Attributes\Validation\Nullable;
use Spatie\LaravelData\Attributes\Validation\StringType;
use Spatie\LaravelData\Data;

class ApprovePurchaseOrderData extends Data
{
    public function __construct(
        #[Nullable]
        #[StringType]
        public readonly ?string $approval_notes = null,
    ) {}

    public static function rules(): array
    {
        return [
            'approval_notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
