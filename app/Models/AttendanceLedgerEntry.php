<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttendanceLedgerEntry extends Model
{
    protected $fillable = [
        'employee_id',
        'attendance_date',
        'clock_in_at',
        'clock_out_at',
        'break_minutes',
        'worked_hours',
        'status',
        'approved_by',
        'approved_at',
        'approval_note',
        'created_by',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'clock_in_at' => 'datetime',
        'clock_out_at' => 'datetime',
        'break_minutes' => 'integer',
        'worked_hours' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::saving(function (AttendanceLedgerEntry $entry): void {
            if ($entry->clock_in_at && $entry->clock_out_at) {
                $minutes = $entry->clock_in_at->diffInMinutes($entry->clock_out_at);
                $netMinutes = max(0, $minutes - (int) $entry->break_minutes);
                $entry->worked_hours = round($netMinutes / 60, 2);
            } else {
                $entry->worked_hours = 0;
            }
        });

        static::updating(function (AttendanceLedgerEntry $entry): void {
            // ANTI-FRAUD: If the employee already clocked out, prevent changing clock_in or break_minutes.
            // This stops users from clocking out, then editing the form to extend their hours.
            if ($entry->getOriginal('clock_out_at') !== null) {
                if ($entry->isDirty('clock_in_at') || $entry->isDirty('break_minutes')) {
                    throw new \Exception('Attendance times cannot be modified after clocking out. HR must reverse the entry first.');
                }
            }

            // ANTI-FRAUD: Prevent modifying times if the entry is already Approved
            if ($entry->getOriginal('status') === 'APPROVED' && $entry->isDirty(['clock_in_at', 'clock_out_at', 'break_minutes'])) {
                throw new \Exception('Cannot modify time fields on an approved attendance entry.');
            }
        });
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
