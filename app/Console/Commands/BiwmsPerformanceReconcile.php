<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\PerformanceAppraisal;
use App\Models\PerformanceAppraisalCycle;
use App\Models\PerformanceAppraisalCycleAssignment;
use App\Models\PerformanceAppraisalModerationItem;
use App\Models\PerformanceAppraisalRecommendation;
use App\Models\PerformanceDevelopmentPlan;
use App\Models\PerformanceGoalPlan;
use App\Models\PerformanceImprovementPlan;
use App\Models\PerformanceProbationReview;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

#[Signature('biwms:performance-reconcile {--details : Show detailed diagnostic rows} {--export= : Export findings to a JSON file}')]
#[Description('Report BIWMS performance, goals, appraisal, PIP, and probation consistency issues.')]
class BiwmsPerformanceReconcile extends Command
{
    public function handle(): int
    {
        $findings = $this->findings();

        $this->info('BIWMS Performance Reconcile');
        $this->line('Mode: report-only. No performance, employee, payroll, attendance, or roster data was changed.');
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
            $this->info('Exported performance reconcile report to '.$path);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{classification: string, severity: string, message: string, remediation: string, context: array<string, mixed>}>
     */
    private function findings(): array
    {
        if (! Schema::hasTable('performance_appraisal_cycles')) {
            return [];
        }

        return [
            ...$this->invalidCycleDateFindings(),
            ...$this->missingAppraisalFindings(),
            ...$this->missingSnapshotFindings(),
            ...$this->goalPlanWeightFindings(),
            ...$this->finalizedAppraisalFindings(),
            ...$this->moderationFindings(),
            ...$this->developmentPlanFindings(),
            ...$this->pipFindings(),
            ...$this->probationFindings(),
            ...$this->recommendationFindings(),
        ];
    }

    private function finding(string $classification, string $severity, string $message, string $remediation, array $context = []): array
    {
        return compact('classification', 'severity', 'message', 'remediation', 'context');
    }

    private function invalidCycleDateFindings(): array
    {
        return PerformanceAppraisalCycle::query()
            ->whereColumn('period_start', '>', 'period_end')
            ->limit(500)
            ->get()
            ->map(fn (PerformanceAppraisalCycle $cycle): array => $this->finding(
                'invalid_cycle_date_sequence',
                'critical',
                "Performance cycle {$cycle->code} has period_start after period_end.",
                'Correct the cycle dates through the controlled cycle setup workflow.',
                ['cycle_id' => $cycle->id],
            ))
            ->all();
    }

    private function missingAppraisalFindings(): array
    {
        return PerformanceAppraisalCycleAssignment::query()
            ->where('eligibility_status', 'eligible')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('performance_appraisals')
                    ->whereColumn('performance_appraisals.performance_appraisal_cycle_assignment_id', 'performance_appraisal_cycle_assignments.id');
            })
            ->limit(500)
            ->get()
            ->map(fn (PerformanceAppraisalCycleAssignment $assignment): array => $this->finding(
                'eligible_employee_missing_appraisal',
                'warning',
                "Eligible cycle assignment {$assignment->id} has no generated appraisal.",
                'Run appraisal generation for the cycle and verify exclusions before opening the workflow.',
                ['cycle_assignment_id' => $assignment->id, 'employee_id' => $assignment->employee_id],
            ))
            ->all();
    }

    private function missingSnapshotFindings(): array
    {
        return PerformanceAppraisal::query()
            ->where(function ($query): void {
                $query->whereNull('template_snapshot')->orWhereNull('rating_scale_snapshot');
            })
            ->limit(500)
            ->get()
            ->map(fn (PerformanceAppraisal $appraisal): array => $this->finding(
                'appraisal_missing_template_or_rating_snapshot',
                'critical',
                "Appraisal {$appraisal->id} is missing template or rating-scale snapshot data.",
                'Regenerate the appraisal before any assessment is submitted, or create a controlled amendment if already finalized.',
                ['appraisal_id' => $appraisal->id],
            ))
            ->all();
    }

    private function goalPlanWeightFindings(): array
    {
        return PerformanceGoalPlan::query()
            ->with('goals')
            ->limit(500)
            ->get()
            ->filter(fn (PerformanceGoalPlan $plan): bool => abs($plan->activeGoalWeightTotal() - 100.0) > 0.0001)
            ->map(fn (PerformanceGoalPlan $plan): array => $this->finding(
                'goal_plan_weight_not_100',
                'warning',
                "Goal plan {$plan->id} active goal weights total {$plan->activeGoalWeightTotal()}%.",
                'Revise goal weights through the goal approval workflow before approving the plan.',
                ['goal_plan_id' => $plan->id, 'weight_total' => $plan->activeGoalWeightTotal()],
            ))
            ->values()
            ->all();
    }

    private function finalizedAppraisalFindings(): array
    {
        $findings = [];

        PerformanceAppraisal::query()
            ->with('ratingScale.levels')
            ->whereIn('status', [PerformanceAppraisal::STATUS_FINALIZED, PerformanceAppraisal::STATUS_ACKNOWLEDGED, PerformanceAppraisal::STATUS_CLOSED])
            ->limit(500)
            ->get()
            ->each(function (PerformanceAppraisal $appraisal) use (&$findings): void {
                if ($appraisal->final_score === null) {
                    $findings[] = $this->finding(
                        'finalized_appraisal_missing_final_score',
                        'critical',
                        "Finalized appraisal {$appraisal->id} has no final score.",
                        'Reopen through controlled HR workflow or create an amendment with score calculation evidence.',
                        ['appraisal_id' => $appraisal->id],
                    );

                    return;
                }

                $level = $appraisal->ratingScale?->levelForScore((float) $appraisal->final_score);
                if ($level && (int) $appraisal->final_rating_level_id !== (int) $level->id) {
                    $findings[] = $this->finding(
                        'final_score_rating_level_mismatch',
                        'critical',
                        "Finalized appraisal {$appraisal->id} score does not match its rating level.",
                        'Review the score/rating mapping and correct through controlled amendment.',
                        ['appraisal_id' => $appraisal->id, 'expected_level_id' => $level->id, 'actual_level_id' => $appraisal->final_rating_level_id],
                    );
                }
            });

        return $findings;
    }

    private function moderationFindings(): array
    {
        return PerformanceAppraisalModerationItem::query()
            ->whereNotNull('moderated_score')
            ->whereColumn('moderated_score', '<>', 'original_score')
            ->whereNull('moderation_reason')
            ->limit(500)
            ->get()
            ->map(fn (PerformanceAppraisalModerationItem $item): array => $this->finding(
                'moderation_adjustment_without_reason',
                'critical',
                "Moderation item {$item->id} changed score without a reason.",
                'Add a moderation reason or reverse the adjustment through the moderation workflow.',
                ['moderation_item_id' => $item->id],
            ))
            ->all();
    }

    private function developmentPlanFindings(): array
    {
        return PerformanceDevelopmentPlan::query()
            ->whereNotNull('performance_appraisal_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('performance_appraisals')
                    ->whereColumn('performance_appraisals.id', 'performance_development_plans.performance_appraisal_id');
            })
            ->limit(500)
            ->get()
            ->map(fn (PerformanceDevelopmentPlan $plan): array => $this->finding(
                'development_plan_missing_appraisal',
                'warning',
                "Development plan {$plan->id} is linked to a missing appraisal.",
                'Link the plan to the correct appraisal or document that it was created independently.',
                ['development_plan_id' => $plan->id],
            ))
            ->all();
    }

    private function pipFindings(): array
    {
        $invalidDates = PerformanceImprovementPlan::query()
            ->whereColumn('start_date', '>', 'end_date')
            ->limit(500)
            ->get()
            ->map(fn (PerformanceImprovementPlan $plan): array => $this->finding(
                'active_pip_invalid_dates',
                'critical',
                "Performance improvement plan {$plan->id} has invalid dates.",
                'Correct the PIP dates through HR review; no employment or payroll action should be automatic.',
                ['pip_id' => $plan->id],
            ))
            ->all();

        $missingOutcome = PerformanceImprovementPlan::query()
            ->whereIn('status', [PerformanceImprovementPlan::STATUS_SUCCESSFULLY_COMPLETED, PerformanceImprovementPlan::STATUS_UNSUCCESSFULLY_COMPLETED])
            ->whereNull('outcome_summary')
            ->limit(500)
            ->get()
            ->map(fn (PerformanceImprovementPlan $plan): array => $this->finding(
                'pip_completed_without_outcome',
                'warning',
                "Completed PIP {$plan->id} has no outcome summary.",
                'Record the outcome summary; do not infer termination or payroll action.',
                ['pip_id' => $plan->id],
            ))
            ->all();

        return [...$invalidDates, ...$missingOutcome];
    }

    private function probationFindings(): array
    {
        return PerformanceProbationReview::query()
            ->whereNotNull('recommended_extension_end_date')
            ->whereColumn('recommended_extension_end_date', '<=', 'review_date')
            ->limit(500)
            ->get()
            ->map(fn (PerformanceProbationReview $review): array => $this->finding(
                'probation_invalid_extension_date',
                'critical',
                "Probation review {$review->id} has an invalid extension date.",
                'Correct the recommendation. Confirmation or termination remains a separate HR action.',
                ['probation_review_id' => $review->id],
            ))
            ->all();
    }

    private function recommendationFindings(): array
    {
        return PerformanceAppraisalRecommendation::query()
            ->where('status', 'implemented')
            ->where(function ($query): void {
                $query->whereNull('implementation_reference_type')->orWhereNull('implementation_reference_id');
            })
            ->limit(500)
            ->get()
            ->map(fn (PerformanceAppraisalRecommendation $recommendation): array => $this->finding(
                'recommendation_implemented_without_reference',
                'warning',
                "Performance recommendation {$recommendation->id} is implemented without an implementation reference.",
                'Link the recommendation to the controlled HR/payroll workflow that implemented it.',
                ['recommendation_id' => $recommendation->id],
            ))
            ->all();
    }
}
