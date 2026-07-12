<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Manufacturing\WorkCenter;
use App\Models\Manufacturing\WorkCenterGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class WorkforceRotationAssignment extends Model
{
    protected $fillable = [
        'workforce_rotation_template_id', 'employee_id', 'effective_from',
        'effective_to', 'cycle_start_date', 'starting_sequence_day',
        'attendance_location_id', 'work_center_id', 'is_primary', 'is_active', 'assigned_by',
    ];

    protected $casts = [
        'effective_from' => 'date',
        'effective_to' => 'date',
        'cycle_start_date' => 'date',
        'starting_sequence_day' => 'integer',
        'is_primary' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (WorkforceRotationAssignment $assignment): void {
            if (! $assignment->is_active || ! $assignment->is_primary) {
                return;
            }

            $from = Carbon::parse($assignment->effective_from)->toDateString();
            $to = $assignment->effective_to?->toDateString() ?? '9999-12-31';

            $overlapExists = self::query()
                ->when($assignment->exists, fn ($query) => $query->whereKeyNot($assignment->getKey()))
                ->where('employee_id', $assignment->employee_id)
                ->where('is_active', true)
                ->where('is_primary', true)
                ->whereDate('effective_from', '<=', $to)
                ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))
                ->exists();

            if ($overlapExists) {
                throw new \RuntimeException('Only one active primary rotation can apply to an employee/date.');
            }
        });
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'attendance_location_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function AttendanceLocation(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'attendance_location_id');
    }

    public function workCenterGroup(): BelongsTo
    {
        return $this->belongsTo(WorkCenterGroup::class, 'work_center_group_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(WorkforceRotationTemplate::class, 'workforce_rotation_template_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
