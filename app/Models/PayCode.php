<?php

namespace App\Models;

use App\Enums\CalculationMethod;
use App\Enums\PayCodeType;
use Illuminate\Database\Eloquent\Model;

class PayCode extends Model
{
    protected $fillable = [
        'code',
        'name',
        'type',
        'calculation_method',
        'default_amount',
        'gl_account_id',
    ];

    protected $casts = [
        'type' => PayCodeType::class,
        'calculation_method' => CalculationMethod::class,
        'default_amount' => 'decimal:4',
    ];

    public function glAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'gl_account_id');
    }
}
