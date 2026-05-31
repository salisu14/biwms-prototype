<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeePromotionHistory extends Model
{
    protected $fillable = [
        'employee_id',
        'effective_date',
        'reason_code',
        'old_job_title',
        'new_job_title',
        'old_department_id',
        'new_department_id',
        'old_base_salary',
        'new_base_salary',
        'audit_note',
        'promoted_by',
    ];

    protected $casts = [
        'effective_date' => 'date',
        'old_base_salary' => 'decimal:4',
        'new_base_salary' => 'decimal:4',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function oldDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'old_department_id');
    }

    public function newDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'new_department_id');
    }

    public function promotedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'promoted_by');
    }
}
