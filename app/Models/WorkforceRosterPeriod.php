<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Manufacturing\WorkCenter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class WorkforceRosterPeriod extends Model
{
    public const string STATUS_DRAFT = 'draft';

    public const string STATUS_GENERATED = 'generated';

    public const string STATUS_UNDER_REVIEW = 'under_review';

    public const string STATUS_PUBLISHED = 'published';

    public const string STATUS_ACTIVE = 'active';

    public const string STATUS_CLOSED = 'closed';

    public const string STATUS_CANCELLED = 'cancelled';

    public const string STATUS_REOPENED = 'reopened';

    protected $fillable = [
        'business_id',
        'code',
        'name',
        'date_from',
        'date_to',
        'status',
        'department_id',
        'work_center_id',
        'attendance_location_id',
        'generated_by',
        'generated_at',
        'submitted_by',
        'submitted_at',
        'published_by',
        'published_at',
        'closed_by',
        'closed_at',
        'reopened_by',
        'reopened_at',
        'reopen_reason',
        'notes',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'generated_at' => 'datetime',
        'submitted_at' => 'datetime',
        'published_at' => 'datetime',
        'closed_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (WorkforceRosterPeriod $period): void {
            if ($period->date_from !== null && $period->date_to !== null && Carbon::parse($period->date_from)->gt(Carbon::parse($period->date_to))) {
                throw new \RuntimeException('Roster period start date must be on or before the end date.');
            }
        });
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(WorkforceRosterAssignment::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(WorkforceRosterHistory::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function attendanceLocation(): BelongsTo
    {
        return $this->belongsTo(AttendanceLocation::class);
    }

    public function isPublishedLike(): bool
    {
        return in_array($this->status, [self::STATUS_PUBLISHED, self::STATUS_ACTIVE, self::STATUS_CLOSED], true);
    }
}
