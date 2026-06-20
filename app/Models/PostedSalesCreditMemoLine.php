<?php

// app/Models/PostedSalesCreditMemoLine.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostedSalesCreditMemoLine extends Model
{
    use HasFactory;

    protected $table = 'posted_sales_credit_memo_lines';

    protected $fillable = [
        'posted_sales_credit_memo_id',
        'corrected_invoice_line_id',
        'so_line_id',
        'so_line_number',
        'item_id',
        'item_code',
        'item_description',
        'posting_date',
        'variant_code',
        'general_product_posting_group_id',
        'inventory_posting_group_id',
        'sales_account_id',
        'cogs_account_id',
        'inventory_account_id',
        'returns_account_id',
        'quantity',
        'unit_of_measure_code',
        'qty_per_unit_of_measure',
        'quantity_base',
        'unit_price',
        'unit_cost',
        'unit_cost_lcy',
        'line_discount_percent',
        'line_discount_amount',
        'line_total',
        'line_amount',
        'vat_code',
        'vat_percentage',
        'vat_amount',
        'amount_including_vat',
        'cost_amount_reversed',
        'inventory_amount_reversed',
        'return_type',
        'lot_number',
        'serial_number',
        'expiration_date',
        'warehouse_receipt_id',
        'return_bin_code',
        'item_ledger_entry_id',
        'gl_entry_id',
        'dimensions',
        'line_number',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'qty_per_unit_of_measure' => 'decimal:4',
        'quantity_base' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'unit_cost_lcy' => 'decimal:4',
        'line_discount_percent' => 'decimal:2',
        'line_discount_amount' => 'decimal:4',
        'line_total' => 'decimal:4',
        'line_amount' => 'decimal:4',
        'vat_percentage' => 'decimal:2',
        'vat_amount' => 'decimal:4',
        'amount_including_vat' => 'decimal:4',
        'cost_amount_reversed' => 'decimal:4',
        'inventory_amount_reversed' => 'decimal:4',
        'expiration_date' => 'date',
        'dimensions' => 'array',
    ];

    // ==================== RELATIONSHIPS ====================

    public function postedSalesCreditMemo(): BelongsTo
    {
        return $this->belongsTo(PostedSalesCreditMemo::class, 'posted_sales_credit_memo_id');
    }

    public function correctedInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(PostedSalesInvoiceLine::class, 'corrected_invoice_line_id');
    }

    public function salesOrderLine(): BelongsTo
    {
        return $this->belongsTo(SalesOrderLine::class, 'so_line_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function generalProductPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralProductPostingGroup::class);
    }

    public function inventoryPostingGroup(): BelongsTo
    {
        return $this->belongsTo(InventoryPostingGroup::class);
    }

    public function salesAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'sales_account_id');
    }

    public function cogsAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'cogs_account_id');
    }

    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'inventory_account_id');
    }

    public function returnsAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'returns_account_id');
    }

    public function warehouseReceipt(): BelongsTo
    {
        return $this->belongsTo(WarehouseReceipt::class);
    }

    public function itemLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(ItemLedgerEntry::class);
    }

    // ==================== CALCULATED ATTRIBUTES ====================

    public function getIsInventoryItemAttribute(): bool
    {
        return $this->inventory_posting_group_id !== null;
    }

    public function getAbsoluteQuantityAttribute(): float
    {
        return abs($this->quantity);
    }

    public function getOriginalSaleAmountAttribute(): float
    {
        // What this line originally sold for
        return abs($this->line_amount);
    }

    public function getNetCreditAmountAttribute(): float
    {
        // After any restocking fees
        return abs($this->amount_including_vat);
    }

    // ==================== SCOPES ====================

    public function scopeInventoryItems($query)
    {
        return $query->whereNotNull('inventory_posting_group_id');
    }

    public function scopeForItem($query, int $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    public function scopePhysicalReturns($query)
    {
        return $query->whereNotNull('warehouse_receipt_id');
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereHas('postedSalesCreditMemo', function ($q) use ($startDate, $endDate) {
            $q->whereBetween('posting_date', [$startDate, $endDate]);
        });
    }

    // ==================== BUSINESS METHODS ====================

    /**
     * Post inventory put-back when physical return received
     */
    public function postInventoryPutBack(
        WarehouseReceiptLine $receiptLine,
        float $actualQuantity,
        ?float $actualCost = null
    ): ItemLedgerEntry {
        $cost = $actualCost ?? $this->unit_cost;

        // Create positive item ledger entry (inventory increase)
        $entry = ItemLedgerEntry::create([
            'entry_type' => 'SALES_RETURN',
            'document_type' => 'SALES_CREDIT_MEMO',
            'document_number' => $this->postedSalesCreditMemo->document_number,
            'document_line_number' => $this->line_number,
            'item_id' => $this->item_id,
            'variant_code' => $this->variant_code,
            'location_id' => $receiptLine->warehouseReceipt->location_id,
            'bin_code' => $this->return_bin_code,
            'quantity' => $actualQuantity, // Positive
            'remaining_quantity' => $actualQuantity,
            'serial_number' => $receiptLine->serial_number ?? $this->serial_number,
            'lot_number' => $receiptLine->lot_number ?? $this->lot_number,
            'expiration_date' => $receiptLine->expiration_date,
            'cost_amount_actual' => $cost * $actualQuantity,
            'general_business_posting_group_id' => $this->postedSalesCreditMemo->general_business_posting_group_id,
            'general_product_posting_group_id' => $this->general_product_posting_group_id,
            'inventory_posting_group_id' => $this->inventory_posting_group_id,
            'posting_date' => $this->postedSalesCreditMemo->posting_date,
            'entry_date' => now(),
            'open' => true,
        ]);

        // Update this line
        $this->update([
            'warehouse_receipt_id' => $receiptLine->warehouse_receipt_id,
            'item_ledger_entry_id' => $entry->id,
        ]);

        // Update item inventory
        $this->item->increment('inventory', $actualQuantity);

        return $entry;
    }

    /**
     * Get restocking fee recommendation based on return condition
     */
    public function getRestockingFeePercent(): float
    {
        return match ($this->return_type) {
            'DAMAGED' => 0.50,      // 50% fee
            'DEFECTIVE' => 0.00,     // No fee
            'WRONG_ITEM' => 0.00,    // No fee (our error)
            'PARTIAL' => 0.15,       // 15% fee
            default => 0.00,         // Full return
        };
    }
}
