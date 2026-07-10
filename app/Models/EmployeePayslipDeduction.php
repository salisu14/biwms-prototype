<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePayslipDeduction extends Model
{
    protected $fillable = [
        'payslip_id',
        'pay_code_id',
        'pay_code',
        'description',
        'quantity',
        'rate',
        'amount',
        'display_order',
    ];

    protected $casts = [
        'quantity' => 'decimal:4',
        'rate' => 'decimal:4',
        'amount' => 'decimal:4',
    ];

    public function payslip(): BelongsTo
    {
        return $this->belongsTo(EmployeePayslip::class, 'payslip_id');
    }

    public function payCode(): BelongsTo
    {
        return $this->belongsTo(PayCode::class);
    }
}
