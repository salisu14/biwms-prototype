<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Hr\AttendanceCalculationService;
use Carbon\CarbonPeriod;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LeaveRequest extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_SUBMITTED = 'submitted';

    public const STATUS_MANAGER_APPROVED = 'manager_approved';

    public const STATUS_APPROVED = 'approved';

    public const STATUS_REJECTED = 'rejected';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_POSTED = 'posted';

    public const STATUS_COMPLETED = 'completed';

    /**
     * @var array<int|string, array{employee_id: int|null, start_date: mixed, end_date: mixed, status: mixed}>
     */
    private static array $attendanceOriginalRanges = [];

    protected $fillable = [
        'business_id',
        'request_number',
        'employee_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'start_part',
        'end_part',
        'requested_quantity',
        'approved_quantity',
        'reason',
        'attachment_path',
        'contact_during_leave',
        'handover_notes',
        'status',
        'payroll_review_required',
        'payroll_impact_status',
        'payroll_reference',
        'submitted_at',
        'manager_approved_by',
        'manager_approved_at',
        'hr_approved_by',
        'hr_approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'cancelled_by',
        'cancelled_at',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'requested_quantity' => 'decimal:2',
        'approved_quantity' => 'decimal:2',
        'payroll_review_required' => 'boolean',
        'submitted_at' => 'datetime',
        'manager_approved_at' => 'datetime',
        'hr_approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::updating(function (LeaveRequest $request): void {
            if ($request->isDirty(['employee_id', 'start_date', 'end_date', 'status', 'start_part', 'end_part'])) {
                self::$attendanceOriginalRanges[$request->getKey()] = [
                    'employee_id' => $request->getOriginal('employee_id'),
                    'start_date' => $request->getOriginal('start_date'),
                    'end_date' => $request->getOriginal('end_date'),
                    'status' => $request->getOriginal('status'),
                ];
            }
        });

        static::saved(function (LeaveRequest $request): void {
            if (self::attendanceRelevantStatus($request->status)) {
                self::recalculateAttendanceRange($request->employee_id, $request->start_date, $request->end_date);
            }

            $previous = self::$attendanceOriginalRanges[$request->getKey()] ?? null;
            unset(self::$attendanceOriginalRanges[$request->getKey()]);

            if ($previous !== null && self::attendanceRelevantStatus($previous['status'])) {
                self::recalculateAttendanceRange($previous['employee_id'], $previous['start_date'], $previous['end_date']);
            }
        });

        static::deleted(function (LeaveRequest $request): void {
            if (self::attendanceRelevantStatus($request->status)) {
                self::recalculateAttendanceRange($request->employee_id, $request->start_date, $request->end_date);
            }
        });
    }

    private static function attendanceRelevantStatus(mixed $status): bool
    {
        return in_array($status, [self::STATUS_APPROVED, self::STATUS_POSTED, self::STATUS_COMPLETED], true);
    }

    private static function recalculateAttendanceRange(mixed $employeeId, mixed $startDate, mixed $endDate): void
    {
        if ($employeeId === null || $startDate === null || $endDate === null) {
            return;
        }

        $employee = Employee::query()->find($employeeId);
        if ($employee === null) {
            return;
        }

        foreach (CarbonPeriod::create($startDate, $endDate) as $date) {
            app(AttendanceCalculationService::class)->recalculate($employee, $date);
        }
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function managerApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_approved_by');
    }

    public function hrApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'hr_approved_by');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(EmployeeLeaveLedgerEntry::class);
    }

    public function isApprovedOrPosted(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_POSTED, self::STATUS_COMPLETED], true);
    }
}
