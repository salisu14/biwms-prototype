<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\RecruitmentApplication;
use App\Models\RecruitmentApplicationScreening;
use App\Models\RecruitmentInterviewScore;
use App\Models\RecruitmentOffer;
use App\Models\RecruitmentOnboardingPlan;
use App\Models\RecruitmentRequisition;
use App\Models\RecruitmentVacancy;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

#[Signature('biwms:recruitment-reconcile {--details : Show detailed recruitment diagnostics} {--export= : Export findings to a JSON file}')]
#[Description('Report BIWMS recruitment, hiring, onboarding, and confirmation consistency issues.')]
class BiwmsRecruitmentReconcile extends Command
{
    public function handle(): int
    {
        $findings = $this->findings();

        $this->info('BIWMS Recruitment Reconcile');
        $this->line('Mode: report-only. No recruitment, employee, payroll, or probation data was changed.');
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
            $this->info('Exported recruitment reconcile report to '.$path);
        }

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{classification:string,severity:string,message:string,remediation:string,context:array<string,mixed>}>
     */
    private function findings(): array
    {
        if (! Schema::hasTable('recruitment_requisitions')) {
            return [];
        }

        return [
            ...$this->requisitionFindings(),
            ...$this->vacancyFindings(),
            ...$this->applicationFindings(),
            ...$this->screeningFindings(),
            ...$this->interviewFindings(),
            ...$this->offerFindings(),
            ...$this->onboardingFindings(),
        ];
    }

    private function requisitionFindings(): array
    {
        $findings = [];

        foreach (RecruitmentRequisition::query()->where('requested_headcount', '<=', 0)->limit(500)->get() as $requisition) {
            $findings[] = $this->finding('invalid_requisition_headcount', 'critical', "Requisition {$requisition->requisition_number} has invalid headcount.", 'Correct headcount through an approved requisition amendment.', ['requisition_id' => $requisition->id]);
        }

        return $findings;
    }

    private function vacancyFindings(): array
    {
        $findings = [];

        foreach (RecruitmentVacancy::query()->with('requisition')->limit(1000)->get() as $vacancy) {
            if (! in_array($vacancy->requisition?->status, ['approved', 'open', 'partially_filled', 'filled'], true) && $vacancy->status === 'open') {
                $findings[] = $this->finding('open_vacancy_unapproved_requisition', 'critical', "Open vacancy {$vacancy->vacancy_number} is linked to an unapproved requisition.", 'Pause the vacancy or complete requisition approval.', ['vacancy_id' => $vacancy->id]);
            }

            if ((float) $vacancy->salary_max > 0 && $vacancy->requisition?->budgeted_salary_max !== null && (float) $vacancy->salary_max > (float) $vacancy->requisition->budgeted_salary_max) {
                $findings[] = $this->finding('vacancy_salary_outside_authority', 'warning', "Vacancy {$vacancy->vacancy_number} salary exceeds requisition authority.", 'Review compensation authority and submit a controlled amendment if valid.', ['vacancy_id' => $vacancy->id]);
            }
        }

        return $findings;
    }

    private function applicationFindings(): array
    {
        $findings = [];

        foreach (RecruitmentApplication::query()->with('vacancy', 'histories')->limit(1000)->get() as $application) {
            if (in_array($application->vacancy?->status, ['closed', 'cancelled', 'filled'], true) && $application->status === 'active') {
                $findings[] = $this->finding('active_application_closed_vacancy', 'warning', "Application {$application->application_number} is active on a closed/cancelled/filled vacancy.", 'Review the application and move it to an appropriate historical stage.', ['application_id' => $application->id]);
            }

            $lastStage = $application->histories->sortBy('changed_at')->last()?->to_stage;

            if ($lastStage !== null && $lastStage !== $application->current_stage) {
                $findings[] = $this->finding('application_stage_history_mismatch', 'critical', "Application {$application->application_number} current stage does not match stage history.", 'Use the application service to replay or correct the stage transition.', ['application_id' => $application->id]);
            }
        }

        return $findings;
    }

    private function screeningFindings(): array
    {
        return RecruitmentApplicationScreening::query()
            ->withCount('items')
            ->limit(1000)
            ->get()
            ->flatMap(function (RecruitmentApplicationScreening $screening): array {
                $findings = [];

                if ($screening->status === 'completed' && $screening->items_count === 0) {
                    $findings[] = $this->finding('screening_completed_without_snapshots', 'critical', "Screening {$screening->id} completed without criterion snapshots.", 'Regenerate screening snapshots from the versioned template and review the result.', ['screening_id' => $screening->id]);
                }

                if (filled($screening->override_recommendation) && blank($screening->override_reason)) {
                    $findings[] = $this->finding('screening_override_without_reason', 'critical', "Screening {$screening->id} has an override without reason.", 'Add an authorized override reason or remove the override.', ['screening_id' => $screening->id]);
                }

                return $findings;
            })
            ->all();
    }

    private function interviewFindings(): array
    {
        return RecruitmentInterviewScore::query()
            ->whereIn('status', ['submitted', 'locked'])
            ->whereNull('submitted_at')
            ->limit(1000)
            ->get()
            ->map(fn (RecruitmentInterviewScore $score): array => $this->finding('submitted_score_missing_timestamp', 'warning', "Interview score {$score->id} is submitted without submitted_at.", 'Review score submission history and set the controlled submission timestamp.', ['score_id' => $score->id]))
            ->all();
    }

    private function offerFindings(): array
    {
        $findings = [];

        foreach (RecruitmentOffer::query()->with('application')->limit(1000)->get() as $offer) {
            if (in_array($offer->status, ['issued', 'accepted'], true) && $offer->approved_at === null) {
                $findings[] = $this->finding('offer_issued_without_approval', 'critical', "Offer {$offer->offer_number} was issued or accepted without approval.", 'Withdraw or regularize the offer through offer approval workflow.', ['offer_id' => $offer->id]);
            }

            if ($offer->status === 'accepted' && $offer->accepted_at !== null && $offer->valid_until->lt($offer->accepted_at)) {
                $findings[] = $this->finding('expired_offer_accepted', 'critical', "Offer {$offer->offer_number} was accepted after expiry.", 'Review the acceptance and issue a superseding offer if appropriate.', ['offer_id' => $offer->id]);
            }
        }

        return $findings;
    }

    private function onboardingFindings(): array
    {
        $findings = [];

        foreach (RecruitmentOnboardingPlan::query()->with('tasks')->where('status', 'completed')->limit(1000)->get() as $plan) {
            if ($plan->tasks->where('is_required', true)->whereNotIn('status', ['completed', 'waived'])->isNotEmpty()) {
                $findings[] = $this->finding('completed_onboarding_with_incomplete_required_tasks', 'critical', "Onboarding plan {$plan->id} is completed with incomplete mandatory tasks.", 'Reopen the plan and complete or waive required tasks with approval.', ['plan_id' => $plan->id]);
            }
        }

        return $findings;
    }

    /**
     * @return array{classification:string,severity:string,message:string,remediation:string,context:array<string,mixed>}
     */
    private function finding(string $classification, string $severity, string $message, string $remediation, array $context = []): array
    {
        return compact('classification', 'severity', 'message', 'remediation', 'context');
    }
}
