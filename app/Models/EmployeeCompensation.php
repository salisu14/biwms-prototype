<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class EmployeeCompensation extends Model
{
    protected $table = 'employee_compensation';

    protected $fillable = [
        'employee_id',
        'effective_date',
        'base_salary',
        'reason_code',
        'audit_note',
        'job_title',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'base_salary' => 'decimal:4',
    ];

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }
}
