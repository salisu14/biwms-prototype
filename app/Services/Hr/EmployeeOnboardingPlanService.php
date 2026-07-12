<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentOffer;
use App\Models\RecruitmentOnboardingPlan;
use App\Models\RecruitmentOnboardingTask;
use App\Models\RecruitmentOnboardingTemplate;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class EmployeeOnboardingPlanService
{
    public function generate(Employee $employee, RecruitmentOnboardingTemplate $template, Carbon|string $startDate, ?RecruitmentApplication $application = null, ?RecruitmentOffer $offer = null, ?int $userId = null): RecruitmentOnboardingPlan
    {
        return DB::transaction(function () use ($employee, $template, $startDate, $application, $offer): RecruitmentOnboardingPlan {
            $start = Carbon::parse($startDate);
            $plan = RecruitmentOnboardingPlan::query()->create([
                'employee_id' => $employee->id,
                'recruitment_application_id' => $application?->id,
                'recruitment_offer_id' => $offer?->id,
                'onboarding_template_id' => $template->id,
                'onboarding_template_version' => $template->version,
                'start_date' => $start,
                'target_completion_date' => $start->copy()->addDays(max(0, (int) $template->tasks()->max('due_offset_days'))),
                'status' => 'active',
                'manager_employee_id' => $offer?->reporting_manager_employee_id ?? $application?->hiring_manager_employee_id,
            ]);

            foreach ($template->tasks()->orderBy('sort_order')->get() as $task) {
                $plan->tasks()->create([
                    'source_template_task_id' => $task->id,
                    'code' => $task->code,
                    'title' => $task->title,
                    'description' => $task->description,
                    'task_category' => $task->task_category,
                    'assigned_user_id' => $task->responsible_user_id,
                    'assigned_employee_id' => $task->responsible_role_type === 'employee' ? $employee->id : null,
                    'due_date' => $start->copy()->addDays((int) $task->due_offset_days),
                    'status' => 'pending',
                    'is_required' => $task->is_required,
                    'requires_attachment' => $task->requires_attachment,
                    'requires_approval' => $task->requires_approval,
                ]);
            }

            $this->recalculateProgress($plan);

            return $plan->fresh(['tasks']);
        });
    }

    public function completeTask(RecruitmentOnboardingTask $task, int $userId, ?string $evidencePath = null): RecruitmentOnboardingTask
    {
        $task->update([
            'status' => $task->requires_approval ? 'in_progress' : 'completed',
            'completed_by' => $userId,
            'completed_at' => now(),
            'evidence_path' => $evidencePath,
        ]);

        $this->recalculateProgress($task->plan);

        return $task->fresh();
    }

    public function approveTask(RecruitmentOnboardingTask $task, int $userId): RecruitmentOnboardingTask
    {
        $task->update([
            'status' => 'completed',
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        $this->recalculateProgress($task->plan);

        return $task->fresh();
    }

    public function waiveTask(RecruitmentOnboardingTask $task, int $userId, string $reason): RecruitmentOnboardingTask
    {
        if (blank($reason)) {
            throw new \RuntimeException('Waiving an onboarding task requires a reason.');
        }

        $task->update([
            'status' => 'waived',
            'approved_by' => $userId,
            'approved_at' => now(),
            'waiver_reason' => $reason,
        ]);

        $this->recalculateProgress($task->plan);

        return $task->fresh();
    }

    private function recalculateProgress(RecruitmentOnboardingPlan $plan): void
    {
        $plan->load('tasks');
        $total = max(1, $plan->tasks->count());
        $done = $plan->tasks->whereIn('status', ['completed', 'waived'])->count();
        $requiredIncomplete = $plan->tasks->where('is_required', true)->whereNotIn('status', ['completed', 'waived'])->count();

        $plan->update([
            'progress_percent' => round(($done / $total) * 100, 4),
            'status' => $requiredIncomplete === 0 ? 'completed' : $plan->status,
            'completed_at' => $requiredIncomplete === 0 ? now() : $plan->completed_at,
        ]);
    }
}
