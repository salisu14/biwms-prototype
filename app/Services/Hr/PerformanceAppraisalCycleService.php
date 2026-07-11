<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\PerformanceAppraisal;
use App\Models\PerformanceAppraisalCycle;
use App\Models\PerformanceAppraisalCycleAssignment;
use App\Models\PerformanceAppraisalHistory;
use App\Models\PerformanceAppraisalSection;
use App\Models\PerformanceAppraisalTemplate;
use App\Services\AuditTrailService;
use Illuminate\Support\Facades\DB;

class PerformanceAppraisalCycleService
{
    public function __construct(private readonly AuditTrailService $auditTrailService) {}

    public function generateAssignments(PerformanceAppraisalCycle $cycle, PerformanceAppraisalTemplate $template, int $userId, bool $includeInactive = false): PerformanceAppraisalCycle
    {
        return DB::transaction(function () use ($cycle, $template, $userId, $includeInactive): PerformanceAppraisalCycle {
            $locked = PerformanceAppraisalCycle::query()->lockForUpdate()->findOrFail($cycle->id);

            Employee::query()
                ->with('department.manager')
                ->when(! $includeInactive, fn ($query) => $query->where('is_active', true))
                ->orderBy('id')
                ->chunkById(100, function ($employees) use ($locked, $template, $userId): void {
                    foreach ($employees as $employee) {
                        $existing = PerformanceAppraisalCycleAssignment::query()
                            ->where('performance_appraisal_cycle_id', $locked->id)
                            ->where('employee_id', $employee->id)
                            ->first();

                        if ($existing?->eligibility_status === 'excluded') {
                            continue;
                        }

                        PerformanceAppraisalCycleAssignment::query()->updateOrCreate(
                            [
                                'performance_appraisal_cycle_id' => $locked->id,
                                'employee_id' => $employee->id,
                            ],
                            [
                                'department_id' => $employee->department_id,
                                'manager_employee_id' => $employee->department?->manager_id,
                                'appraisal_template_id' => $template->id,
                                'rating_scale_id' => $locked->rating_scale_id,
                                'employment_status_snapshot' => $employee->is_active ? 'active' : 'inactive',
                                'position_snapshot' => $employee->job_title,
                                'department_snapshot' => $employee->department?->name ?? $employee->department_code,
                                'manager_snapshot' => $employee->department?->manager?->full_name,
                                'eligibility_status' => 'eligible',
                                'assigned_by' => $userId,
                                'assigned_at' => now(),
                            ],
                        );
                    }
                });

            $this->auditTrailService->recordGeneric('performance', 'cycle_population_generated', $locked, userId: $userId);

            return $locked->fresh(['assignments']);
        });
    }

    public function generateAppraisals(PerformanceAppraisalCycle $cycle, int $userId): PerformanceAppraisalCycle
    {
        return DB::transaction(function () use ($cycle, $userId): PerformanceAppraisalCycle {
            $locked = PerformanceAppraisalCycle::query()->lockForUpdate()->findOrFail($cycle->id);

            $locked->assignments()
                ->with(['template.sections.items', 'ratingScale.levels'])
                ->where('eligibility_status', 'eligible')
                ->chunkById(50, function ($assignments) use ($locked, $userId): void {
                    foreach ($assignments as $assignment) {
                        $template = $assignment->template;
                        $appraisal = PerformanceAppraisal::query()->firstOrCreate(
                            ['performance_appraisal_cycle_assignment_id' => $assignment->id],
                            [
                                'performance_appraisal_cycle_id' => $locked->id,
                                'employee_id' => $assignment->employee_id,
                                'manager_employee_id' => $assignment->manager_employee_id,
                                'secondary_reviewer_employee_id' => $assignment->secondary_reviewer_employee_id,
                                'appraisal_template_id' => $template->id,
                                'appraisal_template_version' => $template->version,
                                'rating_scale_id' => $assignment->rating_scale_id,
                                'status' => PerformanceAppraisal::STATUS_SELF_ASSESSMENT_PENDING,
                                'template_snapshot' => $this->templateSnapshot($template),
                                'rating_scale_snapshot' => $assignment->ratingScale?->loadMissing('levels')->toArray(),
                            ],
                        );

                        if ($appraisal->sections()->exists()) {
                            continue;
                        }

                        foreach ($template->sections as $templateSection) {
                            $section = PerformanceAppraisalSection::query()->create([
                                'performance_appraisal_id' => $appraisal->id,
                                'source_template_section_id' => $templateSection->id,
                                'code' => $templateSection->code,
                                'title' => $templateSection->title,
                                'section_type' => $templateSection->section_type,
                                'weight_percent' => $templateSection->weight_percent,
                                'sort_order' => $templateSection->sort_order,
                            ]);

                            foreach ($templateSection->items as $item) {
                                $section->items()->create([
                                    'source_template_item_id' => $item->id,
                                    'competency_id' => $item->competency_id,
                                    'code' => $item->code,
                                    'title' => $item->title,
                                    'description' => $item->description,
                                    'measurement_type' => $item->measurement_type,
                                    'weight_percent' => $item->weight_percent,
                                    'expected_value' => $item->target_value,
                                ]);
                            }
                        }

                        PerformanceAppraisalHistory::query()->create([
                            'performance_appraisal_id' => $appraisal->id,
                            'performance_appraisal_cycle_id' => $locked->id,
                            'employee_id' => $appraisal->employee_id,
                            'event_type' => 'appraisal_generated',
                            'changed_by' => $userId,
                            'changed_at' => now(),
                        ]);
                    }
                });

            $this->auditTrailService->recordGeneric('performance', 'appraisals_generated', $locked, userId: $userId);

            return $locked->fresh(['appraisals']);
        });
    }

    public function reopen(PerformanceAppraisalCycle $cycle, int $userId, string $reason): PerformanceAppraisalCycle
    {
        if (blank($reason)) {
            throw new \RuntimeException('A reopen reason is required.');
        }

        return DB::transaction(function () use ($cycle, $userId, $reason): PerformanceAppraisalCycle {
            $locked = PerformanceAppraisalCycle::query()->lockForUpdate()->findOrFail($cycle->id);
            $before = $locked->only(['status', 'reopen_reason']);

            $locked->forceFill([
                'status' => PerformanceAppraisalCycle::STATUS_REOPENED,
                'reopened_by' => $userId,
                'reopened_at' => now(),
                'reopen_reason' => $reason,
            ])->save();

            $this->auditTrailService->recordGeneric('performance', 'cycle_reopened', $locked, userId: $userId, oldValues: $before, newValues: $locked->only(['status', 'reopen_reason']));

            return $locked->fresh();
        });
    }

    /**
     * @return array<string, mixed>
     */
    private function templateSnapshot(PerformanceAppraisalTemplate $template): array
    {
        return $template->loadMissing('sections.items')->toArray();
    }
}
