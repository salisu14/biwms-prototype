<?php

// app/Data/PO/PurchaseOrderData.php

namespace App\Data\PO;

use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseOrderType;
use App\Models\PurchaseOrder;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\EnumCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\LaravelData\Lazy;

class PurchaseOrderData extends Data
{
    public function __construct(
        public readonly ?int $id,

        public readonly string $order_number,

        #[WithCast(EnumCast::class, PurchaseOrderType::class)]
        public readonly PurchaseOrderType $order_type,

        public readonly int $vendor_id,
        public readonly string $vendor_name,

        public readonly string $order_date,
        public readonly int $location_id,
        public readonly ?string $posting_date,
        public readonly ?string $due_date,
        public readonly ?string $delivery_date,

        public readonly ?string $payment_terms,

        #[WithCast(EnumCast::class, PurchaseOrderStatus::class)]
        public readonly PurchaseOrderStatus $status,

        public readonly ?string $comment,

        public readonly float $total_amount,
        public readonly float $total_vat,
        public readonly float $grand_total,

        public readonly int $created_by,
        public readonly ?int $approved_by,
        public readonly ?string $approved_at,

        /** @var DataCollection<PurchaseOrderLineData> */
        public readonly ?DataCollection $lines,

        public readonly ?string $created_at,
        public readonly ?string $updated_at,
    ) {}

    public static function fromModel(PurchaseOrder $order): self
    {
        return new self(
            id: $order->id,
            order_number: $order->order_number,
            order_type: $order->order_type,
            vendor_id: $order->vendor_id,
            vendor_name: $order->vendor_name,
            order_date: $order->order_date->format('Y-m-d'),
            location_id: $order->location_id,
            posting_date: $order->posting_date?->format('Y-m-d'),
            due_date: $order->due_date?->format('Y-m-d'),
            delivery_date: $order->delivery_date?->format('Y-m-d'),
            payment_terms: $order->payment_terms,
            status: $order->status,
            comment: $order->comment,
            total_amount: (float) $order->total_amount,
            total_vat: (float) $order->total_vat,
            grand_total: (float) $order->grand_total,
            created_by: $order->created_by,
            approved_by: $order->approved_by,
            approved_at: $order->approved_at?->format('Y-m-d H:i:s'),
            lines: Lazy::whenLoaded('lines', $order, fn () => PurchaseOrderLineData::collection($order->lines)),
            created_at: $order->created_at?->format('Y-m-d H:i:s'),
            updated_at: $order->updated_at?->format('Y-m-d H:i:s'),
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'order_type' => $this->order_type->value,
            'order_type_label' => $this->order_type->label(),
            'vendor_id' => $this->vendor_id,
            'vendor_name' => $this->vendor_name,
            'order_date' => $this->order_date,
            'location_id' => $this->location_id,
            'posting_date' => $this->posting_date,
            'due_date' => $this->due_date,
            'delivery_date' => $this->delivery_date,
            'payment_terms' => $this->payment_terms,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'status_color' => $this->status->color(),
            'comment' => $this->comment,
            'total_amount' => $this->total_amount,
            'total_vat' => $this->total_vat,
            'grand_total' => $this->grand_total,
            'created_by' => $this->created_by,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'lines' => $this->lines?->toArray(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
