<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmployeeAssignmentType;
use App\Notifications\EmployeeAssignedNotification;
use App\Services\DimensionService;
use Database\Factories\EmployeeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Cache;

class Employee extends Model
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory;

    protected $fillable = [
        'employee_number',
        'first_name',
        'last_name',
        'full_name',
        'email',
        'phone',
        'photo_path',
        'job_title',
        'assignment_type',
        'employee_posting_group_id',
        'payroll_posting_group_id',
        'business_code',
        'factory_code',
        'department_code',
        'department_id',
        'is_active',
        'id_card_number',
        'id_card_issue_date',
        'id_card_expiry_date',
        'id_card_status',
        'id_card_token',
    ];

    protected $casts = [
        'assignment_type' => EmployeeAssignmentType::class,
        'is_active' => 'boolean',
        'id_card_issue_date' => 'date',
        'id_card_expiry_date' => 'date',
    ];

    protected $hidden = [
        'id_card_token',
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

    public function attendanceEvents(): HasMany
    {
        return $this->hasMany(EmployeeAttendanceEvent::class);
    }

    public function attendanceDays(): HasMany
    {
        return $this->hasMany(EmployeeAttendanceDay::class);
    }

    public function attendanceLedgerEntries(): HasMany
    {
        return $this->hasMany(AttendanceLedgerEntry::class);
    }

    public function workScheduleAssignments(): HasMany
    {
        return $this->hasMany(EmployeeWorkScheduleAssignment::class);
    }

    /**
     * Auto-sync department assignment when department_id changes.
     */
    public function syncDepartmentAssignment(?int $previousDeptId = null): void
    {
        // If department was removed, delete all assignments
        if (empty($this->department_id)) {
            DepartmentEmployee::where('employee_id', $this->id)->delete();

            \Log::info("Cleared all department assignments for employee {$this->employee_number}");

            return;
        }

        // Create or update primary assignment
        try {
            DepartmentEmployee::updateOrCreate(
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
            \Log::error("Failed to sync department assignment for employee {$this->id}: ".$e->getMessage());

            // Don't throw - allow employee save to succeed even if assignment fails
            report($e);
        }
    }

    /**
     * Notify department manager about assignment changes.
     */
    public function notifyDepartmentManager(?int $previousDeptId = null): void
    {
        $oldDeptId = $previousDeptId;
        $newDeptId = $this->department_id;

        // Determine action type
        if ($oldDeptId && $newDeptId) {
            $action = 'changed';
            $previousDepartment = Department::find($oldDeptId);
        } elseif (! $oldDeptId && $newDeptId) {
            $action = 'assigned';
            $previousDepartment = null;
        } elseif ($oldDeptId && ! $newDeptId) {
            $action = 'removed';
            $previousDepartment = null;
        } else {
            return; // No change
        }

        // Get current department
        $department = $this->department;
        if (! $department) {
            return;
        }

        // Notify new department manager (if exists and not self)
        if ($department->manager_id && $department->manager_id != $this->id) {
            $manager = $department->manager;

            if ($manager->user) { // Only notify if manager has user account
                $manager->user->notify(new EmployeeAssignedNotification(
                    employee: $this,
                    department: $department,
                    action: $action,
                    previousDepartment: $previousDepartment ?? null,
                ));

                \Log::info("Notified manager {$manager->email} about employee {$this->employee_number} {$action}");
            }
        }

        // If transferred, also notify old department manager
        if ($action === 'changed' && isset($previousDepartment)) {
            if ($previousDepartment->manager_id
                && $previousDepartment->manager_id != $this->id
                && $previousDepartment->manager_id != $department->manager_id) {

                $oldManager = $previousDepartment->manager;

                if ($oldManager?->user) {
                    $oldManager->user->notify(new EmployeeAssignedNotification(
                        employee: $this,
                        department: $previousDepartment, // Their perspective
                        action: 'removed',
                        previousDepartment: null,
                    ));

                    \Log::info("Notified old manager {$oldManager->email} about employee departure");
                }
            }
        }
    }

    protected static function booted(): void
    {
        static::saving(function ($employee) {
            $employee->full_name = trim("{$employee->first_name} {$employee->last_name}");

            if ($employee->assignment_type === EmployeeAssignmentType::Corporate) {
                $employee->business_code = null;
                $employee->factory_code = null;
            }

            // Sync department_code from department_id if changed
            if ($employee->isDirty('department_id') && $employee->department_id) {
                $employee->department_code = $employee->department->department_code;

                // ✅ FIXED: Store in Cache instead of model property!
                // This won't try to save to DB
                Cache::put(
                    "employee_prev_dept_{$employee->id}",
                    $employee->getOriginal('department_id'),
                    now()->addMinutes(5)
                );
            }
        });

        static::saved(function ($employee) {
            $employee->syncDefaultDimensions();

            // ✅ NEW: Auto-create/update DepartmentEmployee when department_id changes
            if ($employee->isDirty('department_id')) {
                // Retrieve from cache (not from model property)
                $previousDeptId = Cache::get("employee_prev_dept_{$employee->id}");

                // Clear cache after use
                Cache::forget("employee_prev_dept_{$employee->id}");

                $employee->syncDepartmentAssignment($previousDeptId);
                $employee->notifyDepartmentManager($previousDeptId);
            }
        });
    }

    public function hasUserAccount(): bool
    {
        return $this->user()->exists();
    }

    public function idCards(): HasMany
    {
        return $this->hasMany(EmployeeIdCard::class);
    }

    public function activeIdCard(): HasOne
    {
        return $this->hasOne(EmployeeIdCard::class)
            ->where('status', EmployeeIdCard::STATUS_ACTIVE)
            ->latestOfMany();
    }

    public function payslips(): HasMany
    {
        return $this->hasMany(EmployeePayslip::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function leaveEntitlements(): HasMany
    {
        return $this->hasMany(EmployeeLeaveEntitlement::class);
    }

    public function leaveLedgerEntries(): HasMany
    {
        return $this->hasMany(EmployeeLeaveLedgerEntry::class);
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
     * Get all department assignments.
     */
    public function departmentAssignments(): HasMany
    {
        return $this->hasMany(DepartmentEmployee::class, 'employee_id');
    }

    /**
     * Get primary department assignment.
     */
    public function primaryDepartmentAssignment(): ?DepartmentEmployee
    {
        return $this->departmentAssignments()->where('assignment_type', 'primary')->first();
    }
}
