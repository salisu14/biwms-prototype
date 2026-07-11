<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EmployeeAttendanceDay;
use App\Models\EmployeeAttendanceEvent;
use App\Models\EmployeeIdCardVerificationLog;
use App\Models\EmployeeWorkScheduleAssignment;
use App\Models\LeaveRequest;
use App\Models\OvertimeApproval;
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
}
