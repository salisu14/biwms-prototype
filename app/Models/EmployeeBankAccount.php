<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeBankAccount extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeBankAccountFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'bank_code',
        'bank_name',
        'account_number',
        'account_name',
        'is_primary',
        'payment_method',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
