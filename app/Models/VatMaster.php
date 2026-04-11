<?php

// app/Models/VatMaster.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'code',
    'description',
    'purchase_account_id',
    'sales_account_id',
    'percentage',
])]
class VatMaster extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';

    protected $table = 'vat_masters';

    protected $casts = [
        'percentage' => 'decimal:2',
    ];

    /**
     * G/L Account for Purchase VAT (Input)
     */
    public function purchaseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'purchase_account_id');
    }

    /**
     * G/L Account for Sales VAT (Output)
     */
    public function salesAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'sales_account_id');
    }

    /**
     * Check if this is "No VAT" (0%)
     */
    public function getIsNoVatAttribute(): bool
    {
        return $this->percentage == 0;
    }

    /**
     * Format percentage for display
     */
    public function getFormattedPercentageAttribute(): string
    {
        return $this->percentage.'%';
    }

    /**
     * Get full label for dropdowns
     */
    public function getFullLabelAttribute(): string
    {
        return "{$this->code} ({$this->formatted_percentage})";
    }
}
