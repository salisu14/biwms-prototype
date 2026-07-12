<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\RecruitmentApplication;
use App\Models\RecruitmentApplicationStageHistory;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentVacancy;
use App\Services\AuditTrailService;
use Illuminate\Support\Facades\DB;

class RecruitmentApplicationService
{
    private const ALLOWED_TRANSITIONS = [
        'applied' => ['screening', 'on_hold', 'withdrawn', 'rejected'],
        'screening' => ['longlisted', 'shortlisted', 'on_hold', 'rejected', 'withdrawn'],
        'longlisted' => ['shortlisted', 'assessment', 'interview', 'on_hold', 'rejected', 'withdrawn'],
        'shortlisted' => ['assessment', 'interview', 'reference_check', 'background_check', 'selection_review', 'on_hold', 'rejected', 'withdrawn'],
        'assessment' => ['interview', 'selection_review', 'on_hold', 'rejected', 'withdrawn'],
        'interview' => ['reference_check', 'background_check', 'selection_review', 'on_hold', 'rejected', 'withdrawn'],
        'reference_check' => ['background_check', 'selection_review', 'offer', 'on_hold', 'rejected', 'withdrawn'],
        'background_check' => ['selection_review', 'offer', 'on_hold', 'rejected', 'withdrawn'],
        'selection_review' => ['offer', 'rejected', 'on_hold', 'withdrawn'],
        'offer' => ['hired', 'rejected', 'withdrawn'],
        'on_hold' => ['screening', 'shortlisted', 'interview', 'selection_review', 'rejected', 'withdrawn'],
    ];

    public function __construct(private readonly AuditTrailService $auditTrailService) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(RecruitmentCandidate $candidate, RecruitmentVacancy $vacancy, array $data = [], ?int $userId = null): RecruitmentApplication
    {
        return DB::transaction(function () use ($candidate, $vacancy, $data, $userId): RecruitmentApplication {
            $vacancy = RecruitmentVacancy::query()->lockForUpdate()->findOrFail($vacancy->id);

            if (! $vacancy->acceptsApplications()) {
                throw new \RuntimeException('This vacancy is not accepting applications.');
            }

            if (RecruitmentApplication::query()->where('recruitment_candidate_id', $candidate->id)->where('recruitment_vacancy_id', $vacancy->id)->exists()) {
                throw new \RuntimeException('Candidate already has an application for this vacancy.');
            }

            $application = RecruitmentApplication::query()->create(array_merge($data, [
                'recruitment_candidate_id' => $candidate->id,
                'recruitment_vacancy_id' => $vacancy->id,
                'application_date' => $data['application_date'] ?? now()->toDateString(),
                'current_stage' => RecruitmentApplication::STAGE_APPLIED,
                'status' => 'active',
                'hiring_manager_employee_id' => $data['hiring_manager_employee_id'] ?? $vacancy->hiring_manager_employee_id,
                'assigned_recruiter_employee_id' => $data['assigned_recruiter_employee_id'] ?? $vacancy->recruiter_employee_id,
            ]));

            $this->recordStage($application, null, RecruitmentApplication::STAGE_APPLIED, $userId, 'Application submitted');
            $this->audit('application_submitted', $application, $userId);

            return $application;
        });
    }

    public function moveToStage(RecruitmentApplication $application, string $stage, int $userId, ?string $reason = null): RecruitmentApplication
    {
        return DB::transaction(function () use ($application, $stage, $userId, $reason): RecruitmentApplication {
            $application = RecruitmentApplication::query()->lockForUpdate()->findOrFail($application->id);
            $fromStage = $application->current_stage;

            if (! in_array($stage, self::ALLOWED_TRANSITIONS[$fromStage] ?? [], true)) {
                throw new \RuntimeException("Invalid recruitment stage transition from {$fromStage} to {$stage}.");
            }

            $application->update(['current_stage' => $stage]);
            $this->recordStage($application, $fromStage, $stage, $userId, $reason);
            $this->audit('application_stage_changed', $application, $userId, ['from' => $fromStage, 'to' => $stage]);

            return $application->fresh();
        });
    }

    public function reject(RecruitmentApplication $application, int $userId, string $reason, ?int $reasonId = null): RecruitmentApplication
    {
        if (blank($reason)) {
            throw new \RuntimeException('Application rejection requires a reason.');
        }

        return DB::transaction(function () use ($application, $userId, $reason, $reasonId): RecruitmentApplication {
            $application = RecruitmentApplication::query()->lockForUpdate()->findOrFail($application->id);
            $fromStage = $application->current_stage;
            $application->update([
                'current_stage' => RecruitmentApplication::STAGE_REJECTED,
                'status' => 'unsuccessful',
                'rejection_reason_id' => $reasonId,
                'rejection_notes' => $reason,
            ]);
            $this->recordStage($application, $fromStage, RecruitmentApplication::STAGE_REJECTED, $userId, $reason);
            $this->audit('application_rejected', $application, $userId, ['reason' => $reason]);

            return $application->fresh();
        });
    }

    public function withdraw(RecruitmentApplication $application, ?int $userId, string $reason): RecruitmentApplication
    {
        return DB::transaction(function () use ($application, $userId, $reason): RecruitmentApplication {
            $application = RecruitmentApplication::query()->lockForUpdate()->findOrFail($application->id);
            $fromStage = $application->current_stage;
            $application->update([
                'current_stage' => RecruitmentApplication::STAGE_WITHDRAWN,
                'status' => 'withdrawn',
                'withdrawn_at' => now(),
                'withdrawal_reason' => $reason,
            ]);
            $this->recordStage($application, $fromStage, RecruitmentApplication::STAGE_WITHDRAWN, $userId, $reason);

            return $application->fresh();
        });
    }

    private function recordStage(RecruitmentApplication $application, ?string $from, string $to, ?int $userId, ?string $reason): void
    {
        RecruitmentApplicationStageHistory::query()->create([
            'recruitment_application_id' => $application->id,
            'from_stage' => $from,
            'to_stage' => $to,
            'changed_by' => $userId,
            'changed_at' => now(),
            'reason' => $reason,
        ]);
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function audit(string $action, RecruitmentApplication $application, ?int $userId, array $metadata = []): void
    {
        $this->auditTrailService->recordGeneric('recruitment', $action, $application, userId: $userId, metadata: $metadata);
    }
}
