<?php

// app/Data/PO/PurchaseOrderLineData.php

namespace App\Data\PO;

use App\Models\PurchaseOrderLine;
use Spatie\LaravelData\Data;

class PurchaseOrderLineData extends Data
{
    public function __construct(
        public readonly ?int $id,
        public readonly int $purchase_order_id,
        public readonly int $line_number,
        public readonly int $item_id,
        public readonly string $item_code,
        public readonly string $description,
        public readonly float $quantity,
        public readonly string $unit_of_measure,
        public readonly float $unit_cost,
        public readonly float $line_total,
        public readonly ?string $vat_code,
        public readonly float $vat_percentage,
        public readonly float $vat_amount,
        public readonly float $total_amount,
        public readonly float $received_quantity,
        public readonly float $returned_quantity,
        public readonly float $invoiced_quantity,
        public readonly ?string $expected_delivery_date,
        public readonly ?string $comment,
    ) {}

    public static function fromModel(PurchaseOrderLine $line): self
    {
        return new self(
            id: $line->id,
            purchase_order_id: $line->purchase_order_id,
            line_number: $line->line_number,
            item_id: $line->item_id,
            item_code: $line->item_code,
            description: $line->description,
            quantity: (float) $line->quantity,
            unit_of_measure: $line->unit_of_measure,
            unit_cost: (float) $line->unit_cost,
            line_total: (float) $line->line_total,
            vat_code: $line->vat_code,
            vat_percentage: (float) $line->vat_percentage,
            vat_amount: (float) $line->vat_amount,
            total_amount: (float) $line->total_amount,
            received_quantity: (float) $line->received_quantity,
            returned_quantity: (float) $line->returned_quantity,
            invoiced_quantity: (float) $line->invoiced_quantity,
            expected_delivery_date: $line->expected_delivery_date?->format('Y-m-d'),
            comment: $line->comment,
        );
    }
}
