<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\WorkforceRosterAssignment;
use App\Models\WorkforceRosterPeriod;
use App\Models\WorkforceRotationAssignment;
use App\Models\WorkforceRotationTemplateDay;
use App\Services\AuditTrailService;
use Carbon\CarbonPeriod;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class WorkforceRosterGenerationService
{
    public function __construct(
        private readonly WorkforceScheduleResolverService $resolver,
        private readonly WorkforceScheduleValidationService $validator,
        private readonly AuditTrailService $auditTrailService,
    ) {}

    /**
     * @return array{assignments: array<int, array<string, mixed>>, rest_days: array<int, array<string, mixed>>, blocking: array<int, array<string, mixed>>, warnings: array<int, array<string, mixed>>}
     */
    public function preview(WorkforceRosterPeriod $period): array
    {
        $assignments = [];
        $restDays = [];
        $blocking = [];
        $warnings = [];

        $employees = $this->employeesForPeriod($period);

        foreach ($employees as $employee) {
            $rotation = $this->rotationFor($employee, $period->date_from, $period->date_to);
            if (! $rotation) {
                continue;
            }

            foreach (CarbonPeriod::create($period->date_from, $period->date_to) as $date) {
                $templateDay = $this->templateDayFor($rotation, Carbon::parse($date));
                if (! $templateDay instanceof WorkforceRotationTemplateDay || $templateDay->is_rest_day) {
                    $restDays[] = ['employee_id' => $employee->id, 'work_date' => $date->toDateString()];

                    continue;
                }

                [$start, $end] = $this->resolver->bounds($templateDay->shift, Carbon::parse($date));
                $payload = [
                    'workforce_roster_period_id' => $period->id,
                    'employee_id' => $employee->id,
                    'work_date' => $date->toDateString(),
                    'employee_shift_id' => $templateDay->employee_shift_id,
                    'attendance_location_id' => $templateDay->attendance_location_id ?? $rotation->attendance_location_id ?? $period->attendance_location_id,
                    'department_id' => $period->department_id ?? $employee->department_id,
                    'work_center_id' => $templateDay->work_center_id ?? $rotation->work_center_id ?? $period->work_center_id,
                    'roster_role_id' => $templateDay->roster_role_id,
                    'assignment_type' => WorkforceRosterAssignment::TYPE_ROTATION,
                    'status' => WorkforceRosterAssignment::STATUS_DRAFT,
                    'expected_start_at' => $start,
                    'expected_end_at' => $end,
                    'break_minutes' => (int) ($templateDay->shift?->break_minutes ?? 0),
                    'may_create_overtime' => false,
                    'metadata' => ['rotation_assignment_id' => $rotation->id],
                ];

                $validation = $this->validator->validateAssignment($payload);
                foreach ($validation['blocking'] as $conflict) {
                    $blocking[] = ['employee_id' => $employee->id, 'work_date' => $date->toDateString(), 'conflict' => $conflict];
                }
                foreach ($validation['warnings'] as $warning) {
                    $warnings[] = ['employee_id' => $employee->id, 'work_date' => $date->toDateString(), 'warning' => $warning];
                }

                $payload['conflict_status'] = $validation['blocking'] !== [] ? 'blocking' : ($validation['warnings'] !== [] ? 'warning' : null);
                $payload['conflict_details'] = $validation;
                $assignments[] = $payload;
            }
        }

        return [
            'assignments' => $assignments,
            'rest_days' => $restDays,
            'blocking' => $blocking,
            'warnings' => $warnings,
        ];
    }

    public function generate(WorkforceRosterPeriod $period, int $userId): WorkforceRosterPeriod
    {
        return DB::transaction(function () use ($period, $userId): WorkforceRosterPeriod {
            $locked = WorkforceRosterPeriod::query()->lockForUpdate()->findOrFail($period->id);
            if ($locked->isPublishedLike()) {
                throw new \RuntimeException('Published roster periods cannot be regenerated.');
            }

            $preview = $this->preview($locked);
            foreach ($preview['assignments'] as $payload) {
                WorkforceRosterAssignment::query()->updateOrCreate(
                    [
                        'workforce_roster_period_id' => $locked->id,
                        'employee_id' => $payload['employee_id'],
                        'work_date' => $payload['work_date'],
                        'assignment_type' => WorkforceRosterAssignment::TYPE_ROTATION,
                    ],
                    $payload + ['assigned_by' => $userId],
                );
            }

            $locked->forceFill([
                'status' => WorkforceRosterPeriod::STATUS_GENERATED,
                'generated_by' => $userId,
                'generated_at' => now(),
            ])->save();

            $this->auditTrailService->recordGeneric('workforce_roster', 'roster_generated', $locked, userId: $userId, metadata: [
                'assignment_count' => count($preview['assignments']),
                'blocking_count' => count($preview['blocking']),
                'warning_count' => count($preview['warnings']),
            ]);

            return $locked->fresh(['assignments']);
        });
    }

    private function employeesForPeriod(WorkforceRosterPeriod $period)
    {
        return Employee::query()
            ->where('is_active', true)
            ->when($period->department_id, fn ($query) => $query->where('department_id', $period->department_id))
            ->orderBy('employee_number')
            ->get();
    }

    private function rotationFor(Employee $employee, Carbon $from, Carbon $to): ?WorkforceRotationAssignment
    {
        return WorkforceRotationAssignment::query()
            ->with('template.days.shift')
            ->where('employee_id', $employee->id)
            ->where('is_active', true)
            ->where('is_primary', true)
            ->whereDate('effective_from', '<=', $to)
            ->where(fn ($query) => $query->whereNull('effective_to')->orWhereDate('effective_to', '>=', $from))
            ->orderByDesc('effective_from')
            ->first();
    }

    private function templateDayFor(WorkforceRotationAssignment $rotation, Carbon $date): ?WorkforceRotationTemplateDay
    {
        $cycleLength = max(1, (int) $rotation->template->cycle_length_days);
        $daysSinceStart = Carbon::parse($rotation->cycle_start_date)->diffInDays($date);
        $sequence = ((($rotation->starting_sequence_day - 1) + $daysSinceStart) % $cycleLength) + 1;

        return $rotation->template->days->firstWhere('sequence_day', $sequence);
    }
}
