<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\AttendanceReviewItem;
use App\Models\AttendanceReviewPeriod;
use App\Models\EmployeeAttendanceDay;
use App\Models\EmployeeAttendanceEvent;
use App\Models\OvertimeApproval;
use App\Models\User;
use App\Services\AuditTrailService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AttendanceExceptionReviewService
{
    public function __construct(
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function generateForPeriod(AttendanceReviewPeriod $period): Collection
    {
        return DB::transaction(function () use ($period): Collection {
            $lockedPeriod = AttendanceReviewPeriod::query()->lockForUpdate()->findOrFail($period->id);
            $items = collect();

            EmployeeAttendanceDay::query()
                ->with('employee')
                ->whereBetween('attendance_date', [$lockedPeriod->date_from, $lockedPeriod->date_to])
                ->orderBy('attendance_date')
                ->chunkById(200, function ($days) use ($lockedPeriod, &$items): void {
                    foreach ($days as $day) {
                        foreach ($this->issuesForDay($day) as $issue) {
                            $items->push($this->upsertIssue($lockedPeriod, $day, $issue));
                        }
                    }
                });

            return $items;
        });
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function issuesForDay(EmployeeAttendanceDay $day): array
    {
        $issues = [];

        if ($day->missing_clock_out) {
            $issues[] = $this->issue(AttendanceReviewItem::ISSUE_MISSING_CLOCK_OUT, 'critical', $day);
        }

        if ($day->status === EmployeeAttendanceDay::STATUS_ABSENT) {
            $issues[] = $this->issue(AttendanceReviewItem::ISSUE_ABSENT, 'critical', $day);
        }

        if ($day->status === 'unpaid_absence') {
            $issues[] = $this->issue(AttendanceReviewItem::ISSUE_UNPAID_ABSENCE, 'critical', $day);
        }

        if ($day->late_minutes > 0) {
            $issues[] = $this->issue(AttendanceReviewItem::ISSUE_LATE, 'warning', $day);
        }

        if ($day->early_departure_minutes > 0) {
            $issues[] = $this->issue(AttendanceReviewItem::ISSUE_EARLY_DEPARTURE, 'warning', $day);
        }

        if ($day->overtime_minutes > 0) {
            $approvedMinutes = (int) OvertimeApproval::query()
                ->where('employee_id', $day->employee_id)
                ->whereDate('attendance_date', $day->attendance_date)
                ->where('status', OvertimeApproval::STATUS_APPROVED)
                ->sum('approved_minutes');

            $issues[] = $this->issue(
                $approvedMinutes > 0 ? AttendanceReviewItem::ISSUE_APPROVED_OVERTIME : AttendanceReviewItem::ISSUE_UNAPPROVED_OVERTIME,
                $approvedMinutes > 0 ? 'info' : 'warning',
                $day,
                ['approved_overtime_minutes' => $approvedMinutes]
            );
        }

        if (($day->calculation_notes['leave_fraction'] ?? 0) > 0 && ($day->worked_minutes > 0)) {
            $issues[] = $this->issue(AttendanceReviewItem::ISSUE_HALF_DAY_LEAVE_VARIANCE, 'warning', $day);
        }

        $hasCorrectionEvent = EmployeeAttendanceEvent::query()
            ->where('employee_id', $day->employee_id)
            ->whereDate('attendance_date', $day->attendance_date)
            ->whereIn('event_type', [EmployeeAttendanceEvent::TYPE_CORRECTION_CLOCK_IN, EmployeeAttendanceEvent::TYPE_CORRECTION_CLOCK_OUT])
            ->exists();

        if ($hasCorrectionEvent) {
            $issues[] = $this->issue(AttendanceReviewItem::ISSUE_ATTENDANCE_CORRECTION, 'warning', $day);
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, mixed>
     */
    private function issue(string $type, string $severity, EmployeeAttendanceDay $day, array $extra = []): array
    {
        $values = [
            'status' => $day->status,
            'worked_minutes' => $day->worked_minutes,
            'late_minutes' => $day->late_minutes,
            'early_departure_minutes' => $day->early_departure_minutes,
            'overtime_minutes' => $day->overtime_minutes,
            'missing_clock_out' => $day->missing_clock_out,
            'expected_minutes' => $this->expectedMinutes($day),
            ...$extra,
        ];

        return [
            'issue_type' => $type,
            'severity' => $severity,
            'original_values' => $values,
            'source_hash' => hash('sha256', json_encode($values, JSON_THROW_ON_ERROR)),
        ];
    }

    private function expectedMinutes(EmployeeAttendanceDay $day): int
    {
        if ($day->scheduled_start_at === null || $day->scheduled_end_at === null) {
            return 480;
        }

        return (int) max(0, $day->scheduled_start_at->diffInMinutes($day->scheduled_end_at) - (int) $day->break_minutes);
    }

    /**
     * @param  array<string, mixed>  $issue
     */
    private function upsertIssue(AttendanceReviewPeriod $period, EmployeeAttendanceDay $day, array $issue): AttendanceReviewItem
    {
        $item = AttendanceReviewItem::query()->firstOrNew([
            'attendance_review_period_id' => $period->id,
            'employee_attendance_day_id' => $day->id,
            'issue_type' => $issue['issue_type'],
        ]);

        if ($item->exists && in_array($item->review_status, [AttendanceReviewItem::STATUS_RESOLVED, AttendanceReviewItem::STATUS_WAIVED], true) && $item->source_hash === $issue['source_hash']) {
            return $item;
        }

        $item->fill([
            'employee_id' => $day->employee_id,
            'attendance_date' => $day->attendance_date,
            'severity' => $issue['severity'],
            'review_status' => AttendanceReviewItem::STATUS_PENDING,
            'original_values' => $issue['original_values'],
            'source_hash' => $issue['source_hash'],
        ])->save();

        return $item;
    }

    public function resolve(AttendanceReviewItem $item, User $user, string $resolutionType, ?string $notes = null): AttendanceReviewItem
    {
        $item->forceFill([
            'review_status' => AttendanceReviewItem::STATUS_RESOLVED,
            'resolution_type' => $resolutionType,
            'resolution_notes' => $notes,
            'resolved_by' => $user->id,
            'resolved_at' => now(),
        ])->save();

        $this->auditTrailService->recordGeneric('attendance_review', 'review_item_resolved', $item, userId: $user->id, metadata: ['issue_type' => $item->issue_type]);

        return $item->fresh();
    }

    public function waive(AttendanceReviewItem $item, User $user, string $notes): AttendanceReviewItem
    {
        $item->forceFill([
            'review_status' => AttendanceReviewItem::STATUS_WAIVED,
            'resolution_type' => 'waived',
            'resolution_notes' => $notes,
            'resolved_by' => $user->id,
            'resolved_at' => now(),
        ])->save();

        $this->auditTrailService->recordGeneric('attendance_review', 'review_item_waived', $item, userId: $user->id, metadata: ['issue_type' => $item->issue_type]);

        return $item->fresh();
    }
}
