<?php

namespace App\Models;

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
        'location_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'vat_percent' => 'decimal:2',
        'vat_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    protected static function booted()
    {
        static::saving(function ($line) {

            $base = $line->quantity * $line->unit_price;

            $discount = $line->discount_amount
                ?: ($base * ($line->discount_percent / 100));

            $afterDiscount = $base - $discount;

            $vat = $afterDiscount * ($line->vat_percent / 100);

            $line->vat_amount = $vat;
            $line->line_total = $afterDiscount + $vat;
        });

        static::saved(fn ($line) => $line->salesInvoice->refreshTotal());
        static::deleted(fn ($line) => $line->salesInvoice->refreshTotal());
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
