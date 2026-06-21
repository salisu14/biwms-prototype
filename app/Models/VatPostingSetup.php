<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VatPostingSetup extends Model
{
    use HasFactory;

    protected $fillable = [
        'vat_business_posting_group_id',
        'vat_product_posting_group_id',
        'vat_percent',
        'vat_calculation_type',
        'sales_vat_account_id',
        'purchase_vat_account_id',
        'reverse_charge_vat_account_id',
        'vat_identifier',
        'blocked',
        'eu_service'
    ];

    protected $casts = [
        'vat_percent' => 'decimal:2',
    ];

    public function vatBusinessPostingGroup(): BelongsTo
    {
        return $this->belongsTo(VatBusinessPostingGroup::class);
    }

    public function vatProductPostingGroup(): BelongsTo
    {
        return $this->belongsTo(VatProductPostingGroup::class);
    }

    public function salesVatAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'sales_vat_account_id');
    }

    public function purchaseVatAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'purchase_vat_account_id');
    }

    public function reverseChargeVatAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'reverse_charge_vat_account_id');
    }

    // Get VAT setup by combination (core Business Central logic)
    public static function getSetup($businessGroupCode, $productGroupCode)
    {
        return self::whereHas('vatBusinessPostingGroup', function($q) use ($businessGroupCode) {
            $q->where('code', $businessGroupCode);
        })
            ->whereHas('vatProductPostingGroup', function($q) use ($productGroupCode) {
                $q->where('code', $productGroupCode);
            })
            ->where('blocked', false)
            ->first();
    }
}
