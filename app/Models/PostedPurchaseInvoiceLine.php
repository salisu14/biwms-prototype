<?php

namespace App\Models;

use App\Enums\PurchaseLineType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostedPurchaseInvoiceLine extends Model
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
        'dimensions',
        'item_ledger_entry_id',
        'gl_entry_id',
        'line_number',
        'type',
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

    public function postedPurchaseInvoice(): BelongsTo
    {
        return $this->belongsTo(PostedPurchaseInvoice::class, 'posted_purchase_invoice_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
