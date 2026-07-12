<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\RecruitmentRequisition;
use App\Models\RecruitmentVacancy;
use App\Services\AuditTrailService;
use Illuminate\Support\Facades\DB;

class RecruitmentRequisitionService
{
    public function __construct(private readonly AuditTrailService $auditTrailService) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function createDraft(array $data, ?int $userId = null): RecruitmentRequisition
    {
        return DB::transaction(function () use ($data, $userId): RecruitmentRequisition {
            $requisition = RecruitmentRequisition::query()->create(array_merge($data, [
                'status' => $data['status'] ?? RecruitmentRequisition::STATUS_DRAFT,
            ]));

            $this->audit('requisition_created', $requisition, $userId);

            return $requisition;
        });
    }

    public function submit(RecruitmentRequisition $requisition, int $userId): RecruitmentRequisition
    {
        return DB::transaction(function () use ($requisition, $userId): RecruitmentRequisition {
            $requisition = RecruitmentRequisition::query()->lockForUpdate()->findOrFail($requisition->id);

            if ($requisition->status !== RecruitmentRequisition::STATUS_DRAFT) {
                throw new \RuntimeException('Only draft requisitions can be submitted.');
            }

            $requisition->update([
                'status' => RecruitmentRequisition::STATUS_SUBMITTED,
                'submitted_by' => $userId,
                'submitted_at' => now(),
            ]);

            $this->audit('requisition_submitted', $requisition, $userId);

            return $requisition->fresh();
        });
    }

    public function approve(RecruitmentRequisition $requisition, int $userId): RecruitmentRequisition
    {
        return DB::transaction(function () use ($requisition, $userId): RecruitmentRequisition {
            $requisition = RecruitmentRequisition::query()->lockForUpdate()->findOrFail($requisition->id);

            if ($requisition->submitted_by === $userId) {
                throw new \RuntimeException('Requester self-approval is not allowed.');
            }

            if (! in_array($requisition->status, [RecruitmentRequisition::STATUS_SUBMITTED, 'under_review'], true)) {
                throw new \RuntimeException('Only submitted requisitions can be approved.');
            }

            $requisition->update([
                'status' => RecruitmentRequisition::STATUS_APPROVED,
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            $this->audit('requisition_approved', $requisition, $userId);

            return $requisition->fresh();
        });
    }

    public function reject(RecruitmentRequisition $requisition, int $userId, string $reason): RecruitmentRequisition
    {
        if (blank($reason)) {
            throw new \RuntimeException('A rejection reason is required.');
        }

        return DB::transaction(function () use ($requisition, $userId, $reason): RecruitmentRequisition {
            $requisition = RecruitmentRequisition::query()->lockForUpdate()->findOrFail($requisition->id);
            $requisition->update([
                'status' => RecruitmentRequisition::STATUS_REJECTED,
                'rejected_by' => $userId,
                'rejected_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $this->audit('requisition_rejected', $requisition, $userId, ['reason' => $reason]);

            return $requisition->fresh();
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function createVacancy(RecruitmentRequisition $requisition, array $data, int $userId): RecruitmentVacancy
    {
        return DB::transaction(function () use ($requisition, $data, $userId): RecruitmentVacancy {
            $requisition = RecruitmentRequisition::query()->lockForUpdate()->findOrFail($requisition->id);

            if ($requisition->status !== RecruitmentRequisition::STATUS_APPROVED && $requisition->status !== RecruitmentRequisition::STATUS_OPEN) {
                throw new \RuntimeException('Only approved requisitions can create vacancies.');
            }

            $openings = (int) ($data['number_of_openings'] ?? 0);

            if ($openings > $requisition->approvedHeadcountRemaining()) {
                throw new \RuntimeException('Vacancy openings exceed remaining approved requisition headcount.');
            }

            $this->ensureSalaryWithinAuthority($requisition, $data);

            $vacancy = RecruitmentVacancy::query()->create(array_merge([
                'recruitment_requisition_id' => $requisition->id,
                'title' => $requisition->title,
                'department_id' => $requisition->department_id,
                'work_center_id' => $requisition->work_center_id,
                'location_id' => $requisition->location_id,
                'position_id' => $requisition->position_id,
                'job_title_id' => $requisition->job_title_id,
                'grade_id' => $requisition->grade_id,
                'employment_type' => $requisition->employment_type,
                'hiring_manager_employee_id' => $requisition->hiring_manager_employee_id,
                'recruiter_employee_id' => $requisition->recruiter_employee_id,
                'status' => RecruitmentVacancy::STATUS_DRAFT,
            ], $data));

            $requisition->update(['status' => RecruitmentRequisition::STATUS_OPEN]);
            $this->audit('vacancy_created', $vacancy, $userId, ['requisition_id' => $requisition->id]);

            return $vacancy;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function ensureSalaryWithinAuthority(RecruitmentRequisition $requisition, array $data): void
    {
        $salaryMin = $data['salary_min'] ?? null;
        $salaryMax = $data['salary_max'] ?? null;

        if ($requisition->budgeted_salary_min !== null && $salaryMin !== null && (float) $salaryMin < (float) $requisition->budgeted_salary_min) {
            throw new \RuntimeException('Vacancy salary minimum is outside approved requisition range.');
        }

        if ($requisition->budgeted_salary_max !== null && $salaryMax !== null && (float) $salaryMax > (float) $requisition->budgeted_salary_max) {
            throw new \RuntimeException('Vacancy salary maximum is outside approved requisition range.');
        }
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function audit(string $action, object $model, ?int $userId, array $metadata = []): void
    {
        $this->auditTrailService->recordGeneric(
            eventType: 'recruitment',
            action: $action,
            auditable: $model,
            userId: $userId,
            metadata: $metadata,
        );
    }
}
