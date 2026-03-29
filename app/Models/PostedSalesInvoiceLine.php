<?php
// app/Models/PostedSalesInvoiceLine.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostedSalesInvoiceLine extends Model
{
    use HasFactory;

    protected $table = 'posted_sales_invoice_lines';

    protected $fillable = [
        'posted_sales_invoice_id',
        'so_line_id',
        'so_line_number',
        'item_id',
        'item_code',
        'item_description',
        'variant_code',
        'general_product_posting_group_id',
        'inventory_posting_group_id',
        'sales_account_id',
        'cogs_account_id',
        'inventory_account_id',
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
        'cost_amount',
        'profit_amount',
        'lot_number',
        'serial_number',
        'expiration_date',
        'item_ledger_entry_id',
        'shipment_id',
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
        'cost_amount' => 'decimal:4',
        'profit_amount' => 'decimal:4',
        'expiration_date' => 'date',
        'dimensions' => 'array',
    ];

    // ==================== RELATIONSHIPS ====================

    public function postedSalesInvoice(): BelongsTo
    {
        return $this->belongsTo(PostedSalesInvoice::class, 'posted_sales_invoice_id');
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

    public function itemLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(ItemLedgerEntry::class);
    }

    // ==================== CALCULATED ATTRIBUTES ====================

    public function getProfitMarginPercentAttribute(): float
    {
        if ($this->line_amount == 0) return 0;
        return ($this->profit_amount / $this->line_amount) * 100;
    }

    public function getIsInventoryItemAttribute(): bool
    {
        return $this->inventory_posting_group_id !== null;
    }

    // ==================== SCOPES ====================

    public function scopeForItem($query, int $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    public function scopeForProductGroup($query, int $groupId)
    {
        return $query->where('general_product_posting_group_id', $groupId);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereHas('postedSalesInvoice', function ($q) use ($startDate, $endDate) {
            $q->whereBetween('posting_date', [$startDate, $endDate]);
        });
    }

    public function scopeProfitable($query)
    {
        return $query->where('profit_amount', '>', 0);
    }

    public function scopeUnprofitable($query)
    {
        return $query->where('profit_amount', '<=', 0);
    }
}
