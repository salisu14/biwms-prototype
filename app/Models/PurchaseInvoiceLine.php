<?php

// app/Models/PostedPurchaseInvoiceLine.php

namespace App\Models;

use App\Enums\PurchaseLineType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseInvoiceLine extends Model
{
    use HasFactory;

    protected $table = 'posted_purchase_invoice_lines';

    protected $fillable = [
        'posted_purchase_invoice_id',
        'po_line_id',
        'po_line_number',
        'item_id',
        'item_code',
        'item_description',
        'variant_code',
        'general_product_posting_group_id',
        'inventory_posting_group_id',
        'gl_account_id',
        'gl_account_number',
        'gl_account_name',
        'quantity',
        'unit_of_measure_code',
        'qty_per_unit_of_measure',
        'quantity_base',
        'unit_cost',
        'unit_cost_lcy',
        'line_total',
        'line_discount_amount',
        'line_discount_percent',
        'vat_code',
        'vat_percentage',
        'vat_amount',
        'vat_amount_lcy',
        'amount_including_vat',
        'amount_including_vat_lcy',
        'lot_number',
        'serial_number',
        'expiration_date',
        'job_number',
        'job_task_number',
        'dimensions',
        'item_ledger_entry_id',
        'gl_entry_id',
        'line_number',
        'type',
        'asset_id',
        'fa_posting_type',
    ];

    protected $casts = [
        'type' => PurchaseLineType::class,
        'quantity' => 'decimal:4',
        'qty_per_unit_of_measure' => 'decimal:4',
        'quantity_base' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'unit_cost_lcy' => 'decimal:4',
        'line_total' => 'decimal:4',
        'line_discount_amount' => 'decimal:4',
        'line_discount_percent' => 'decimal:2',
        'vat_percentage' => 'decimal:2',
        'vat_amount' => 'decimal:4',
        'vat_amount_lcy' => 'decimal:4',
        'amount_including_vat' => 'decimal:4',
        'amount_including_vat_lcy' => 'decimal:4',
        'expiration_date' => 'date',
        'dimensions' => 'array',
    ];

    // ==================== RELATIONSHIPS ====================

    public function postedPurchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PurchaseInvoice::class, 'posted_purchase_invoice_id');
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class, 'po_line_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class);
    }

    public function generalProductPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralProductPostingGroup::class);
    }

    public function inventoryPostingGroup(): BelongsTo
    {
        return $this->belongsTo(InventoryPostingGroup::class);
    }

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'gl_account_id');
    }

    public function itemLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(ItemLedgerEntry::class, 'item_ledger_entry_id');
    }

    public function glEntry(): BelongsTo
    {
        return $this->belongsTo(GlEntry::class, 'gl_entry_id');
    }

    // ==================== CALCULATED ATTRIBUTES ====================

    public function getIsInventoryItemAttribute(): bool
    {
        return $this->inventory_posting_group_id !== null;
    }

    public function getHasTrackingAttribute(): bool
    {
        return $this->lot_number !== null || $this->serial_number !== null;
    }

    // ==================== SCOPES ====================

    public function scopeForItem($query, int $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    public function scopeForGlAccount($query, int $accountId)
    {
        return $query->where('gl_account_id', $accountId);
    }

    public function scopeInventoryItems($query)
    {
        return $query->whereNotNull('inventory_posting_group_id');
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereHas('postedPurchaseInvoice', function ($q) use ($startDate, $endDate) {
            $q->whereBetween('posting_date', [$startDate, $endDate]);
        });
    }
}
