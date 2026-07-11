<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\PerformanceGoal;
use App\Models\PerformanceGoalPlan;
use App\Models\PerformanceGoalUpdate;
use App\Services\AuditTrailService;
use Illuminate\Support\Facades\DB;

class PerformanceGoalService
{
    public function __construct(private readonly AuditTrailService $auditTrailService) {}

    public function approvePlan(PerformanceGoalPlan $plan, int $userId): PerformanceGoalPlan
    {
        return DB::transaction(function () use ($plan, $userId): PerformanceGoalPlan {
            $locked = PerformanceGoalPlan::query()->lockForUpdate()->findOrFail($plan->id);
            $total = $locked->activeGoalWeightTotal();

            if (abs($total - 100.0) > 0.0001) {
                throw new \RuntimeException('Goal plan active goal weights must total 100%.');
            }

            $locked->forceFill([
                'status' => PerformanceGoalPlan::STATUS_APPROVED,
                'total_weight_percent' => $total,
                'approved_by' => $userId,
                'approved_at' => now(),
            ])->save();

            PerformanceGoal::query()->where('performance_goal_plan_id', $locked->id)->update(['status' => PerformanceGoal::STATUS_APPROVED]);
            $this->auditTrailService->recordGeneric('performance', 'goal_plan_approved', $locked, userId: $userId);

            return $locked->fresh('goals');
        });
    }

    public function submitUpdate(PerformanceGoal $goal, int $userId, float $progressPercent, ?float $currentValue, string $text, ?string $evidencePath = null): PerformanceGoalUpdate
    {
        return DB::transaction(function () use ($goal, $userId, $progressPercent, $currentValue, $text, $evidencePath): PerformanceGoalUpdate {
            $update = PerformanceGoalUpdate::query()->create([
                'performance_goal_id' => $goal->id,
                'progress_percent' => $progressPercent,
                'current_value' => $currentValue,
                'update_text' => $text,
                'evidence_attachment_path' => $evidencePath,
                'update_date' => now()->toDateString(),
                'submitted_by' => $userId,
                'verification_status' => 'pending',
            ]);

            $goal->forceFill([
                'progress_percent' => $progressPercent,
                'current_value' => $currentValue,
            ])->save();

            $this->auditTrailService->recordGeneric('performance', 'goal_update_submitted', $goal, userId: $userId, metadata: ['update_id' => $update->id]);

            return $update;
        });
    }
}
