<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayrollLine extends Model
{
    protected $fillable = [
        'payroll_document_id',
        'employee_id',
        'pay_code_id',
        'amount',
        'description',
    ];

    protected $casts = [
        'amount' => 'decimal:4',
    ];

    public function document(): BelongsTo
    {
        return $this->belongsTo(PayrollDocument::class, 'payroll_document_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function payCode(): BelongsTo
    {
        return $this->belongsTo(PayCode::class);
    }
}
