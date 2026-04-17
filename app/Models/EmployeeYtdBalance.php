<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeYtdBalance extends Model
{
    protected $fillable = [
        'employee_id',
        'year',
        'gross_earnings',
        'tax_deducted',
        'social_security_employee',
        'social_security_employer',
        'net_paid',
    ];

    protected $casts = [
        'gross_earnings' => 'decimal:2',
        'tax_deducted' => 'decimal:2',
        'social_security_employee' => 'decimal:2',
        'social_security_employer' => 'decimal:2',
        'net_paid' => 'decimal:2',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
