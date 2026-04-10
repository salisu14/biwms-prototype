<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\DefaultDimension;
use App\Services\DimensionService;
use App\Enums\EmployeeAssignmentType;

class Employee extends Model
{
    /** @use HasFactory<\Database\Factories\EmployeeFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_number',
        'first_name',
        'last_name',
        'email',
        'phone',
        'job_title',
        'assignment_type',
        'employee_posting_group_id',
        'business_code',
        'factory_code',
        'department_code',
        'is_active',
    ];

    protected $casts = [
        'assignment_type' => EmployeeAssignmentType::class,
        'is_active' => 'boolean',
    ];

    /**
     * Sync local dimension codes to the DefaultDimension table.
     */
    public function syncDefaultDimensions(): void
    {
        app(DimensionService::class)->syncDimensions($this, 'employees', [
            'BUSINESS' => $this->business_code,
            'FACTORY' => $this->factory_code,
            'DEPARTMENT' => $this->department_code,
        ]);
    }

    protected static function booted()
    {
        static::saving(function ($employee) {
            if ($employee->assignment_type === EmployeeAssignmentType::Corporate) {
                $employee->business_code = null;
                $employee->factory_code = null;
            }
        });

        static::saved(function ($employee) {
            $employee->syncDefaultDimensions();
        });
    }

    /**
     * The posting group assigned to this employee.
     */
    public function employeePostingGroup(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(EmployeePostingGroup::class);
    }

    public function compensations()
    {
        return $this->hasMany(EmployeeCompensation::class);
    }

    public function getCurrentBaseSalary()
    {
        return $this->compensations()
            ->where('effective_date', '<=', today())
            ->orderBy('effective_date', 'desc')
            ->value('base_salary') ?? 0;
    }
}
