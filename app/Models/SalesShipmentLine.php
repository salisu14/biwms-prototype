<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Enums\SalesLineType;

class SalesShipmentLine extends Model
{
    use HasFactory;

    protected $table = 'sales_shipment_lines';

    protected $fillable = [
        'sales_shipment_header_id', 'document_no', 'line_no', 'type', 'no',
        'description', 'quantity', 'quantity_base', 'unit_of_measure',
        'unit_price', 'line_discount_pct', 'qty_shipped_not_invoiced',
        'quantity_invoiced', 'order_no', 'order_line_no', 'drop_shipment',
        'location_code', 'bin_code', 'dimension_set_id', 'serial_no', 'lot_no'
    ];

    protected $casts = [
        'type' => SalesLineType::class,
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'line_discount_pct' => 'decimal:2',
        'drop_shipment' => 'boolean',
        'correction' => 'boolean',
        'shipment_date' => 'date',
    ];

    // Relationships
    public function header(): BelongsTo
    {
        return $this->belongsTo(SalesShipmentHeader::class, 'sales_shipment_header_id');
    }

    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class, 'sales_order_line_id');
    }

    public function itemTrackingEntries(): MorphMany
    {
        return $this->morphMany(ItemTrackingEntry::class, 'document');
    }

    public function dimensionSet(): BelongsTo
    {
        return $this->belongsTo(DimensionSet::class);
    }

    // Scopes
    public function scopeItems($query)
    {
        return $query->where('type', SalesLineType::Item);
    }

    public function scopeShippedNotInvoiced($query)
    {
        return $query->whereColumn('quantity', '>', 'quantity_invoiced');
    }

    public function scopeForItem($query, string $itemNo)
    {
        return $query->where('no', $itemNo);
    }

    // Business Logic
    public function remainingQtyToInvoice(): float
    {
        return $this->qty_shipped_not_invoiced;
    }

    public function isItem(): bool
    {
        return $this->type === SalesLineType::Item;
    }

    public function requiresItemTracking(): bool
    {
        if (!$this->isItem()) return false;

        // Check item tracking setup
        return Item::where('item_no', $this->no)
            ->where(function($q) {
                $q->where('item_tracking_code', '!=', null)
                    ->where('item_tracking_code', '!=', '');
            })->exists();
    }

    public function getLineDiscountAmount(): float
    {
        return $this->line_discount_amount ?:
            ($this->line_amount * $this->line_discount_pct / 100);
    }

    public function getAmountIncludingVAT(): float
    {
        if ($this->header?->prices_including_vat) {
            return $this->line_amount;
        }

        return $this->line_amount * (1 + $this->vat_pct / 100);
    }

    // BC "Copy Document" functionality support
    public function toInvoiceLineArray(): array
    {
        return [
            'type' => $this->type,
            'no' => $this->no,
            'variant_code' => $this->variant_code,
            'location_code' => $this->location_code,
            'description' => $this->description,
            'description_2' => $this->description_2,
            'quantity' => $this->qty_shipped_not_invoiced,
            'unit_of_measure_code' => $this->unit_of_measure_code,
            'unit_price' => $this->unit_price,
            'line_discount_pct' => $this->line_discount_pct,
            'order_no' => $this->order_no,
            'order_line_no' => $this->order_line_no,
            'shipment_no' => $this->document_no,
            'shipment_line_no' => $this->line_no,
            'qty_to_ship' => 0, // Already shipped
            'qty_to_invoice' => $this->qty_shipped_not_invoiced,
        ];
    }
}
