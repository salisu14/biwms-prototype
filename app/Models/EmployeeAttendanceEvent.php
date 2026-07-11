<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmployeeAttendanceEvent extends Model
{
    public const TYPE_CLOCK_IN = 'clock_in';

    public const TYPE_CLOCK_OUT = 'clock_out';

    public const TYPE_CORRECTION_CLOCK_IN = 'correction_clock_in';

    public const TYPE_CORRECTION_CLOCK_OUT = 'correction_clock_out';

    protected $fillable = [
        'employee_id',
        'employee_id_card_id',
        'attendance_device_id',
        'attendance_location_id',
        'correction_request_id',
        'event_type',
        'occurred_at',
        'attendance_date',
        'source',
        'card_token_hash',
        'verification_result',
        'ip_address',
        'user_agent',
        'metadata',
        'created_by',
    ];

    protected $hidden = [
        'card_token_hash',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'attendance_date' => 'date',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new \RuntimeException('Raw attendance events are immutable. Create a correction request instead.');
        });

        static::deleting(function (): void {
            throw new \RuntimeException('Raw attendance events cannot be deleted. Create a correction request instead.');
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(EmployeeIdCard::class, 'employee_id_card_id');
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(AttendanceLocation::class, 'attendance_location_id');
    }

    public function correctionRequest(): BelongsTo
    {
        return $this->belongsTo(AttendanceCorrectionRequest::class, 'correction_request_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
