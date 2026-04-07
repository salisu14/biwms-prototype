<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\ShipmentStatus;

class SalesShipmentHeader extends Model
{
    use HasFactory;

    protected $table = 'sales_shipment_headers';

    protected $fillable = [
        'document_no', 'sales_order_id', 'order_no', 'sell_to_customer_no',
        'sell_to_customer_name', 'bill_to_customer_no', 'ship_to_code',
        'order_date', 'posting_date', 'shipment_date', 'shipment_method_code',
        'shipping_agent_code', 'package_tracking_no', 'location_code',
        'shortcut_dimension_1_code', 'shortcut_dimension_2_code', 'dimension_set_id',
        'currency_code', 'prices_including_vat', 'correction'
    ];

    protected $casts = [
        'order_date' => 'date',
        'posting_date' => 'date',
        'shipment_date' => 'date',
        'due_date' => 'date',
        'document_date' => 'date',
        'requested_delivery_date' => 'date',
        'promised_delivery_date' => 'date',
        'prices_including_vat' => 'boolean',
        'correction' => 'boolean',
        'tax_liable' => 'boolean',
    ];

    // Relationships
    public function lines(): HasMany
    {
        return $this->hasMany(SalesShipmentLine::class, 'sales_shipment_header_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(SalesOrder::class, 'sales_order_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'sell_to_customer_no', 'customer_no');
    }

    public function dimensionSet(): BelongsTo
    {
        return $this->belongsTo(DimensionSet::class);
    }

    // Scopes
    public function scopeForCustomer($query, string $customerNo)
    {
        return $query->where('sell_to_customer_no', $customerNo);
    }

    public function scopeByPostingDate($query, $from, $to)
    {
        return $query->whereBetween('posting_date', [$from, $to]);
    }

    public function scopeShippedNotInvoiced($query)
    {
        return $query->whereHas('lines', function($q) {
            $q->whereRaw('quantity > quantity_invoiced');
        });
    }

    // Accessors
    public function getStatusAttribute(): ShipmentStatus
    {
        $hasOutstanding = $this->lines->contains(fn($line) => $line->qty_shipped_not_invoiced > 0);

        if ($hasOutstanding) {
            return ShipmentStatus::PartiallyInvoiced;
        }

        return ShipmentStatus::Shipped;
    }

    // Business logic methods mirroring BC
    public function calcShippedNotInvoiced(): float
    {
        return $this->lines->sum('qty_shipped_not_invoiced');
    }

    public function isFullyInvoiced(): bool
    {
        return $this->lines->every(fn($line) => $line->qty_shipped_not_invoiced == 0);
    }

    public function getShipmentLinesForInvoicing(): array
    {
        return $this->lines
            ->where('qty_shipped_not_invoiced', '>', 0)
            ->map(fn($line) => [
                'shipment_no' => $this->document_no,
                'shipment_line_no' => $line->line_no,
                'order_no' => $line->order_no,
                'order_line_no' => $line->order_line_no,
                'type' => $line->type,
                'no' => $line->no,
                'description' => $line->description,
                'qty_to_invoice' => $line->qty_shipped_not_invoiced,
                'unit_price' => $line->unit_price,
                'line_discount_pct' => $line->line_discount_pct,
            ])->values()->toArray();
    }
}
