<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Model for FA Insurance Policies
 */
class FAInsurancePolicy extends Model
{
    use HasFactory;

    protected $fillable = [
        'fixed_asset_id', 'policy_no', 'insurance_vendor_id',
        'coverage_amount', 'premium_amount', 'start_date', 'expiry_date', 'status',
    ];

    protected $casts = [
        'coverage_amount' => 'decimal:4',
        'premium_amount' => 'decimal:4',
        'start_date' => 'date',
        'expiry_date' => 'date',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'fixed_asset_id');
    }

    public function insuranceVendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'insurance_vendor_id');
    }
}
