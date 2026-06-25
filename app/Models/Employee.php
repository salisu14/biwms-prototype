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
        'payroll_posting_group_id',
        'business_code',
        'factory_code',
        'department_code',
        'department_id',  // ✅ Ensure this is here!
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

    /**
     * ✅ NEW: Auto-sync department assignment when department_id changes
     */
    public function syncDepartmentAssignment(): void
    {
        // If department was removed, delete all assignments
        if (empty($this->department_id)) {
            \App\Models\DepartmentEmployee::where('employee_id', $this->id)->delete();

            \Log::info("Cleared all department assignments for employee {$this->employee_number}");
            return;
        }

        // Create or update primary assignment
        try {
            \App\Models\DepartmentEmployee::updateOrCreate(
                [
                    'employee_id' => $this->id,
                    'assignment_type' => 'primary', // Manage primary assignments automatically
                ],
                [
                    'department_id' => $this->department_id,
                    'position_title' => $this->job_title ?? 'Staff Member',
                    'assignment_date' => now()->toDateString(),
                    'end_date' => null,
                    'allocation_percentage' => 100,
                    'is_default_dimension' => true,
                ]
            );

            \Log::info("Auto-synced: Employee {$this->employee_number} → Dept ID {$this->department_id}");

        } catch (\Exception $e) {
            \Log::error("Failed to sync department assignment for employee {$this->id}: " . $e->getMessage());

            // Don't throw - allow employee save to succeed even if assignment fails
            report($e);
        }
    }

    protected static function booted(): void
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

            // ✅ NEW: Auto-create/update DepartmentEmployee when department_id changes
            if ($employee->isDirty('department_id')) {
                $employee->syncDepartmentAssignment();
            }
        });
    }

    public function hasUserAccount(): bool
    {
        return $this->user()->exists();
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

    public function payrollPostingGroup(): BelongsTo
    {
        return $this->belongsTo(PayrollPostingGroup::class);
    }

    public function employeePayCodes(): HasMany
    {
        return $this->hasMany(EmployeePayCode::class);
    }

    public function compensations(): Employee|HasMany
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

    public function bankAccounts(): HasMany
    {
        return $this->hasMany(EmployeeBankAccount::class);
    }

    public function ytdBalances(): HasMany
    {
        return $this->hasMany(EmployeeYtdBalance::class);
    }

    public function promotionHistories(): HasMany
    {
        return $this->hasMany(EmployeePromotionHistory::class);
    }

    /**
     * ✅ NEW HELPER: Get all department assignments
     */
    public function departmentAssignments(): HasMany
    {
        return $this->hasMany(DepartmentEmployee::class, 'employee_id');
    }

    /**
     * ✅ NEW HELPER: Get primary department assignment
     */
    public function primaryDepartmentAssignment(): ?DepartmentEmployee
    {
        return $this->departmentAssignments()->where('assignment_type', 'primary')->first();
    }
}
