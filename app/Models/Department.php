<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\DepartmentStatus;
use App\Enums\DepartmentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'department_code',
        'name',
        'search_name',
        'parent_department_id',
        'level',
        'department_path',
        'type',
        'status',
        'dimension_value_id',
        'global_dimension_1_code',
        'is_cost_center',
        'is_profit_center',
        'cost_center_code',
        'profit_center_code',
        'manager_id',
        'approver_id',
        'location_code',
        'company_id',
        'annual_budget',
        'budget_utilized',
        'default_expense_account',
        'default_project_code',
        'email',
        'phone',
        'room_location',
        'starting_date',
        'ending_date',
        'notes',
        'blocked_at',
        'blocked_by',
    ];

    protected $casts = [
        'status' => DepartmentStatus::class,
        'type' => DepartmentType::class,
        'is_cost_center' => 'boolean',
        'is_profit_center' => 'boolean',
        'annual_budget' => 'decimal:4',
        'budget_utilized' => 'decimal:4',
        'starting_date' => 'date',
        'ending_date' => 'date',
        'blocked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function ($department) {
            if (empty($department->search_name)) {
                $department->search_name = $department->name;
            }
            // Auto-generate department path
            if ($department->parent_department_id) {
                $parent = static::find($department->parent_department_id);
                $department->level = $parent->level + 1;
                $department->department_path = $parent->department_path.'|'.$department->department_code;
            } else {
                $department->department_path = $department->department_code;
            }
        });

        static::created(function ($department) {
            // Auto-create dimension value if not linked
            if (! $department->dimension_value_id) {
                $dimension = Dimension::where('code', 'DEPARTMENT')->first();
                if ($dimension) {
                    $dimValue = DimensionValue::create([
                        'dimension_id' => $dimension->id,
                        'code' => $department->department_code,
                        'name' => $department->name,
                        'dimension_value_type' => 'standard',
                        'blocked' => ! $department->status->canPost(),
                    ]);
                    $department->updateQuietly(['dimension_value_id' => $dimValue->id]);
                }
            }
        });

        static::updating(function ($department) {
            // Update dimension value if linked
            if ($department->dimension_value_id && $department->isDirty(['name', 'status'])) {
                $department->dimensionValue?->update([
                    'name' => $department->name,
                    'blocked' => ! $department->status->canPost(),
                ]);
            }
        });
    }

    // Relationships
    public function parentDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_department_id');
    }

    public function subDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_department_id');
    }

    public function allSubDepartments(): HasMany
    {
        return $this->hasMany(Department::class, 'parent_department_id')->with('allSubDepartments');
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function employeeAssignments(): HasMany
    {
        return $this->hasMany(DepartmentEmployee::class);
    }

    public function activeEmployees(): HasMany
    {
        return $this->hasMany(Employee::class)->where('status', 'active');
    }

    public function dimensionValue(): BelongsTo
    {
        return $this->belongsTo(DimensionValue::class);
    }

    //    public function company(): BelongsTo
    //    {
    //        return $this->belongsTo(Company::class);
    //    }

    public function blockedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'blocked_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', DepartmentStatus::ACTIVE);
    }

    public function scopePostingAllowed($query)
    {
        return $query->where('status', DepartmentStatus::ACTIVE);
    }

    public function scopeByType($query, DepartmentType $type)
    {
        return $query->where('type', $type);
    }

    public function scopeRootLevel($query)
    {
        return $query->whereNull('parent_department_id');
    }

    public function scopeForCompany($query, int $companyId)
    {
        return $query->where('company_id', $companyId);
    }

    // Accessors
    public function getBudgetRemainingAttribute(): ?float
    {
        if ($this->annual_budget === null) {
            return null;
        }

        return $this->annual_budget - $this->budget_utilized;
    }

    public function getBudgetUtilizationPercentAttribute(): float
    {
        if (! $this->annual_budget) {
            return 0;
        }

        return min(100, ($this->budget_utilized / $this->annual_budget) * 100);
    }

    public function getFullPathAttribute(): string
    {
        return str_replace('|', ' > ', $this->department_path);
    }

    // Methods
    public function isDescendantOf(int $departmentId): bool
    {
        return str_contains($this->department_path, (string) $departmentId);
    }

    public function canDelete(): bool
    {
        return $this->employees()->count() === 0
            && $this->subDepartments()->count() === 0
            && $this->budget_utilized == 0;
    }

    public function block(?string $reason = null): void
    {
        $this->update([
            'status' => DepartmentStatus::INACTIVE,
            'blocked_at' => now(),
            'blocked_by' => auth()->id(),
            'notes' => $reason ? $this->notes."\n[Blocked: {$reason}]" : $this->notes,
        ]);
    }

    public function activate(): void
    {
        $this->update([
            'status' => DepartmentStatus::ACTIVE,
            'blocked_at' => null,
            'blocked_by' => null,
        ]);
    }

    /**
     * Update budget utilization from posted entries
     */
    public function recalculateBudget(): void
    {
        // This would query G/L entries with this department dimension
        $utilized = GlEntry::where('shortcut_dimension_1_code', $this->global_dimension_1_code)
            ->whereHas('account', function ($q) {
                $q->where('account_type', 'expense');
            })
            ->whereYear('posting_date', now()->year)
            ->sum('amount');

        $this->update(['budget_utilized' => abs($utilized)]);
    }
}
