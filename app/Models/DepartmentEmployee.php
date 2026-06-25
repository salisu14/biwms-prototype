<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DepartmentEmployee extends Model
{
    use HasFactory;

    protected $table = 'department_employee';

    protected $fillable = [
        'department_id',
        'employee_id',
        'assignment_type',
        'position_title',
        'assignment_date',
        'end_date',
        'allocation_percentage',
        'is_default_dimension',
    ];

    protected $casts = [
        'assignment_date' => 'date',
        'end_date' => 'date',
        'allocation_percentage' => 'decimal:2',
        'is_default_dimension' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function scopeActive($query)
    {
        return $query->whereNull('end_date')
            ->orWhere('end_date', '>=', now());
    }

    public function scopePrimary($query)
    {
        return $query->where('assignment_type', 'primary');
    }

    protected static function booted(): void
    {
        static::saving(function ($assignment) {
            // Prevent duplicate active primary assignments
            if ($assignment->assignment_type === 'primary' && $assignment->is_default_dimension) {
                $exists = static::where('employee_id', $assignment->employee_id)
                    ->where('assignment_type', 'primary')
                    ->where('id', '!=', $assignment->id ?? 0)
                    ->where(function ($q) {
                        $q->whereNull('end_date')
                            ->orWhere('end_date', '>=', now());
                    })
                    ->exists();

                if ($exists) {
                    throw \Exception('Employee already has an active primary assignment');
                }
            }
        });

        static::saved(function ($assignment) {
            // Push department assignment to Employee's native dimension handling if it's the primary/default assignment
            if (($assignment->is_default_dimension || $assignment->assignment_type === 'primary')
                && (! $assignment->end_date || $assignment->end_date >= now()->toDateString())) {

                $department = Department::find($assignment->department_id);
                if ($department) {
                    $assignment->employee->update(['department_code' => $department->department_code]);
                }
            }
        });
    }
}
