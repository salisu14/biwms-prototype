<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class WorkforceRosterAssignment extends Model
{
    public const TYPE_REGULAR = 'regular';

    public const TYPE_ROTATION = 'rotation';

    public const TYPE_MANUAL = 'manual';

    public const TYPE_REPLACEMENT = 'replacement';

    public const TYPE_SWAPPED = 'swapped';

    public const TYPE_CALL_IN = 'call_in';

    public const TYPE_OVERTIME = 'overtime';

    public const TYPE_TRAINING = 'training';

    public const TYPE_OFFICIAL_DUTY = 'official_duty';

    public const STATUS_DRAFT = 'draft';

    public const STATUS_SCHEDULED = 'scheduled';

    public const STATUS_PUBLISHED = 'published';

    public const STATUS_ACCEPTED = 'accepted';

    public const STATUS_DECLINED = 'declined';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ABSENT = 'absent';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_REPLACED = 'replaced';

    protected $fillable = [
        'workforce_roster_period_id', 'employee_id', 'work_date', 'employee_shift_id',
        'attendance_location_id', 'department_id', 'work_center_id', 'roster_role_id',
        'assignment_type', 'status', 'source_reference_type', 'source_reference_id',
        'original_assignment_id', 'replaced_by_assignment_id', 'expected_start_at',
        'expected_end_at', 'break_minutes', 'may_create_overtime', 'conflict_status',
        'conflict_details', 'forecast_overtime_minutes', 'assigned_by', 'published_at',
        'cancelled_by', 'cancelled_at', 'cancellation_reason', 'metadata',
    ];

    protected $casts = [
        'work_date' => 'date',
        'expected_start_at' => 'datetime',
        'expected_end_at' => 'datetime',
        'break_minutes' => 'integer',
        'may_create_overtime' => 'boolean',
        'conflict_details' => 'array',
        'forecast_overtime_minutes' => 'integer',
        'published_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (WorkforceRosterAssignment $assignment): void {
            if (! in_array($assignment->status, [self::STATUS_PUBLISHED, self::STATUS_ACCEPTED, self::STATUS_SCHEDULED], true)) {
                return;
            }

            if ($assignment->expected_start_at === null || $assignment->expected_end_at === null) {
                return;
            }

            $overlapExists = self::query()
                ->when($assignment->exists, fn ($query) => $query->whereKeyNot($assignment->getKey()))
                ->where('employee_id', $assignment->employee_id)
                ->whereNotIn('status', [self::STATUS_CANCELLED, self::STATUS_REPLACED, self::STATUS_DECLINED])
                ->where('expected_start_at', '<', $assignment->expected_end_at)
                ->where('expected_end_at', '>', $assignment->expected_start_at)
                ->exists();

            if ($overlapExists) {
                throw new \RuntimeException('Employee already has an overlapping active roster assignment.');
            }
        });
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(WorkforceRosterPeriod::class, 'workforce_roster_period_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(EmployeeShift::class, 'employee_shift_id');
    }

    public function attendanceLocation(): BelongsTo
    {
        return $this->belongsTo(AttendanceLocation::class);
    }

    public function rosterRole(): BelongsTo
    {
        return $this->belongsTo(WorkforceRosterRole::class, 'roster_role_id');
    }

    public function originalAssignment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'original_assignment_id');
    }

    public function replacementAssignment(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replaced_by_assignment_id');
    }

    public function histories(): HasMany
    {
        return $this->hasMany(WorkforceRosterHistory::class);
    }

    public function isActiveForCoverage(): bool
    {
        return ! in_array($this->status, [self::STATUS_CANCELLED, self::STATUS_REPLACED, self::STATUS_DECLINED], true);
    }

    public function scheduledMinutes(): int
    {
        if ($this->expected_start_at === null || $this->expected_end_at === null) {
            return 0;
        }

        return (int) max(0, Carbon::parse($this->expected_start_at)->diffInMinutes($this->expected_end_at) - (int) $this->break_minutes);
    }
}
