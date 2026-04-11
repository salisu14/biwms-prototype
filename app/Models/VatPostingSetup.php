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
        'vat_percentage',
        'sales_vat_account_id',
        'purchase_vat_account_id',
    ];

    protected $casts = [
        'vat_percentage' => 'decimal:2',
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
}
