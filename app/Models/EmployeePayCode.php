<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePayCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'employee_id',
        'pay_code_id',
        'amount',
        'percentage',
        'effective_date',
        'end_date',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'percentage' => 'decimal:2',
        'effective_date' => 'date',
        'end_date' => 'date',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payCode(): BelongsTo
    {
        return $this->belongsTo(PayCode::class);
    }
}
