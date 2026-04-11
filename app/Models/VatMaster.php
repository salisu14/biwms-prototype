<?php

// app/Models/VatMaster.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'code',
    'description',
    'purchase_account_number',
    'sales_account_number',
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
