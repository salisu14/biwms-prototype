<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\RecruitmentApplication;
use App\Models\RecruitmentOffer;
use Illuminate\Support\Facades\DB;

class RecruitmentOfferService
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function draft(RecruitmentApplication $application, array $data): RecruitmentOffer
    {
        return DB::transaction(function () use ($application, $data): RecruitmentOffer {
            $application = RecruitmentApplication::query()->with('vacancy.requisition')->lockForUpdate()->findOrFail($application->id);

            if ($application->current_stage !== 'offer') {
                throw new \RuntimeException('Offers can only be drafted for applications in offer stage.');
            }

            $this->validateSalaryAuthority($application, $data['base_salary'] ?? null);

            return RecruitmentOffer::query()->create(array_merge([
                'recruitment_application_id' => $application->id,
                'offer_version' => 1,
                'department_id' => $application->vacancy->department_id,
                'work_center_id' => $application->vacancy->work_center_id,
                'location_id' => $application->vacancy->location_id,
                'employment_type' => $application->vacancy->employment_type,
                'status' => RecruitmentOffer::STATUS_DRAFT,
            ], $data));
        });
    }

    public function approve(RecruitmentOffer $offer, int $userId): RecruitmentOffer
    {
        if (! in_array($offer->status, [RecruitmentOffer::STATUS_DRAFT, RecruitmentOffer::STATUS_UNDER_REVIEW], true)) {
            throw new \RuntimeException('Only draft or under-review offers can be approved.');
        }

        $offer->update([
            'status' => RecruitmentOffer::STATUS_APPROVED,
            'approved_by' => $userId,
            'approved_at' => now(),
        ]);

        return $offer->fresh();
    }

    public function issue(RecruitmentOffer $offer, int $userId): RecruitmentOffer
    {
        if ($offer->status !== RecruitmentOffer::STATUS_APPROVED) {
            throw new \RuntimeException('Only approved offers can be issued.');
        }

        $offer->update([
            'status' => RecruitmentOffer::STATUS_ISSUED,
            'issued_by' => $userId,
            'issued_at' => now(),
        ]);

        return $offer->fresh();
    }

    public function accept(RecruitmentOffer $offer): RecruitmentOffer
    {
        return DB::transaction(function () use ($offer): RecruitmentOffer {
            $offer = RecruitmentOffer::query()->lockForUpdate()->findOrFail($offer->id);

            if ($offer->status !== RecruitmentOffer::STATUS_ISSUED) {
                throw new \RuntimeException('Only issued offers can be accepted.');
            }

            if ($offer->valid_until->isPast()) {
                throw new \RuntimeException('Expired offers cannot be accepted.');
            }

            $offer->updateQuietly([
                'status' => RecruitmentOffer::STATUS_ACCEPTED,
                'accepted_at' => now(),
            ]);

            return $offer->fresh();
        });
    }

    public function decline(RecruitmentOffer $offer, string $reason): RecruitmentOffer
    {
        $offer->update([
            'status' => RecruitmentOffer::STATUS_DECLINED,
            'declined_at' => now(),
            'decline_reason' => $reason,
        ]);

        return $offer->fresh();
    }

    private function validateSalaryAuthority(RecruitmentApplication $application, mixed $baseSalary): void
    {
        if ($baseSalary === null) {
            return;
        }

        $requisition = $application->vacancy->requisition;

        if ($requisition->budgeted_salary_min !== null && (float) $baseSalary < (float) $requisition->budgeted_salary_min) {
            throw new \RuntimeException('Offer salary is below approved requisition authority.');
        }

        if ($requisition->budgeted_salary_max !== null && (float) $baseSalary > (float) $requisition->budgeted_salary_max) {
            throw new \RuntimeException('Offer salary exceeds approved requisition authority.');
        }
    }
}
