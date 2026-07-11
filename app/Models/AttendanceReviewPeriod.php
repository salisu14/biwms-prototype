<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AttendanceReviewPeriod extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_OPEN = 'open';

    public const STATUS_UNDER_REVIEW = 'under_review';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_LOCKED = 'locked';

    public const STATUS_EXPORTED = 'exported';

    public const STATUS_REOPENED = 'reopened';

    protected $fillable = [
        'business_id', 'code', 'name', 'date_from', 'date_to', 'status',
        'opened_by', 'opened_at', 'submitted_by', 'submitted_at', 'approved_by',
        'approved_at', 'locked_by', 'locked_at', 'reopened_by', 'reopened_at',
        'reopen_reason', 'notes',
    ];

    protected $casts = [
        'date_from' => 'date',
        'date_to' => 'date',
        'opened_at' => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'locked_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(AttendanceReviewItem::class);
    }

    public function payrollBatches(): HasMany
    {
        return $this->hasMany(AttendancePayrollReviewBatch::class);
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }
}
