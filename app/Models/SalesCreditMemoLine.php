<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class SalesCreditMemoLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'sales_credit_memo_id',
        'line_no',
        'item_id',
        'quantity',
        'unit_of_measure_code',
        'unit_price',
        'line_discount_amount',
        'line_discount_percent',
        'vat_percent',
        'vat_amount',
        'amount', // Net
        'amount_including_vat', // Gross
        'sales_invoice_line_id',
    ];

    protected $casts = [
        'line_no' => 'integer',
        'quantity' => 'decimal:5',
        'unit_price' => 'decimal:5',
        'line_discount_amount' => 'decimal:2',
        'line_discount_percent' => 'decimal:2',
        'vat_percent' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'amount' => 'decimal:2',
        'amount_including_vat' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::saving(function (SalesCreditMemoLine $line) {
            // 1. Calculate Base (Quantity * Price)
            $lineAmountExclDiscount = $line->quantity * $line->unit_price;

            // 2. Handle Discounts (BC priorities Percent if both exist, or calculates amount)
            if ($line->line_discount_percent > 0 && $line->line_discount_amount == 0) {
                $line->line_discount_amount = round($lineAmountExclDiscount * ($line->line_discount_percent / 100), 2);
            }

            // 3. BC "Amount" is defined as (Quantity * Price) - Line Discount
            $line->amount = $lineAmountExclDiscount - $line->line_discount_amount;

            // 4. Calculate VAT
            $line->vat_amount = round($line->amount * ($line->vat_percent / 100), 2);

            // 5. BC "Amount Including VAT"
            $line->amount_including_vat = $line->amount + $line->vat_amount;
        });

        static::saved(function ($line) {
            if ($line->creditMemo) {
                $line->creditMemo->refreshTotal();
            }
        });

        static::deleted(function ($line) {
            if ($line->creditMemo) {
                $line->creditMemo->refreshTotal();
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Relationships
    |--------------------------------------------------------------------------
    */

    public function creditMemo(): BelongsTo
    {
        return $this->belongsTo(SalesCreditMemo::class, 'sales_credit_memo_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function invoiceLine(): BelongsTo
    {
        return $this->belongsTo(SalesInvoiceLine::class, 'sales_invoice_line_id');
    }
}
