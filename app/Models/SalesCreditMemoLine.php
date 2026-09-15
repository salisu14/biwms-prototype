<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SalesLinePricingStatus;
use App\Services\Sales\SalesDocumentMonetaryCalculator;
use App\Support\DecimalPrecision;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesCreditMemoLine extends Model
{
    use HasFactory;

    /**
     * `unit_price_lcy` mirrors its 5-decimal document-currency column; the other
     * LCY components mirror their 2-decimal document columns.
     */
    private const UNIT_PRICE_LCY_SCALE = 5;

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
        'unit_price_lcy',
        'line_discount_amount_lcy',
        'vat_amount_lcy',
        'amount_lcy',
        'amount_including_vat_lcy',
        'sales_invoice_line_id',
        'posted_sales_invoice_line_id',
        'price_source',
        'pricing_master_id',
        'price_record_id',
        'pricing_status',
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
        'unit_price_lcy' => 'decimal:5',
        'line_discount_amount_lcy' => 'decimal:2',
        'vat_amount_lcy' => 'decimal:2',
        'amount_lcy' => 'decimal:2',
        'amount_including_vat_lcy' => 'decimal:2',
        'pricing_status' => SalesLinePricingStatus::class,
    ];

    protected static function booted(): void
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

            $line->deriveLcyAmounts();
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

    /**
     * Derive the LCY equivalents of this credit memo line's document-currency
     * amounts. The commercial (FCY) amounts remain authoritative and are never
     * modified here.
     *
     * A linked memo line's unit price was inherited from the posted invoice
     * line, so its LCY value is derived from that same inherited document price
     * using the memo's own currency context. An unresolved currency/factor
     * leaves existing LCY values untouched rather than fabricating them.
     */
    public function deriveLcyAmounts(): void
    {
        $memo = $this->relationLoaded('creditMemo') ? $this->creditMemo : $this->creditMemo()->first();

        if (! $memo instanceof SalesCreditMemo) {
            return;
        }

        $calculator = app(SalesDocumentMonetaryCalculator::class);

        $derived = $calculator->deriveComponents(
            $memo->currency_code,
            $memo->currency_factor,
            [
                'line_discount_amount_lcy' => $this->line_discount_amount,
                'vat_amount_lcy' => $this->vat_amount,
                'amount_lcy' => $this->amount,
                'amount_including_vat_lcy' => $this->amount_including_vat,
            ],
            DecimalPrecision::CURRENCY_SCALE,
        );

        $derived['unit_price_lcy'] = $calculator->deriveLcy(
            $memo->currency_code,
            $memo->currency_factor,
            $this->unit_price,
            self::UNIT_PRICE_LCY_SCALE,
        );

        foreach ($derived as $column => $value) {
            if ($value !== null) {
                $this->{$column} = $value;
            }
        }
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

    public function postedInvoiceLine(): BelongsTo
    {
        return $this->belongsTo(PostedSalesInvoiceLine::class, 'posted_sales_invoice_line_id');
    }
}
