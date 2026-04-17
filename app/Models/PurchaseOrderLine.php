<?php

namespace App\Models;

use App\Enums\PurchaseLineType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PurchaseOrderLine extends Model
{
    use HasFactory;

    protected $table = 'purchase_order_lines';

    protected $fillable = [
        'purchase_order_id',
        'line_number',
        'item_id',
        'item_code',
        'description',
        'variant_code',
        'quantity',
        'unit_of_measure',
        'unit_cost',
        'line_total',
        'vat_code',
        'vat_percentage',
        'vat_amount',
        'total_amount',
        'received_quantity',
        'returned_quantity',
        'invoiced_quantity',
        'expected_delivery_date',
        'comment',
        'general_product_posting_group_id',
        'type',
        'asset_id',
        'fa_posting_type',
    ];

    protected $casts = [
        'type' => PurchaseLineType::class,
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'line_total' => 'decimal:4',
        'vat_percentage' => 'decimal:2',
        'vat_amount' => 'decimal:4',
        'total_amount' => 'decimal:4',
        'received_quantity' => 'decimal:4',
        'returned_quantity' => 'decimal:4',
        'invoiced_quantity' => 'decimal:4',
        'expected_delivery_date' => 'date',
    ];

    protected static function boot(): void
    {
        parent::boot();

        /**
         * Logic for calculations and metadata syncing is now
         * primarily handled in PurchaseOrderService or via
         * Filament RelationManager hooks to ensure UI/UX consistency.
         */
        static::saving(function ($line) {
            $line->line_total = (float) $line->quantity * (float) $line->unit_cost;
            $line->vat_amount = (float) $line->line_total * ((float) $line->vat_percentage / 100);
            $line->total_amount = (float) $line->line_total + (float) $line->vat_amount;
        });

        // Auto-set posting group from item if not already set
        static::creating(function ($line) {
            if ($line->item_id && ! $line->general_product_posting_group_id) {
                $item = Item::find($line->item_id);
                if ($item) {
                    $line->general_product_posting_group_id = $item->general_product_posting_group_id;
                    $line->item_code = $item->item_code;
                }
            }
        });
    }

    // ==================== RELATIONSHIPS ====================

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'asset_id');
    }

    public function generalProductPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralProductPostingGroup::class);
    }

    public function warehouseReceiptLines(): HasMany
    {
        return $this->hasMany(WarehouseReceiptLine::class, 'source_line_id');
    }

    public function postedInvoiceLines(): HasMany
    {
        return $this->hasMany(PurchaseInvoiceLine::class, 'po_line_id');
    }

    // ==================== CALCULATED ATTRIBUTES ====================

    public function getRemainingQuantityAttribute(): float
    {
        return max(0, (float) $this->quantity - (float) $this->received_quantity);
    }

    public function getRemainingToInvoiceAttribute(): float
    {
        return max(0, (float) $this->received_quantity - (float) $this->invoiced_quantity);
    }

    public function getIsFullyReceivedAttribute(): bool
    {
        return (float) $this->received_quantity >= (float) $this->quantity;
    }

    public function getIsPartiallyReceivedAttribute(): bool
    {
        return (float) $this->received_quantity > 0 && (float) $this->received_quantity < (float) $this->quantity;
    }

    public function getIsFullyInvoicedAttribute(): bool
    {
        return (float) $this->invoiced_quantity >= (float) $this->quantity;
    }

    // ==================== POSTING HELPERS ====================

    /**
     * Get General Posting Setup for this line by combining
     * Header (Business) and Line (Product) posting groups.
     */
    public function getPostingSetup(): ?GeneralPostingSetup
    {
        $businessGroupId = $this->purchaseOrder?->general_business_posting_group_id;
        $productGroupId = $this->general_product_posting_group_id;

        if (! $businessGroupId || ! $productGroupId) {
            return null;
        }

        return GeneralPostingSetup::where([
            'general_business_posting_group_id' => $businessGroupId,
            'general_product_posting_group_id' => $productGroupId,
        ])->first();
    }
}
