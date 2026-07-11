<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Hr\AttendanceCalculationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class EmployeeWorkScheduleAssignment extends Model
{
    protected $fillable = [
        'employee_id',
        'employee_shift_id',
        'effective_from',
        'effective_until',
        'working_days',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_until' => 'date',
        'working_days' => 'array',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (EmployeeWorkScheduleAssignment $assignment): void {
            if (! $assignment->is_active) {
                return;
            }

            $effectiveFrom = Carbon::parse($assignment->effective_from)->toDateString();
            $effectiveUntil = $assignment->effective_until !== null
                ? Carbon::parse($assignment->effective_until)->toDateString()
                : '9999-12-31';

            $overlapExists = self::query()
                ->where('employee_id', $assignment->employee_id)
                ->where('is_active', true)
                ->when($assignment->exists, fn ($query) => $query->whereKeyNot($assignment->getKey()))
                ->whereDate('effective_from', '<=', $effectiveUntil)
                ->where(function ($query) use ($effectiveFrom): void {
                    $query->whereNull('effective_until')
                        ->orWhereDate('effective_until', '>=', $effectiveFrom);
                })
                ->exists();

            if ($overlapExists) {
                throw new \RuntimeException('Only one active work schedule assignment can apply to an employee on the same date.');
            }
        });

        static::saved(function (EmployeeWorkScheduleAssignment $assignment): void {
            static::recalculateAffectedDays($assignment);
        });

        static::deleted(function (EmployeeWorkScheduleAssignment $assignment): void {
            static::recalculateAffectedDays($assignment);
        });
    }

    private static function recalculateAffectedDays(EmployeeWorkScheduleAssignment $assignment): void
    {
        if ($assignment->employee === null || $assignment->effective_from === null) {
            return;
        }

        $until = $assignment->effective_until ?? now()->addDays(14);

        EmployeeAttendanceDay::query()
            ->where('employee_id', $assignment->employee_id)
            ->whereBetween('attendance_date', [$assignment->effective_from, $until])
            ->pluck('attendance_date')
            ->each(fn ($date): EmployeeAttendanceDay => app(AttendanceCalculationService::class)->recalculate($assignment->employee, $date));
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(EmployeeShift::class, 'employee_shift_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
