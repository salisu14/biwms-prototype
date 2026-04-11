<?php

namespace App\Models;

use App\Enums\EmployeeAssignmentType;
use App\Services\DimensionService;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
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
        'department_id',
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
        // Prioritize code from the linked department if available
        $deptCode = $this->department?->department_code ?? $this->department_code;

        app(DimensionService::class)->syncDimensions($this, 'employees', [
            'BUSINESS' => $this->business_code,
            'FACTORY' => $this->factory_code,
            'DEPARTMENT' => $deptCode,
        ]);
    }

    protected static function booted()
    {
        static::saving(function ($employee) {
            if ($employee->assignment_type === EmployeeAssignmentType::Corporate) {
                $employee->business_code = null;
                $employee->factory_code = null;
            }

            // Sync department_code from department_id if changed
            if ($employee->isDirty('department_id') && $employee->department_id) {
                $employee->department_code = $employee->department->department_code;
            }
        });

        static::saved(function ($employee) {
            $employee->syncDefaultDimensions();
        });
    }

    /**
     * The posting group assigned to this employee.
     */
    public function employeePostingGroup(): BelongsTo
    {
        return $this->belongsTo(EmployeePostingGroup::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
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

    /**
     * The User account linked to this employee.
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * Trading identifiers (Salesperson/Purchaser codes) linked to this employee.
     */
    public function salespersonPurchasers(): HasMany
    {
        return $this->hasMany(SalespersonPurchaser::class);
    }
}
