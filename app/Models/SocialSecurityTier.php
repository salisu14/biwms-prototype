<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SocialSecurityTier extends Model
{
    /** @use HasFactory<\Database\Factories\SocialSecurityTierFactory> */
    use HasFactory;

    protected $fillable = [
        'tier_code',
        'code',
        'from_salary',
        'to_salary',
        'employee_rate',
        'employer_rate',
        'max_base',
        'employee_max_amount',
        'employer_max_amount',
    ];

    protected $casts = [
        'from_salary' => 'decimal:2',
        'to_salary' => 'decimal:2',
        'employee_rate' => 'decimal:2',
        'employer_rate' => 'decimal:2',
        'max_base' => 'decimal:2',
        'employee_max_amount' => 'decimal:2',
        'employer_max_amount' => 'decimal:2',
    ];
}
