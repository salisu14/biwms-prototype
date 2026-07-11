<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AttendancePayrollReviewBatch;
use App\Models\AttendancePayrollReviewBatchLine;
use App\Models\AttendanceReviewItem;
use App\Models\AttendanceReviewPeriod;
use App\Models\EmployeeAttendanceDay;
use App\Models\EmployeeAttendanceEvent;
use App\Models\EmployeeIdCardVerificationLog;
use App\Models\EmployeeWorkScheduleAssignment;
use App\Models\LeaveRequest;
use App\Models\OvertimeApproval;
use App\Models\PayrollLine;
use Carbon\CarbonPeriod;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

#[Signature('biwms:attendance-reconcile {--details : Show detailed findings} {--export= : Export findings to a JSON file}')]
#[Description('Report attendance event, daily summary, leave, overtime, and payroll-review inconsistencies.')]
class BiwmsAttendanceReconcile extends Command
{
    public function handle(): int
    {
        $findings = $this->findings();

        $this->info('BIWMS Attendance Reconcile');
        $this->line('Findings: '.count($findings));

        $summary = collect($findings)->countBy('classification')->sortKeys();
        foreach ($summary as $classification => $count) {
            $this->line(" - {$classification}: {$count}");
        }

        if ($this->option('details')) {
            foreach ($findings as $finding) {
                $this->newLine();
                $this->warn(strtoupper((string) $finding['severity']).' '.$finding['classification']);
                $this->line((string) $finding['message']);
                $this->line('Remediation: '.$finding['remediation']);
            }
        }

        if (is_string($this->option('export')) && $this->option('export') !== '') {
            $path = base_path($this->option('export'));
            File::ensureDirectoryExists(dirname($path));
            File::put($path, json_encode([
                'generated_at' => now()->toIso8601String(),
                'finding_count' => count($findings),
                'findings' => $findings,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $this->info('Exported attendance reconcile report to '.$path);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{classification: string, severity: string, message: string, remediation: string, context: array<string, mixed>}>
     */
    private function findings(): array
    {
        if (! Schema::hasTable('employee_attendance_events') || ! Schema::hasTable('employee_attendance_days')) {
            return [];
        }

        return [
            ...$this->eventsWithoutDailySummary(),
            ...$this->missingClockOutFindings(),
            ...$this->duplicateRapidScanFindings(),
            ...$this->overlappingScheduleFindings(),
            ...$this->approvedLeaveMarkedAbsentFindings(),
            ...$this->invalidCardFindings(),
            ...$this->approvedOvertimeMismatchFindings(),
            ...$this->payrollReviewFlagMismatchFindings(),
            ...$this->lockedDayDriftFindings(),
            ...$this->postLockEventFindings(),
            ...$this->unresolvedBlockingExceptionFindings(),
            ...$this->missingPayrollBatchFindings(),
            ...$this->duplicateActivePayrollBatchFindings(),
            ...$this->approvedOvertimeOmittedFromBatchFindings(),
            ...$this->unreviewedUnpaidAbsenceBatchFindings(),
            ...$this->postedBatchLineWithoutPayrollLineFindings(),
        ];
    }

    private function finding(string $classification, string $severity, string $message, string $remediation, array $context = []): array
    {
        return compact('classification', 'severity', 'message', 'remediation', 'context');
    }

    private function eventsWithoutDailySummary(): array
    {
        return EmployeeAttendanceEvent::query()
            ->with('employee')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('employee_attendance_days')
                    ->whereColumn('employee_attendance_days.employee_id', 'employee_attendance_events.employee_id')
                    ->whereColumn('employee_attendance_days.attendance_date', 'employee_attendance_events.attendance_date');
            })
            ->limit(500)
            ->get()
            ->map(fn (EmployeeAttendanceEvent $event): array => $this->finding(
                'event_without_daily_summary',
                'critical',
                "Attendance event {$event->id} for {$event->employee?->employee_number} on {$event->attendance_date?->toDateString()} has no daily summary.",
                'Run attendance recalculation for the employee/date and review whether the event was imported before summaries existed.',
                ['event_id' => $event->id, 'employee_id' => $event->employee_id, 'attendance_date' => $event->attendance_date?->toDateString()],
            ))
            ->all();
    }

    private function missingClockOutFindings(): array
    {
        return EmployeeAttendanceDay::query()
            ->with('employee')
            ->where('missing_clock_out', true)
            ->limit(500)
            ->get()
            ->map(fn (EmployeeAttendanceDay $day): array => $this->finding(
                'missing_clock_out',
                'warning',
                "{$day->employee?->employee_number} is missing clock-out on {$day->attendance_date?->toDateString()}.",
                'Ask the employee/manager to submit an attendance correction; do not edit raw events.',
                ['attendance_day_id' => $day->id, 'employee_id' => $day->employee_id],
            ))
            ->all();
    }

    private function duplicateRapidScanFindings(): array
    {
        $findings = [];
        $events = EmployeeAttendanceEvent::query()
            ->where('occurred_at', '>=', now()->subDays(30))
            ->orderBy('employee_id')
            ->orderBy('event_type')
            ->orderBy('occurred_at')
            ->get();

        foreach ($events->groupBy(fn (EmployeeAttendanceEvent $event): string => $event->employee_id.'|'.$event->event_type) as $group) {
            $previous = null;
            foreach ($group as $event) {
                if ($previous instanceof EmployeeAttendanceEvent && $previous->occurred_at->diffInSeconds($event->occurred_at) <= 120) {
                    $findings[] = $this->finding(
                        'duplicate_rapid_scan',
                        'warning',
                        "Potential duplicate {$event->event_type} scans: events {$previous->id} and {$event->id}.",
                        'Review the scans. If one is incorrect, submit a correction request instead of deleting raw history.',
                        ['first_event_id' => $previous->id, 'second_event_id' => $event->id],
                    );
                }
                $previous = $event;
            }
        }

        return $findings;
    }

    private function overlappingScheduleFindings(): array
    {
        $findings = [];
        $assignments = EmployeeWorkScheduleAssignment::query()
            ->with(['employee', 'shift'])
            ->where('is_active', true)
            ->orderBy('employee_id')
            ->orderBy('effective_from')
            ->get()
            ->groupBy('employee_id');

        foreach ($assignments as $employeeAssignments) {
            foreach ($employeeAssignments as $index => $assignment) {
                $next = $employeeAssignments->get($index + 1);
                if (! $next) {
                    continue;
                }

                $assignmentEnd = $assignment->effective_until ?? Carbon::parse('9999-12-31');
                if ($assignmentEnd->gte($next->effective_from)) {
                    $findings[] = $this->finding(
                        'overlapping_shift_assignment',
                        'warning',
                        "Overlapping work schedule assignments for {$assignment->employee?->employee_number}.",
                        'Close or deactivate one assignment so each employee/date resolves to one active shift.',
                        ['assignment_id' => $assignment->id, 'next_assignment_id' => $next->id],
                    );
                }
            }
        }

        return $findings;
    }

    private function approvedLeaveMarkedAbsentFindings(): array
    {
        $findings = [];

        LeaveRequest::query()
            ->with('employee')
            ->whereIn('status', [LeaveRequest::STATUS_APPROVED, LeaveRequest::STATUS_POSTED, LeaveRequest::STATUS_COMPLETED])
            ->chunkById(100, function ($requests) use (&$findings): void {
                foreach ($requests as $request) {
                    foreach (CarbonPeriod::create($request->start_date, $request->end_date) as $date) {
                        $day = EmployeeAttendanceDay::query()
                            ->where('employee_id', $request->employee_id)
                            ->whereDate('attendance_date', $date->toDateString())
                            ->where('status', EmployeeAttendanceDay::STATUS_ABSENT)
                            ->first();

                        if ($day) {
                            $findings[] = $this->finding(
                                'approved_leave_marked_absent',
                                'critical',
                                "Approved leave for {$request->employee?->employee_number} is marked absent on {$date->toDateString()}.",
                                'Recalculate attendance for the date and verify leave cancellation/posting state.',
                                ['leave_request_id' => $request->id, 'attendance_day_id' => $day->id],
                            );
                        }
                    }
                }
            });

        return $findings;
    }

    private function invalidCardFindings(): array
    {
        if (! Schema::hasTable('employee_id_card_verification_logs')) {
            return [];
        }

        return EmployeeIdCardVerificationLog::query()
            ->where('result', '!=', 'active')
            ->latest('verified_at')
            ->limit(100)
            ->get()
            ->map(fn (EmployeeIdCardVerificationLog $log): array => $this->finding(
                'invalid_card_used',
                'warning',
                "Invalid employee ID card verification attempt logged at {$log->verified_at?->toDateTimeString()}.",
                'Review the verification log for lost/revoked/expired card use and issue a replacement card if needed.',
                ['verification_log_id' => $log->id, 'card_id' => $log->card_id],
            ))
            ->all();
    }

    private function approvedOvertimeMismatchFindings(): array
    {
        return EmployeeAttendanceDay::query()
            ->with('employee')
            ->where('overtime_minutes', '>', 0)
            ->limit(500)
            ->get()
            ->filter(function (EmployeeAttendanceDay $day): bool {
                $approvedMinutes = (int) OvertimeApproval::query()
                    ->where('employee_id', $day->employee_id)
                    ->whereDate('attendance_date', $day->attendance_date)
                    ->where('status', OvertimeApproval::STATUS_APPROVED)
                    ->sum('approved_minutes');

                return $day->overtime_minutes > $approvedMinutes;
            })
            ->map(fn (EmployeeAttendanceDay $day): array => $this->finding(
                'approved_overtime_mismatch',
                'warning',
                "{$day->employee?->employee_number} has {$day->overtime_minutes} overtime minutes without enough approval on {$day->attendance_date?->toDateString()}.",
                'Create or approve an overtime request, or review whether the shift/overtime threshold is configured correctly.',
                ['attendance_day_id' => $day->id],
            ))
            ->values()
            ->all();
    }

    private function payrollReviewFlagMismatchFindings(): array
    {
        return EmployeeAttendanceDay::query()
            ->with('employee')
            ->where(function ($query): void {
                $query->where('missing_clock_out', true)
                    ->orWhere('on_leave', true)
                    ->orWhere('overtime_minutes', '>', 0);
            })
            ->where('payroll_review_required', false)
            ->limit(500)
            ->get()
            ->map(fn (EmployeeAttendanceDay $day): array => $this->finding(
                'payroll_review_flag_mismatch',
                'warning',
                "{$day->employee?->employee_number} attendance day {$day->attendance_date?->toDateString()} should be flagged for payroll review.",
                'Recalculate the attendance day and verify payroll-impact rules before payroll processing.',
                ['attendance_day_id' => $day->id],
            ))
            ->all();
    }

    private function lockedDayDriftFindings(): array
    {
        if (! Schema::hasColumn('employee_attendance_days', 'locked_snapshot_hash')) {
            return [];
        }

        return EmployeeAttendanceDay::query()
            ->with('employee')
            ->whereNotNull('locked_by_review_period_id')
            ->whereNotNull('locked_snapshot_hash')
            ->limit(500)
            ->get()
            ->filter(fn (EmployeeAttendanceDay $day): bool => $day->locked_snapshot_hash !== $this->snapshotHash($day))
            ->map(fn (EmployeeAttendanceDay $day): array => $this->finding(
                'locked_attendance_day_changed',
                'critical',
                "Locked attendance summary for {$day->employee?->employee_number} on {$day->attendance_date?->toDateString()} no longer matches its lock snapshot.",
                'Do not overwrite locked summaries silently. Reopen the period, review the exception, and regenerate payroll review data if needed.',
                ['attendance_day_id' => $day->id, 'review_period_id' => $day->locked_by_review_period_id],
            ))
            ->values()
            ->all();
    }

    private function postLockEventFindings(): array
    {
        if (! Schema::hasColumn('employee_attendance_days', 'locked_at')) {
            return [];
        }

        return EmployeeAttendanceDay::query()
            ->with('employee')
            ->whereNotNull('locked_at')
            ->limit(500)
            ->get()
            ->filter(function (EmployeeAttendanceDay $day): bool {
                $latestEventAt = EmployeeAttendanceEvent::query()
                    ->where('employee_id', $day->employee_id)
                    ->whereDate('attendance_date', $day->attendance_date)
                    ->max('created_at');

                return $latestEventAt !== null && Carbon::parse($latestEventAt)->gt($day->locked_at);
            })
            ->map(fn (EmployeeAttendanceDay $day): array => $this->finding(
                'event_after_attendance_lock',
                'critical',
                "Attendance event was recorded after lock for {$day->employee?->employee_number} on {$day->attendance_date?->toDateString()}.",
                'Create or review the post-lock attendance exception. Do not recalculate the locked summary without reopening the period.',
                ['attendance_day_id' => $day->id, 'review_period_id' => $day->locked_by_review_period_id],
            ))
            ->values()
            ->all();
    }

    private function unresolvedBlockingExceptionFindings(): array
    {
        if (! Schema::hasTable('attendance_review_items')) {
            return [];
        }

        return AttendanceReviewItem::query()
            ->with('period')
            ->where('severity', 'critical')
            ->whereNotIn('review_status', [AttendanceReviewItem::STATUS_RESOLVED, AttendanceReviewItem::STATUS_WAIVED])
            ->whereHas('period', fn ($query) => $query->whereIn('status', [AttendanceReviewPeriod::STATUS_APPROVED, AttendanceReviewPeriod::STATUS_LOCKED, AttendanceReviewPeriod::STATUS_EXPORTED]))
            ->limit(500)
            ->get()
            ->map(fn (AttendanceReviewItem $item): array => $this->finding(
                'unresolved_exception_in_approved_period',
                'critical',
                "Approved/locked period {$item->period?->code} has unresolved critical exception {$item->issue_type}.",
                'Reopen the period or resolve/waive the exception before payroll review/export.',
                ['review_item_id' => $item->id, 'review_period_id' => $item->attendance_review_period_id],
            ))
            ->all();
    }

    private function missingPayrollBatchFindings(): array
    {
        if (! Schema::hasTable('attendance_payroll_review_batches')) {
            return [];
        }

        return AttendanceReviewPeriod::query()
            ->whereIn('status', [AttendanceReviewPeriod::STATUS_APPROVED, AttendanceReviewPeriod::STATUS_LOCKED, AttendanceReviewPeriod::STATUS_EXPORTED])
            ->whereDoesntHave('payrollBatches', fn ($query) => $query->whereNotIn('status', [AttendancePayrollReviewBatch::STATUS_CANCELLED, AttendancePayrollReviewBatch::STATUS_REVERSED]))
            ->limit(500)
            ->get()
            ->map(fn (AttendanceReviewPeriod $period): array => $this->finding(
                'approved_period_without_payroll_batch',
                'warning',
                "Attendance review period {$period->code} is {$period->status} but has no active payroll review batch.",
                'Generate a payroll review batch when the approved attendance period should feed payroll adjustments.',
                ['review_period_id' => $period->id],
            ))
            ->all();
    }

    private function duplicateActivePayrollBatchFindings(): array
    {
        if (! Schema::hasTable('attendance_payroll_review_batches')) {
            return [];
        }

        return AttendancePayrollReviewBatch::query()
            ->selectRaw('attendance_review_period_id, payroll_period_id, COUNT(*) as batch_count')
            ->whereNotIn('status', [AttendancePayrollReviewBatch::STATUS_CANCELLED, AttendancePayrollReviewBatch::STATUS_REVERSED])
            ->groupBy('attendance_review_period_id', 'payroll_period_id')
            ->havingRaw('COUNT(*) > 1')
            ->get()
            ->map(fn ($row): array => $this->finding(
                'duplicate_active_payroll_batch',
                'critical',
                "Attendance review period {$row->attendance_review_period_id} has duplicate active payroll batches.",
                'Cancel the duplicate batch and keep only the reviewed batch before payroll posting.',
                ['review_period_id' => $row->attendance_review_period_id, 'payroll_period_id' => $row->payroll_period_id],
            ))
            ->all();
    }

    private function approvedOvertimeOmittedFromBatchFindings(): array
    {
        if (! Schema::hasTable('attendance_payroll_review_batch_lines')) {
            return [];
        }

        return AttendanceReviewItem::query()
            ->where('issue_type', AttendanceReviewItem::ISSUE_APPROVED_OVERTIME)
            ->whereIn('review_status', [AttendanceReviewItem::STATUS_RESOLVED, AttendanceReviewItem::STATUS_WAIVED])
            ->limit(500)
            ->get()
            ->filter(fn (AttendanceReviewItem $item): bool => ! AttendancePayrollReviewBatchLine::query()
                ->where('attendance_review_item_id', $item->id)
                ->exists())
            ->map(fn (AttendanceReviewItem $item): array => $this->finding(
                'approved_overtime_missing_from_batch',
                'warning',
                "Resolved approved overtime review item {$item->id} is not present in a payroll review batch.",
                'Regenerate the attendance payroll review batch for the period.',
                ['review_item_id' => $item->id],
            ))
            ->all();
    }

    private function unreviewedUnpaidAbsenceBatchFindings(): array
    {
        if (! Schema::hasTable('attendance_payroll_review_batch_lines')) {
            return [];
        }

        return AttendancePayrollReviewBatchLine::query()
            ->with('reviewItem')
            ->where('line_type', AttendanceReviewItem::ISSUE_UNPAID_ABSENCE)
            ->whereHas('reviewItem', fn ($query) => $query->whereNotIn('review_status', [AttendanceReviewItem::STATUS_RESOLVED, AttendanceReviewItem::STATUS_WAIVED]))
            ->limit(500)
            ->get()
            ->map(fn (AttendancePayrollReviewBatchLine $line): array => $this->finding(
                'unreviewed_unpaid_absence_in_batch',
                'critical',
                "Unpaid absence payroll batch line {$line->id} is linked to an unresolved review item.",
                'Remove/regenerate the batch line after HR resolves or waives the unpaid absence exception.',
                ['batch_line_id' => $line->id, 'review_item_id' => $line->attendance_review_item_id],
            ))
            ->all();
    }

    private function postedBatchLineWithoutPayrollLineFindings(): array
    {
        if (! Schema::hasTable('attendance_payroll_review_batch_lines') || ! Schema::hasColumn('payroll_lines', 'attendance_payroll_review_batch_line_id')) {
            return [];
        }

        return AttendancePayrollReviewBatchLine::query()
            ->where('status', AttendancePayrollReviewBatchLine::STATUS_POSTED)
            ->limit(500)
            ->get()
            ->filter(fn (AttendancePayrollReviewBatchLine $line): bool => ! PayrollLine::query()->where('attendance_payroll_review_batch_line_id', $line->id)->exists())
            ->map(fn (AttendancePayrollReviewBatchLine $line): array => $this->finding(
                'posted_batch_line_without_payroll_line',
                'critical',
                "Posted attendance payroll batch line {$line->id} has no linked payroll line.",
                'Review the batch audit log and repost or reverse through the controlled payroll posting service.',
                ['batch_line_id' => $line->id],
            ))
            ->values()
            ->all();
    }

    private function snapshotHash(EmployeeAttendanceDay $day): string
    {
        return hash('sha256', json_encode($day->only([
            'status', 'worked_minutes', 'late_minutes', 'early_departure_minutes',
            'overtime_minutes', 'missing_clock_out', 'payroll_review_required',
        ]), JSON_THROW_ON_ERROR));
    }
}
