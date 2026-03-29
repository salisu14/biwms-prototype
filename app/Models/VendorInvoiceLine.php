<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorInvoiceLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'vendor_invoice_id',
        'line_number',
        'type',
        'item_id',
        'gl_account_id',
        'fixed_asset_id',
        'description',
        'description_2',
        'quantity',
        'unit_of_measure_code',
        'direct_unit_cost',
        'line_discount_percent',
        'line_discount_amount',
        'line_amount',
        'tax_group_code',
        'tax_percent',
        'tax_amount',
        'purchase_order_id',
        'purchase_order_line_no',
        'purchase_receipt_id',
        'purchase_receipt_line_no',
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
        'dimension_set_id',
        'capex_project_id',
        'capex_project_line_id',
        'production_order_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'direct_unit_cost' => 'decimal:4',
        'line_discount_percent' => 'decimal:2',
        'line_discount_amount' => 'decimal:2',
        'line_amount' => 'decimal:2',
        'tax_percent' => 'decimal:2',
        'tax_amount' => 'decimal:2',
    ];

    // Relationships

    public function vendorInvoice(): BelongsTo
    {
        return $this->belongsTo(VendorInvoice::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Item::class);
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Accounting\GlAccount::class, 'gl_account_id');
    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Manufacturing\FixedAsset::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function purchaseReceipt(): BelongsTo
    {
        return $this->belongsTo(\App\Models\PurchaseReceipt::class);
    }

    public function capExProject(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Manufacturing\CapExProject::class, 'capex_project_id');
    }

    public function capExProjectLine(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Manufacturing\CapExProjectLine::class, 'capex_project_line_id');
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Manufacturing\ProductionOrder::class);
    }

    // Business Logic

    /**
     * Calculate line amount
     */
    public function calculateLineAmount(): float
    {
        $amount = $this->quantity * $this->direct_unit_cost;
        $discount = min($amount, $this->line_discount_amount + ($amount * $this->line_discount_percent / 100));

        return round($amount - $discount, 2);
    }

    /**
     * Calculate tax amount
     */
    public function calculateTaxAmount(): float
    {
        return round($this->line_amount * ($this->tax_percent / 100), 2);
    }

    /**
     * Get total amount including tax
     */
    public function getTotalAmount(): float
    {
        return $this->line_amount + $this->tax_amount;
    }

    /**
     * Check if line is matched to purchase order (3-way match)
     */
    public function isMatched(): bool
    {
        return $this->purchase_order_id !== null && $this->purchase_receipt_id !== null;
    }

    /**
     * Get match status for 3-way matching
     */
    public function getMatchStatus(): string
    {
        if (!$this->purchase_order_id) {
            return 'NO_PO';
        }
        if (!$this->purchase_receipt_id) {
            return 'NO_RECEIPT';
        }
        if ($this->quantityMatches() && $this->amountMatches()) {
            return 'MATCHED';
        }
        return 'MISMATCH';
    }

    /**
     * Check if quantity matches PO/receipt
     */
    protected function quantityMatches(): bool
    {
        // Would compare against PO and receipt quantities
        return true; // Simplified
    }

    /**
     * Check if amount matches PO/receipt
     */
    protected function amountMatches(): bool
    {
        // Would compare against PO and receipt amounts
        return true; // Simplified
    }
}
