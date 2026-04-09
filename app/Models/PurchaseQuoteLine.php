<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseLineType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseQuoteLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'purchase_quote_id',
        'line_no',
        'type',
        'no',
        'variant_code',
        'description',
        'description_2',
        'quantity',
        'outstanding_quantity',
        'unit_of_measure_code',
        'direct_unit_cost',
        'unit_cost_lcy',
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
        'quantity_to_receive',
        'quantity_received',
        'purchase_order_line_id',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'outstanding_quantity' => 'decimal:4',
            'direct_unit_cost' => 'decimal:4',
            'unit_cost_lcy' => 'decimal:4',
            'line_discount_percent' => 'decimal:2',
            'line_discount_amount' => 'decimal:4',
            'line_amount' => 'decimal:4',
            'vat_percent' => 'decimal:2',
            'vat_amount' => 'decimal:4',
            'amount_including_vat' => 'decimal:4',
            'requested_receipt_date' => 'date',
            'promised_receipt_date' => 'date',
            'dimensions' => 'array',
            'type' => PurchaseLineType::class,
        ];
    }

    public function purchaseQuote(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuote::class);
    }

    protected static function booted(): void
    {
        static::saving(function ($line) {
            $line->calculateAmounts();
        });
    }

    public function calculateAmounts(): void
    {
        $discountAmount = ($this->direct_unit_cost * $this->quantity) * ($this->line_discount_percent / 100);
        $this->line_discount_amount = $discountAmount;
        $this->line_amount = ($this->direct_unit_cost * $this->quantity) - $discountAmount;
        $this->vat_amount = $this->line_amount * ($this->vat_percent / 100);
        $this->amount_including_vat = $this->line_amount + $this->vat_amount;
    }
}
