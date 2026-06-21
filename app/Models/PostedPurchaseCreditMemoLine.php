<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PostedPurchaseCreditMemoLine extends Model
{
    use HasFactory;

    protected $table = 'posted_purchase_credit_memo_lines';

    protected $fillable = [
        'credit_memo_id',
        'line_number',
        'type', // ITEM, GL_ACCOUNT, RESOURCE, etc.
        'item_id',
        'gl_account_id',
        'description',
        'quantity',
        'unit_of_measure',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'amount',
        'tax_percent',
        'tax_amount',
        'line_total',

        // Posting Groups (per line)
        'general_product_posting_group_id',
        'inventory_posting_group_id',
        'tax_group_id',

        // Dimensions
        'dimensions',

        // Reference to original invoice line
        'corrected_invoice_line_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:4',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:4',
        'amount' => 'decimal:4',
        'tax_percent' => 'decimal:2',
        'tax_amount' => 'decimal:4',
        'line_total' => 'decimal:4',
        'dimensions' => 'array',
    ];

    public function creditMemo(): BelongsTo
    {
        return $this->belongsTo(PostedPurchaseCreditMemo::class, 'credit_memo_id');
    }

    public function generalProductPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralProductPostingGroup::class);
    }

    public function inventoryPostingGroup(): BelongsTo
    {
        return $this->belongsTo(InventoryPostingGroup::class);
    }
}
