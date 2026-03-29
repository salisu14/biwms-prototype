<?php
// app/Models/PurchaseOrderLine.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'purchase_order_id',
    'line_number',
    'item_id',
    'item_code',
    'description',
    'variant_code',           // NEW: Item variant
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
    // NEW posting-related
    'general_product_posting_group_id', // Copied from item
])]
class PurchaseOrderLine extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $table = 'purchase_order_lines';

    protected $casts = [
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

        static::saving(function ($line) {
            $line->line_total = $line->quantity * $line->unit_cost;
            $line->vat_amount = $line->line_total * ($line->vat_percentage / 100);
            $line->total_amount = $line->line_total + $line->vat_amount;
        });

        // Auto-set posting group from item
        static::creating(function ($line) {
            if ($line->item_id && !$line->general_product_posting_group_id) {
                $item = Item::find($line->item_id);
                if ($item) {
                    $line->general_product_posting_group_id = $item->general_product_posting_group_id;
                    $line->item_code = $item->item_number;
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
        return $this->belongsTo(Item::class);
    }

    // NEW: Product Posting Group
    public function generalProductPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralProductPostingGroup::class);
    }

    // NEW: Warehouse Receipt Lines
    public function warehouseReceiptLines(): HasMany
    {
        return $this->hasMany(WarehouseReceiptLine::class, 'source_line_id');
    }

    // NEW: Posted Invoice Lines
    public function postedInvoiceLines(): HasMany
    {
        return $this->hasMany(PostedPurchaseInvoiceLine::class, 'po_line_id');
    }

    // ==================== CALCULATED ATTRIBUTES ====================

    public function getRemainingQuantityAttribute(): float
    {
        return max(0, $this->quantity - $this->received_quantity);
    }

    public function getRemainingToInvoiceAttribute(): float
    {
        return max(0, $this->received_quantity - $this->invoiced_quantity);
    }

    public function getIsFullyReceivedAttribute(): bool
    {
        return $this->received_quantity >= $this->quantity;
    }

    public function getIsPartiallyReceivedAttribute(): bool
    {
        return $this->received_quantity > 0 && $this->received_quantity < $this->quantity;
    }

    public function getIsFullyInvoicedAttribute(): bool
    {
        return $this->invoiced_quantity >= $this->quantity;
    }

    public function getLineTotalAttribute(): float
    {
        return $this->quantity * $this->unit_cost;
    }

    public function getVatAmountAttribute(): float
    {
        return $this->line_total * ($this->vat_percentage / 100);
    }

    public function getTotalAmountAttribute(): float
    {
        return $this->line_total + $this->vat_amount;
    }

    // ==================== POSTING HELPERS ====================

    /**
     * Get General Posting Setup for this line
     */
    public function getPostingSetup(): ?GeneralPostingSetup
    {
        $businessGroupId = $this->purchaseOrder->general_business_posting_group_id;
        $productGroupId = $this->general_product_posting_group_id;

        if (!$businessGroupId || !$productGroupId) {
            return null;
        }

        return GeneralPostingSetup::where([
            'general_business_posting_group_id' => $businessGroupId,
            'general_product_posting_group_id' => $productGroupId,
        ])->first();
    }

    /**
     * Get purchase account for posting
     */
    public function getPurchaseAccount(): ?ChartOfAccount
    {
        return $this->getPostingSetup()?->getPurchaseAccount();
    }

    /**
     * Get direct cost applied account
     */
    public function getDirectCostAccount(): ?ChartOfAccount
    {
        return $this->getPostingSetup()?->getDirectCostAppliedAccount();
    }

    /**
     * Validate posting setup exists
     */
    public function validatePostingSetup(): array
    {
        $errors = [];

        if (!$this->getPostingSetup()) {
            $errors[] = "General Posting Setup missing for Business Group: " .
                $this->purchaseOrder->generalBusinessPostingGroup?->code .
                " and Product Group: " .
                $this->generalProductPostingGroup?->code;
        }

        if (!$this->getPurchaseAccount()) {
            $errors[] = "Purchase Account not configured";
        }

        return $errors;
    }
}
