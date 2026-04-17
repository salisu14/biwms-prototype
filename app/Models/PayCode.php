<?php

namespace App\Models;

use App\Enums\CalculationMethod;
use App\Enums\PayCodeType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PayCode extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'calculation_method',
        'default_amount',
        'default_percentage',
        'taxable',
        'is_statutory',
        'gl_account_id',
    ];

    protected $casts = [
        'type' => PayCodeType::class,
        'calculation_method' => CalculationMethod::class,
        'default_amount' => 'decimal:4',
        'default_percentage' => 'decimal:2',
        'taxable' => 'boolean',
        'is_statutory' => 'boolean',
    ];


    public function employeePayCodes(): HasMany
    {
        return $this->hasMany(EmployeePayCode::class);
    }

    public function glAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'gl_account_id');
    }
}
