<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseQuoteLineArchive extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_quote_archive_id',
        'line_no',
        'type',
        'no',
        'variant_code',
        'description',
        'description_2',
        'quantity',
        'unit_of_measure_code',
        'direct_unit_cost',
        'line_discount_percent',
        'line_discount_amount',
        'line_amount',
        'vat_percent',
        'vat_amount',
        'amount_including_vat',
        'requested_receipt_date',
        'promised_receipt_date',
        'location_code',
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
        'dimensions',
        'line_data',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'direct_unit_cost' => 'decimal:4',
        'line_discount_percent' => 'decimal:2',
        'line_discount_amount' => 'decimal:4',
        'line_amount' => 'decimal:4',
        'vat_percent' => 'decimal:2',
        'vat_amount' => 'decimal:4',
        'amount_including_vat' => 'decimal:4',
        'requested_receipt_date' => 'date',
        'promised_receipt_date' => 'date',
        'dimensions' => 'array',
        'line_data' => 'array',
    ];

    public function purchaseQuoteArchive(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuoteArchive::class);
    }

    /**
     * Get the related item if type is Item
     */
    public function item(): ?Item
    {
        if ($this->type === 'item' && $this->no) {
            return Item::where('item_no', $this->no)->first();
        }

        return null;
    }
}
