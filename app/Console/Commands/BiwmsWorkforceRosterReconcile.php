<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\EmployeeAttendanceDay;
use App\Models\WorkforceRosterAssignment;
use App\Models\WorkforceRosterPeriod;
use App\Models\WorkforceRotationAssignment;
use App\Services\Hr\WorkforceCoverageService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

#[Signature('biwms:workforce-roster-reconcile {--details : Show detailed diagnostic rows} {--export= : Export findings to a JSON file}')]
#[Description('Report BIWMS workforce roster, coverage, replacement, and attendance schedule consistency issues.')]
class BiwmsWorkforceRosterReconcile extends Command
{
    public function __construct(private readonly WorkforceCoverageService $coverageService)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $findings = $this->findings();

        $this->info('BIWMS Workforce Roster Reconcile');
        $this->line('Mode: report-only. No roster, attendance, or payroll data was changed.');
        $this->line('Findings: '.count($findings));

        foreach (collect($findings)->countBy('classification')->sortKeys() as $classification => $count) {
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
            $this->info('Exported workforce roster reconcile report to '.$path);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{classification: string, severity: string, message: string, remediation: string, context: array<string, mixed>}>
     */
    private function findings(): array
    {
        if (! Schema::hasTable('workforce_roster_assignments')) {
            return [];
        }

        return [
            ...$this->publishedAssignmentValidityFindings(),
            ...$this->overlappingPublishedAssignmentFindings(),
            ...$this->assignmentOutsidePeriodFindings(),
            ...$this->publishedPeriodWithoutAssignmentsFindings(),
            ...$this->coverageFindings(),
            ...$this->replacementLinkFindings(),
            ...$this->attendanceTraceFindings(),
            ...$this->overlappingPrimaryRotationFindings(),
        ];
    }

    private function finding(string $classification, string $severity, string $message, string $remediation, array $context = []): array
    {
        return compact('classification', 'severity', 'message', 'remediation', 'context');
    }

    private function publishedAssignmentValidityFindings(): array
    {
        return WorkforceRosterAssignment::query()
            ->with(['employee', 'shift'])
            ->whereIn('status', [WorkforceRosterAssignment::STATUS_PUBLISHED, WorkforceRosterAssignment::STATUS_ACCEPTED, WorkforceRosterAssignment::STATUS_COMPLETED])
            ->limit(500)
            ->get()
            ->flatMap(function (WorkforceRosterAssignment $assignment) {
                $findings = [];

                if (! $assignment->employee?->is_active) {
                    $findings[] = $this->finding(
                        'published_assignment_inactive_employee',
                        'critical',
                        "Published roster assignment {$assignment->id} references an inactive or missing employee.",
                        'Cancel or replace the assignment, then recalculate unlocked attendance for the affected date.',
                        ['assignment_id' => $assignment->id, 'employee_id' => $assignment->employee_id],
                    );
                }

                if (! $assignment->shift?->is_active) {
                    $findings[] = $this->finding(
                        'published_assignment_inactive_shift',
                        'critical',
                        "Published roster assignment {$assignment->id} references an inactive or missing shift.",
                        'Replace the assignment with an active shift and republish the roster if the period is not closed.',
                        ['assignment_id' => $assignment->id, 'employee_shift_id' => $assignment->employee_shift_id],
                    );
                }

                return $findings;
            })
            ->values()
            ->all();
    }

    private function overlappingPublishedAssignmentFindings(): array
    {
        $findings = [];
        $assignments = WorkforceRosterAssignment::query()
            ->whereIn('status', [WorkforceRosterAssignment::STATUS_PUBLISHED, WorkforceRosterAssignment::STATUS_ACCEPTED, WorkforceRosterAssignment::STATUS_COMPLETED])
            ->whereNotNull('expected_start_at')
            ->whereNotNull('expected_end_at')
            ->orderBy('employee_id')
            ->orderBy('expected_start_at')
            ->get()
            ->groupBy('employee_id');

        foreach ($assignments as $employeeAssignments) {
            foreach ($employeeAssignments as $index => $assignment) {
                $next = $employeeAssignments->get($index + 1);
                if ($next && $assignment->expected_end_at->gt($next->expected_start_at)) {
                    $findings[] = $this->finding(
                        'overlapping_published_roster_assignment',
                        'critical',
                        "Published roster assignments {$assignment->id} and {$next->id} overlap for employee {$assignment->employee_id}.",
                        'Cancel, replace, or reopen the period and resolve the overlapping published assignments.',
                        ['assignment_id' => $assignment->id, 'overlapping_assignment_id' => $next->id],
                    );
                }
            }
        }

        return $findings;
    }

    private function assignmentOutsidePeriodFindings(): array
    {
        return WorkforceRosterAssignment::query()
            ->with('period')
            ->whereHas('period')
            ->limit(500)
            ->get()
            ->filter(fn (WorkforceRosterAssignment $assignment): bool => $assignment->period !== null
                && ($assignment->work_date->lt($assignment->period->date_from) || $assignment->work_date->gt($assignment->period->date_to)))
            ->map(fn (WorkforceRosterAssignment $assignment): array => $this->finding(
                'assignment_outside_roster_period',
                'warning',
                "Roster assignment {$assignment->id} on {$assignment->work_date?->toDateString()} is outside period {$assignment->period?->code}.",
                'Move the assignment into a matching period or correct the period dates before publishing.',
                ['assignment_id' => $assignment->id, 'period_id' => $assignment->workforce_roster_period_id],
            ))
            ->values()
            ->all();
    }

    private function publishedPeriodWithoutAssignmentsFindings(): array
    {
        return WorkforceRosterPeriod::query()
            ->whereIn('status', [WorkforceRosterPeriod::STATUS_PUBLISHED, WorkforceRosterPeriod::STATUS_ACTIVE])
            ->whereDoesntHave('assignments')
            ->limit(500)
            ->get()
            ->map(fn (WorkforceRosterPeriod $period): array => $this->finding(
                'published_period_without_assignments',
                'critical',
                "Published roster period {$period->code} has no assignments.",
                'Reopen the period and regenerate assignments, or cancel the period if it was published by mistake.',
                ['period_id' => $period->id],
            ))
            ->all();
    }

    private function coverageFindings(): array
    {
        $findings = [];

        WorkforceRosterPeriod::query()
            ->whereIn('status', [WorkforceRosterPeriod::STATUS_GENERATED, WorkforceRosterPeriod::STATUS_PUBLISHED, WorkforceRosterPeriod::STATUS_ACTIVE])
            ->limit(100)
            ->get()
            ->each(function (WorkforceRosterPeriod $period) use (&$findings): void {
                $summary = $this->coverageService->summaryForPeriod($period);
                foreach ($summary['missing_critical_roles'] as $missing) {
                    $findings[] = $this->finding(
                        'missing_critical_roster_coverage',
                        'critical',
                        "Roster period {$period->code} is missing critical role {$missing['role_code']} on {$missing['work_date']}.",
                        'Assign a qualified employee or acknowledge the operational coverage gap before go-live.',
                        ['period_id' => $period->id, ...$missing],
                    );
                }
            });

        return $findings;
    }

    private function replacementLinkFindings(): array
    {
        $missingOriginal = WorkforceRosterAssignment::query()
            ->whereIn('assignment_type', [WorkforceRosterAssignment::TYPE_REPLACEMENT, WorkforceRosterAssignment::TYPE_SWAPPED])
            ->whereNull('original_assignment_id')
            ->limit(500)
            ->get()
            ->map(fn (WorkforceRosterAssignment $assignment): array => $this->finding(
                'replacement_assignment_missing_original',
                'warning',
                "Replacement/swap assignment {$assignment->id} is not linked to the original assignment.",
                'Review the replacement history and link or recreate the replacement through the controlled workflow.',
                ['assignment_id' => $assignment->id],
            ))
            ->all();

        $originalStillActive = WorkforceRosterAssignment::query()
            ->whereNotNull('replaced_by_assignment_id')
            ->where('status', '<>', WorkforceRosterAssignment::STATUS_REPLACED)
            ->limit(500)
            ->get()
            ->map(fn (WorkforceRosterAssignment $assignment): array => $this->finding(
                'original_assignment_not_marked_replaced',
                'critical',
                "Original roster assignment {$assignment->id} has a replacement but is still {$assignment->status}.",
                'Mark the original assignment as replaced through the workflow to prevent double scheduling.',
                ['assignment_id' => $assignment->id, 'replacement_id' => $assignment->replaced_by_assignment_id],
            ))
            ->all();

        return [...$missingOriginal, ...$originalStillActive];
    }

    private function attendanceTraceFindings(): array
    {
        if (! Schema::hasColumn('employee_attendance_days', 'workforce_roster_assignment_id')) {
            return [];
        }

        $cancelledUsed = EmployeeAttendanceDay::query()
            ->with('workforceRosterAssignment')
            ->whereNotNull('workforce_roster_assignment_id')
            ->limit(500)
            ->get()
            ->filter(fn (EmployeeAttendanceDay $day): bool => in_array($day->workforceRosterAssignment?->status, [WorkforceRosterAssignment::STATUS_CANCELLED, WorkforceRosterAssignment::STATUS_REPLACED, WorkforceRosterAssignment::STATUS_DECLINED], true))
            ->map(fn (EmployeeAttendanceDay $day): array => $this->finding(
                'attendance_uses_inactive_roster_assignment',
                'warning',
                "Attendance day {$day->id} uses inactive roster assignment {$day->workforce_roster_assignment_id}.",
                'Recalculate the unlocked attendance day so it resolves the current published schedule.',
                ['attendance_day_id' => $day->id, 'assignment_id' => $day->workforce_roster_assignment_id],
            ))
            ->values()
            ->all();

        $missingTrace = WorkforceRosterAssignment::query()
            ->whereIn('status', [WorkforceRosterAssignment::STATUS_PUBLISHED, WorkforceRosterAssignment::STATUS_ACCEPTED, WorkforceRosterAssignment::STATUS_COMPLETED])
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('employee_attendance_days')
                    ->whereColumn('employee_attendance_days.employee_id', 'workforce_roster_assignments.employee_id')
                    ->whereColumn('employee_attendance_days.attendance_date', 'workforce_roster_assignments.work_date');
            })
            ->limit(500)
            ->get()
            ->map(fn (WorkforceRosterAssignment $assignment): array => $this->finding(
                'published_roster_without_attendance_trace',
                'info',
                "Published roster assignment {$assignment->id} has no attendance summary yet.",
                'This is expected for future rosters; recalculate attendance after events are captured or when auditing past dates.',
                ['assignment_id' => $assignment->id],
            ))
            ->all();

        return [...$cancelledUsed, ...$missingTrace];
    }

    private function overlappingPrimaryRotationFindings(): array
    {
        $findings = [];
        $rotations = WorkforceRotationAssignment::query()
            ->where('is_active', true)
            ->where('is_primary', true)
            ->orderBy('employee_id')
            ->orderBy('effective_from')
            ->get()
            ->groupBy('employee_id');

        foreach ($rotations as $employeeRotations) {
            foreach ($employeeRotations as $index => $rotation) {
                $next = $employeeRotations->get($index + 1);
                if (! $next) {
                    continue;
                }

                $rotationEnd = $rotation->effective_to ?? now()->addYears(50);
                if ($rotationEnd->gte($next->effective_from)) {
                    $findings[] = $this->finding(
                        'overlapping_primary_rotation_assignment',
                        'critical',
                        "Primary rotation assignments {$rotation->id} and {$next->id} overlap for employee {$rotation->employee_id}.",
                        'End-date or deactivate one primary rotation so roster generation has a single source.',
                        ['rotation_assignment_id' => $rotation->id, 'next_rotation_assignment_id' => $next->id],
                    );
                }
            }
        }

        return $findings;
    }
}
