<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseCreditMemoLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_credit_memo_id',
        'line_number',
        'item_id',
        'item_code',
        'description',
        'quantity',
        'unit_cost',
        'line_total',
        'tax_percent',
        'tax_amount',
        'grand_total',
        'general_product_posting_group_id',
        'unit_of_measure_code',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'line_total' => 'decimal:4',
        'tax_percent' => 'decimal:4',
        'tax_amount' => 'decimal:4',
        'grand_total' => 'decimal:4',
    ];

    public function purchaseCreditMemo(): BelongsTo
    {
        return $this->belongsTo(PurchaseCreditMemo::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function generalProductPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralProductPostingGroup::class);
    }

    protected static function booted(): void
    {
        static::saving(function (PurchaseCreditMemoLine $line) {
            $line->line_total = $line->quantity * $line->unit_cost;
            $line->tax_amount = $line->line_total * ($line->tax_percent / 100);
            $line->grand_total = $line->line_total + $line->tax_amount;
        });

        static::saved(function (PurchaseCreditMemoLine $line) {
            $line->purchaseCreditMemo->update([
                'subtotal' => $line->purchaseCreditMemo->lines()->sum('line_total'),
                'tax_amount' => $line->purchaseCreditMemo->lines()->sum('tax_amount'),
                'grand_total' => $line->purchaseCreditMemo->lines()->sum('grand_total'),
            ]);
        });
    }
}
