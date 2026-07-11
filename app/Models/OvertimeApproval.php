<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Hr\AttendanceCalculationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OvertimeApproval extends Model
{
    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    /**
     * @var array<int|string, array{employee_id: int|null, attendance_date: mixed, status: mixed}>
     */
    private static array $attendanceOriginalDates = [];

    protected $fillable = [
        'employee_id',
        'attendance_day_id',
        'attendance_date',
        'requested_minutes',
        'approved_minutes',
        'reason',
        'status',
        'requested_by',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
    ];

    protected $casts = [
        'attendance_date' => 'date',
        'requested_minutes' => 'integer',
        'approved_minutes' => 'integer',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (OvertimeApproval $approval): void {
            if ($approval->isDirty(['employee_id', 'attendance_date', 'approved_minutes', 'status'])) {
                self::$attendanceOriginalDates[$approval->getKey()] = [
                    'employee_id' => $approval->getOriginal('employee_id'),
                    'attendance_date' => $approval->getOriginal('attendance_date'),
                    'status' => $approval->getOriginal('status'),
                ];
            }
        });

        static::saved(function (OvertimeApproval $approval): void {
            if (self::attendanceRelevantStatus($approval->status)) {
                self::recalculateAttendance($approval->employee_id, $approval->attendance_date);
            }

            $previous = self::$attendanceOriginalDates[$approval->getKey()] ?? null;
            unset(self::$attendanceOriginalDates[$approval->getKey()]);

            if ($previous !== null && self::attendanceRelevantStatus($previous['status'])) {
                self::recalculateAttendance($previous['employee_id'], $previous['attendance_date']);
            }
        });

        static::deleted(function (OvertimeApproval $approval): void {
            if (self::attendanceRelevantStatus($approval->status)) {
                self::recalculateAttendance($approval->employee_id, $approval->attendance_date);
            }
        });
    }

    private static function attendanceRelevantStatus(mixed $status): bool
    {
        return $status === self::STATUS_APPROVED;
    }

    private static function recalculateAttendance(mixed $employeeId, mixed $attendanceDate): void
    {
        if ($employeeId === null || $attendanceDate === null) {
            return;
        }

        $employee = Employee::query()->find($employeeId);
        if ($employee === null) {
            return;
        }

        app(AttendanceCalculationService::class)->recalculate($employee, $attendanceDate);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function attendanceDay(): BelongsTo
    {
        return $this->belongsTo(EmployeeAttendanceDay::class, 'attendance_day_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
