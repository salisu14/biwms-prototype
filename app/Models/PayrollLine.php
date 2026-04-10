<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

    public function document()
    {
        return $this->belongsTo(PayrollDocument::class, 'payroll_document_id');
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function payCode()
    {
        return $this->belongsTo(PayCode::class);
    }
}
