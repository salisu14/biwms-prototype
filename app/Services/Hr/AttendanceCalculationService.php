<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\AttendanceCorrectionRequest;
use App\Models\AttendanceLedgerEntry;
use App\Models\AttendanceReviewItem;
use App\Models\Employee;
use App\Models\EmployeeAttendanceDay;
use App\Models\EmployeeAttendanceEvent;
use App\Models\EmployeeShift;
use App\Models\EmployeeWorkScheduleAssignment;
use App\Models\LeaveHoliday;
use App\Models\LeaveRequest;
use App\Models\OvertimeApproval;
use App\Models\User;
use App\Services\AuditTrailService;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceCalculationService
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function recalculate(Employee $employee, CarbonInterface|string $attendanceDate, bool $force = false): EmployeeAttendanceDay
    {
        $date = Carbon::parse($attendanceDate)->startOfDay();

        return DB::transaction(function () use ($employee, $date, $force): EmployeeAttendanceDay {
            /** @var Employee $lockedEmployee */
            $lockedEmployee = Employee::query()->lockForUpdate()->findOrFail($employee->id);
            $existingDay = EmployeeAttendanceDay::query()
                ->where('employee_id', $lockedEmployee->id)
                ->whereDate('attendance_date', $date->toDateString())
                ->first();

            if ($existingDay?->isLocked() && ! $force) {
                $this->createPostLockExceptionIfNeeded($existingDay);

                return $existingDay->fresh(['employee', 'shift', 'ledgerEntry']);
            }

            $schedule = $this->scheduleFor($lockedEmployee, $date);
            $shift = $schedule?->shift;
            [$scheduledStartAt, $scheduledEndAt] = $this->scheduledBounds($shift, $date);
            $leaveContext = $this->approvedLeaveContext($lockedEmployee, $date);
            [$scheduledStartAt, $scheduledEndAt] = $this->adjustScheduleForPartialLeave($scheduledStartAt, $scheduledEndAt, $leaveContext);
            $events = EmployeeAttendanceEvent::query()
                ->where('employee_id', $lockedEmployee->id)
                ->whereDate('attendance_date', $date->toDateString())
                ->orderBy('occurred_at')
                ->get();

            $clockIns = $events->whereIn('event_type', [
                EmployeeAttendanceEvent::TYPE_CLOCK_IN,
                EmployeeAttendanceEvent::TYPE_CORRECTION_CLOCK_IN,
            ]);
            $clockOuts = $events->whereIn('event_type', [
                EmployeeAttendanceEvent::TYPE_CLOCK_OUT,
                EmployeeAttendanceEvent::TYPE_CORRECTION_CLOCK_OUT,
            ]);

            $firstClockInAt = $clockIns->first()?->occurred_at;
            $lastClockOutAt = $clockOuts->last()?->occurred_at;
            $isHoliday = LeaveHoliday::query()
                ->whereDate('holiday_date', $date->toDateString())
                ->where('is_active', true)
                ->exists();
            $isWeekend = $shift?->is_weekend ?? in_array($date->dayOfWeekIso, [6, 7], true);
            $onLeave = $leaveContext['fraction'] > 0.0;
            $missingClockOut = $firstClockInAt !== null && $lastClockOutAt === null;

            $breakMinutes = $shift?->break_minutes ?? 0;
            $workedMinutes = $firstClockInAt && $lastClockOutAt
                ? (int) max(0, $firstClockInAt->diffInMinutes($lastClockOutAt) - $breakMinutes)
                : 0;

            $lateMinutes = $firstClockInAt && $scheduledStartAt
                ? (int) max(0, $scheduledStartAt->copy()->addMinutes($shift?->grace_minutes ?? 0)->diffInMinutes($firstClockInAt, false))
                : 0;
            $earlyDepartureMinutes = $lastClockOutAt && $scheduledEndAt
                ? (int) max(0, $lastClockOutAt->diffInMinutes($scheduledEndAt->copy()->subMinutes($shift?->early_departure_grace_minutes ?? 0), false))
                : 0;
            $overtimeMinutes = $lastClockOutAt && $scheduledEndAt
                ? (int) max(0, $scheduledEndAt->copy()->addMinutes($shift?->overtime_threshold_minutes ?? 0)->diffInMinutes($lastClockOutAt, false))
                : 0;

            $approvedOvertimeMinutes = (int) OvertimeApproval::query()
                ->where('employee_id', $lockedEmployee->id)
                ->whereDate('attendance_date', $date->toDateString())
                ->where('status', OvertimeApproval::STATUS_APPROVED)
                ->sum('approved_minutes');

            $status = $this->statusFor($firstClockInAt !== null, $missingClockOut, $lateMinutes, $onLeave, $isHoliday, $isWeekend);
            $payrollReviewRequired = $missingClockOut
                || $onLeave
                || $overtimeMinutes > $approvedOvertimeMinutes
                || $events->contains(fn (EmployeeAttendanceEvent $event): bool => str_starts_with($event->event_type, 'correction_'));

            $day = EmployeeAttendanceDay::query()->updateOrCreate(
                ['employee_id' => $lockedEmployee->id, 'attendance_date' => $date->toDateString()],
                [
                    'employee_shift_id' => $shift?->id,
                    'scheduled_start_at' => $scheduledStartAt,
                    'scheduled_end_at' => $scheduledEndAt,
                    'first_clock_in_at' => $firstClockInAt,
                    'last_clock_out_at' => $lastClockOutAt,
                    'break_minutes' => $breakMinutes,
                    'worked_minutes' => $workedMinutes,
                    'late_minutes' => $lateMinutes,
                    'early_departure_minutes' => $earlyDepartureMinutes,
                    'overtime_minutes' => $overtimeMinutes,
                    'status' => $status,
                    'is_holiday' => $isHoliday,
                    'is_weekend' => $isWeekend,
                    'on_leave' => $onLeave,
                    'missing_clock_out' => $missingClockOut,
                    'payroll_review_required' => $payrollReviewRequired,
                    'payroll_impact_status' => $payrollReviewRequired ? 'pending_review' : 'ready',
                    'calculation_notes' => [
                        'event_count' => $events->count(),
                        'approved_overtime_minutes' => $approvedOvertimeMinutes,
                        'leave_fraction' => $leaveContext['fraction'],
                        'leave_parts' => $leaveContext['parts'],
                        'uses_existing_attendance_ledger' => true,
                    ],
                    'calculated_at' => now(),
                ]
            );

            $ledgerEntry = $this->syncLegacyAttendanceLedger($lockedEmployee, $day);
            if ($ledgerEntry !== null && $day->attendance_ledger_entry_id !== $ledgerEntry->id) {
                $day->forceFill(['attendance_ledger_entry_id' => $ledgerEntry->id])->save();
            }

            return $day->fresh(['employee', 'shift', 'ledgerEntry']);
        });
    }

    public function approveCorrection(AttendanceCorrectionRequest $request, User $approver): EmployeeAttendanceDay
    {
        if ($request->status !== AttendanceCorrectionRequest::STATUS_SUBMITTED) {
            throw new \RuntimeException('Only submitted attendance correction requests can be approved.');
        }

        return DB::transaction(function () use ($request, $approver): EmployeeAttendanceDay {
            $request->forceFill([
                'status' => AttendanceCorrectionRequest::STATUS_APPROVED,
                'approved_by' => $approver->id,
                'approved_at' => now(),
            ])->save();

            foreach ([
                EmployeeAttendanceEvent::TYPE_CORRECTION_CLOCK_IN => $request->requested_clock_in_at,
                EmployeeAttendanceEvent::TYPE_CORRECTION_CLOCK_OUT => $request->requested_clock_out_at,
            ] as $eventType => $occurredAt) {
                if ($occurredAt === null) {
                    continue;
                }

                EmployeeAttendanceEvent::query()->create([
                    'employee_id' => $request->employee_id,
                    'correction_request_id' => $request->id,
                    'event_type' => $eventType,
                    'occurred_at' => $occurredAt,
                    'attendance_date' => $request->attendance_date,
                    'source' => 'correction',
                    'verification_result' => 'approved_correction',
                    'created_by' => $approver->id,
                    'metadata' => ['reason' => $request->reason],
                ]);
            }

            $this->auditTrailService->recordGeneric(
                eventType: 'attendance',
                action: 'attendance_correction_approved',
                auditable: $request,
                userId: $approver->id,
                description: 'Attendance correction request approved.',
                metadata: ['employee_id' => $request->employee_id, 'attendance_date' => $request->attendance_date?->toDateString()],
            );

            return $this->recalculate($request->employee, $request->attendance_date);
        });
    }

    private function scheduleFor(Employee $employee, Carbon $date): ?EmployeeWorkScheduleAssignment
    {
        return EmployeeWorkScheduleAssignment::query()
            ->with('shift')
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->where(function ($query) use ($date): void {
                $query->whereNull('effective_until')
                    ->orWhereDate('effective_until', '>=', $date->toDateString());
            })
            ->orderByDesc('effective_from')
            ->first();
    }

    /**
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function scheduledBounds(?EmployeeShift $shift, Carbon $date): array
    {
        if ($shift === null) {
            return [null, null];
        }

        $start = Carbon::parse($date->toDateString().' '.$shift->start_time);
        $end = Carbon::parse($date->toDateString().' '.$shift->end_time);

        if ($shift->crosses_midnight || $end->lessThanOrEqualTo($start)) {
            $end->addDay();
        }

        return [$start, $end];
    }

    /**
     * @return array{fraction: float, parts: array<int, string>}
     */
    private function approvedLeaveContext(Employee $employee, Carbon $date): array
    {
        $requests = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_POSTED, LeaveRequest::STATUS_COMPLETED])
            ->whereDate('start_date', '<=', $date->toDateString())
            ->whereDate('end_date', '>=', $date->toDateString())
            ->get();

        $fraction = 0.0;
        $parts = [];

        foreach ($requests as $request) {
            $context = $this->leaveContextForRequestDate($request, $date);
            $fraction += $context['fraction'];
            $parts = [...$parts, ...$context['parts']];
        }

        return [
            'fraction' => min(1.0, $fraction),
            'parts' => array_values(array_unique($parts)),
        ];
    }

    /**
     * @return array{fraction: float, parts: array<int, string>}
     */
    private function leaveContextForRequestDate(LeaveRequest $request, Carbon $date): array
    {
        $startDate = $request->start_date?->toDateString();
        $endDate = $request->end_date?->toDateString();
        $dateString = $date->toDateString();

        if ($startDate !== $endDate && $dateString !== $startDate && $dateString !== $endDate) {
            return ['fraction' => 1.0, 'parts' => ['full_day']];
        }

        if ($startDate === $endDate) {
            return $this->leavePartContext($request->start_part, $request->end_part);
        }

        if ($dateString === $startDate) {
            return match ($request->start_part) {
                'afternoon' => ['fraction' => 0.5, 'parts' => ['afternoon']],
                default => ['fraction' => 1.0, 'parts' => ['full_day']],
            };
        }

        if ($dateString === $endDate) {
            return match ($request->end_part) {
                'morning' => ['fraction' => 0.5, 'parts' => ['morning']],
                default => ['fraction' => 1.0, 'parts' => ['full_day']],
            };
        }

        return ['fraction' => 0.0, 'parts' => []];
    }

    /**
     * @return array{fraction: float, parts: array<int, string>}
     */
    private function leavePartContext(?string $startPart, ?string $endPart): array
    {
        if ($startPart === 'morning' && $endPart === 'morning') {
            return ['fraction' => 0.5, 'parts' => ['morning']];
        }

        if ($startPart === 'afternoon' && $endPart === 'afternoon') {
            return ['fraction' => 0.5, 'parts' => ['afternoon']];
        }

        return ['fraction' => 1.0, 'parts' => ['full_day']];
    }

    /**
     * @param  array{fraction: float, parts: array<int, string>}  $leaveContext
     * @return array{0: Carbon|null, 1: Carbon|null}
     */
    private function adjustScheduleForPartialLeave(?Carbon $scheduledStartAt, ?Carbon $scheduledEndAt, array $leaveContext): array
    {
        if ($scheduledStartAt === null || $scheduledEndAt === null || $leaveContext['fraction'] >= 1.0 || $leaveContext['fraction'] <= 0.0) {
            return [$scheduledStartAt, $scheduledEndAt];
        }

        $midpoint = $scheduledStartAt->copy()->addMinutes((int) floor($scheduledStartAt->diffInMinutes($scheduledEndAt) / 2));

        if (in_array('morning', $leaveContext['parts'], true)) {
            $scheduledStartAt = $midpoint;
        }

        if (in_array('afternoon', $leaveContext['parts'], true)) {
            $scheduledEndAt = $midpoint;
        }

        return [$scheduledStartAt, $scheduledEndAt];
    }

    private function statusFor(bool $hasClockIn, bool $missingClockOut, int $lateMinutes, bool $onLeave, bool $isHoliday, bool $isWeekend): string
    {
        if ($onLeave && ! $hasClockIn) {
            return EmployeeAttendanceDay::STATUS_ON_LEAVE;
        }

        if ($isHoliday && ! $hasClockIn) {
            return EmployeeAttendanceDay::STATUS_HOLIDAY;
        }

        if ($isWeekend && ! $hasClockIn) {
            return EmployeeAttendanceDay::STATUS_WEEKEND;
        }

        if ($missingClockOut) {
            return EmployeeAttendanceDay::STATUS_MISSING_CLOCK_OUT;
        }

        if ($hasClockIn && $lateMinutes > 0) {
            return EmployeeAttendanceDay::STATUS_LATE;
        }

        return $hasClockIn ? EmployeeAttendanceDay::STATUS_PRESENT : EmployeeAttendanceDay::STATUS_ABSENT;
    }

    private function syncLegacyAttendanceLedger(Employee $employee, EmployeeAttendanceDay $day): ?AttendanceLedgerEntry
    {
        if ($day->first_clock_in_at === null && $day->last_clock_out_at === null && ! $day->on_leave) {
            return null;
        }

        return AttendanceLedgerEntry::withoutEvents(function () use ($employee, $day): AttendanceLedgerEntry {
            return AttendanceLedgerEntry::query()->updateOrCreate(
                ['employee_id' => $employee->id, 'attendance_date' => $day->attendance_date],
                [
                    'clock_in_at' => $day->first_clock_in_at,
                    'clock_out_at' => $day->last_clock_out_at,
                    'break_minutes' => $day->break_minutes,
                    'worked_hours' => round($day->worked_minutes / 60, 2),
                    'status' => 'OPEN',
                    'created_by' => auth()->id(),
                ]
            );
        });
    }

    private function createPostLockExceptionIfNeeded(EmployeeAttendanceDay $day): void
    {
        if ($day->locked_by_review_period_id === null) {
            return;
        }

        $latestEventAt = EmployeeAttendanceEvent::query()
            ->where('employee_id', $day->employee_id)
            ->whereDate('attendance_date', $day->attendance_date)
            ->max('created_at');

        if ($latestEventAt === null || ($day->locked_at !== null && Carbon::parse($latestEventAt)->lt($day->locked_at))) {
            return;
        }

        AttendanceReviewItem::query()->updateOrCreate(
            [
                'attendance_review_period_id' => $day->locked_by_review_period_id,
                'employee_attendance_day_id' => $day->id,
                'issue_type' => AttendanceReviewItem::ISSUE_MANUAL_OVERRIDE,
            ],
            [
                'employee_id' => $day->employee_id,
                'attendance_date' => $day->attendance_date,
                'severity' => 'critical',
                'review_status' => AttendanceReviewItem::STATUS_PENDING,
                'original_values' => [
                    'locked_at' => $day->locked_at?->toIso8601String(),
                    'latest_event_created_at' => Carbon::parse($latestEventAt)->toIso8601String(),
                ],
                'source_hash' => hash('sha256', implode('|', [$day->id, $latestEventAt])),
                'resolution_notes' => 'Late-arriving attendance event recorded after period lock.',
            ]
        );
    }
}
