<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayrollStatutorySetup extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'personal_relief',
        'insurance_relief_percentage',
        'income_tax_bands',
        'nssf_tier1_limit',
        'nssf_tier1_rate',
        'nssf_tier2_limit',
        'nssf_tier2_rate',
        'nhif_rate',
        'is_active',
    ];

    protected $casts = [
        'income_tax_bands' => 'array',
        'personal_relief' => 'decimal:2',
        'insurance_relief_percentage' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public static function active(): ?self
    {
        return static::where('is_active', true)->first();
    }
}
