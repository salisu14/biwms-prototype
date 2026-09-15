<?php

namespace App\Models;

use App\Enums\SalesLinePricingStatus;
use App\Services\Sales\SalesDocumentMonetaryCalculator;
use App\Support\DecimalPrecision;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SalesInvoiceLine extends Model
{
    protected $fillable = [
        'sales_invoice_id',
        'item_id',
        'type',
        'description',
        'quantity',
        'unit_of_measure',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'vat_percent',
        'vat_amount',
        'line_total',
        'unit_price_lcy',
        'line_total_lcy',
        'discount_amount_lcy',
        'vat_amount_lcy',
        'location_id',
        'price_source',
        'pricing_master_id',
        'price_record_id',
        'pricing_status',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'vat_percent' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'unit_price_lcy' => 'decimal:2',
        'line_total_lcy' => 'decimal:2',
        'discount_amount_lcy' => 'decimal:2',
        'vat_amount_lcy' => 'decimal:2',
        'pricing_status' => SalesLinePricingStatus::class,
    ];

    protected static function booted()
    {
        static::saving(function ($line) {

            $base = $line->quantity * $line->unit_price;

            $discount = $line->discount_amount
                ?: ($base * ($line->discount_percent / 100));

            // One persisted discount truth: a percentage-derived effective
            // discount is written to the FCY column so its LCY equivalent is
            // derived from the same value rather than a second calculation.
            $line->discount_amount = $discount;

            $afterDiscount = $base - $discount;

            $vat = $afterDiscount * ($line->vat_percent / 100);

            $line->vat_amount = $vat;
            $line->line_total = $afterDiscount + $vat;

            $line->deriveLcyAmounts();
        });

        static::saved(fn ($line) => $line->salesInvoice->refreshTotal());
        static::deleted(fn ($line) => $line->salesInvoice->refreshTotal());
    }

    /**
     * Derive the LCY equivalents of this line's document-currency amounts.
     * Commercial (FCY) values are authoritative and never modified here.
     *
     * Direct Sales Invoice money is stored at the 2-decimal currency scale, so
     * the LCY equivalents use the same scale. An unresolved currency/factor
     * leaves existing LCY values untouched rather than fabricating them.
     */
    public function deriveLcyAmounts(): void
    {
        $invoice = $this->relationLoaded('salesInvoice') ? $this->salesInvoice : $this->salesInvoice()->first();

        if (! $invoice instanceof SalesInvoice) {
            return;
        }

        $derived = app(SalesDocumentMonetaryCalculator::class)->deriveComponents(
            $invoice->currency_code,
            $invoice->currency_factor,
            [
                'unit_price_lcy' => $this->unit_price,
                'line_total_lcy' => $this->line_total,
                'discount_amount_lcy' => $this->discount_amount,
                'vat_amount_lcy' => $this->vat_amount,
            ],
            DecimalPrecision::CURRENCY_SCALE,
        );

        foreach ($derived as $column => $value) {
            if ($value !== null) {
                $this->{$column} = $value;
            }
        }
    }

    public function salesInvoice(): BelongsTo
    {
        return $this->belongsTo(SalesInvoice::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }
}
