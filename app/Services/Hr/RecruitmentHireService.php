<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\Employee;
use App\Models\PerformanceProbationReview;
use App\Models\RecruitmentApplication;
use App\Models\RecruitmentOffer;
use App\Models\RecruitmentOnboardingTemplate;
use App\Models\RecruitmentRequisition;
use App\Models\RecruitmentVacancy;
use App\Services\AuditTrailService;
use App\Services\HR\EmployeeOnboardingService;
use Illuminate\Support\Facades\DB;

class RecruitmentHireService
{
    public function __construct(
        private readonly EmployeeOnboardingService $employeeOnboardingService,
        private readonly EmployeeOnboardingPlanService $onboardingPlanService,
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function convert(RecruitmentApplication $application, int $userId, ?RecruitmentOnboardingTemplate $template = null): Employee
    {
        return DB::transaction(function () use ($application, $userId, $template): Employee {
            $application = RecruitmentApplication::query()
                ->with(['candidate', 'vacancy.requisition', 'offers'])
                ->lockForUpdate()
                ->findOrFail($application->id);

            if ($application->hired_employee_id) {
                return Employee::query()->findOrFail($application->hired_employee_id);
            }

            $offer = $application->offers()
                ->where('status', RecruitmentOffer::STATUS_ACCEPTED)
                ->latest()
                ->first();

            if (! $offer) {
                throw new \RuntimeException('Candidate conversion requires an accepted offer.');
            }

            if (! $this->requiredChecksCleared($application)) {
                throw new \RuntimeException('Required pre-employment checks are not cleared.');
            }

            $vacancy = RecruitmentVacancy::query()->lockForUpdate()->findOrFail($application->recruitment_vacancy_id);

            if ($vacancy->remainingOpenings() <= 0) {
                throw new \RuntimeException('No remaining approved vacancy openings are available.');
            }

            $candidate = $application->candidate;
            $employee = $this->employeeOnboardingService->create([
                'employee_number' => $this->nextEmployeeNumber($candidate->candidate_number),
                'first_name' => $candidate->first_name,
                'last_name' => $candidate->last_name,
                'email' => $candidate->email,
                'phone' => $candidate->phone,
                'job_title' => $candidate->current_job_title ?? $vacancy->title,
                'department_id' => $offer->department_id,
                'is_active' => true,
            ]);

            $application->update([
                'current_stage' => RecruitmentApplication::STAGE_HIRED,
                'status' => 'successful',
                'hired_employee_id' => $employee->id,
            ]);
            $candidate->update(['status' => 'hired']);
            $vacancy->increment('filled_openings');
            $this->syncRequisitionStatus($vacancy->fresh('requisition')->requisition);

            if ($template) {
                $this->onboardingPlanService->generate($employee, $template, $offer->proposed_start_date, $application, $offer, $userId);
            }

            $this->scheduleProbationReview($employee, $application, $offer);

            $this->auditTrailService->recordGeneric(
                eventType: 'recruitment',
                action: 'candidate_converted_to_employee',
                auditable: $application,
                userId: $userId,
                metadata: [
                    'candidate_id' => $candidate->id,
                    'employee_id' => $employee->id,
                    'offer_id' => $offer->id,
                    'payroll_boundary' => 'No payroll earnings, deductions, or salary activation were created.',
                ],
            );

            return $employee->fresh();
        });
    }

    private function requiredChecksCleared(RecruitmentApplication $application): bool
    {
        return ! $application->checks()
            ->whereIn('check_type', ['identity', 'right_to_work'])
            ->whereNotIn('status', ['cleared', 'waived'])
            ->exists();
    }

    private function nextEmployeeNumber(string $candidateNumber): string
    {
        $number = 'EMP-'.$candidateNumber;

        if (! Employee::query()->where('employee_number', $number)->exists()) {
            return $number;
        }

        return $number.'-'.strtoupper(str()->random(4));
    }

    private function syncRequisitionStatus(RecruitmentRequisition $requisition): void
    {
        $filled = $requisition->filledHeadcount();
        $status = $filled >= (int) $requisition->requested_headcount
            ? RecruitmentRequisition::STATUS_FILLED
            : 'partially_filled';

        $requisition->update(['status' => $status]);
    }

    private function scheduleProbationReview(Employee $employee, RecruitmentApplication $application, RecruitmentOffer $offer): void
    {
        if (! $offer->probation_months) {
            return;
        }

        PerformanceProbationReview::query()->firstOrCreate([
            'employee_id' => $employee->id,
            'probation_start_date' => $offer->proposed_start_date,
        ], [
            'expected_confirmation_date' => $offer->proposed_start_date->copy()->addMonths((int) $offer->probation_months),
            'review_date' => $offer->proposed_start_date->copy()->addMonths((int) $offer->probation_months),
            'review_type' => 'final',
            'status' => 'scheduled',
            'manager_employee_id' => $offer->reporting_manager_employee_id ?? $application->hiring_manager_employee_id,
            'manager_recommendation' => 'no_recommendation',
        ]);
    }
}
