<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\RecruitmentApplicationScreening;
use App\Models\RecruitmentSelectionReview;
use App\Models\RecruitmentVacancy;
use Illuminate\Support\Facades\DB;

class RecruitmentSelectionService
{
    public function assemble(RecruitmentVacancy $vacancy, int $userId): RecruitmentSelectionReview
    {
        return DB::transaction(function () use ($vacancy, $userId): RecruitmentSelectionReview {
            $review = RecruitmentSelectionReview::query()->create([
                'recruitment_vacancy_id' => $vacancy->id,
                'status' => 'draft',
                'reviewed_by' => $userId,
            ]);

            $applications = $vacancy->applications()
                ->whereIn('current_stage', ['selection_review', 'offer'])
                ->get();

            $rank = 1;

            foreach ($applications as $application) {
                $screeningScore = RecruitmentApplicationScreening::query()
                    ->where('recruitment_application_id', $application->id)
                    ->latest()
                    ->value('total_score');
                $review->candidates()->create([
                    'recruitment_application_id' => $application->id,
                    'screening_score' => $screeningScore,
                    'combined_score' => $screeningScore,
                    'rank' => $rank++,
                    'final_recommendation' => 'hold',
                ]);
            }

            return $review->fresh(['candidates']);
        });
    }

    public function approve(RecruitmentSelectionReview $review, int $userId): RecruitmentSelectionReview
    {
        return DB::transaction(function () use ($review, $userId): RecruitmentSelectionReview {
            $review = RecruitmentSelectionReview::query()->lockForUpdate()->with('candidates', 'vacancy')->findOrFail($review->id);
            $selectedCount = $review->candidates->where('final_recommendation', 'select')->count();

            if ($selectedCount > $review->vacancy->remainingOpenings()) {
                throw new \RuntimeException('Selection exceeds remaining vacancy openings.');
            }

            foreach ($review->candidates as $candidate) {
                if ($candidate->final_recommendation === 'select' && $candidate->rank !== null && $candidate->rank > $selectedCount && blank($candidate->justification)) {
                    throw new \RuntimeException('Selection outside score order requires justification.');
                }
            }

            $review->update([
                'status' => 'approved',
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            return $review->fresh(['candidates']);
        });
    }
}
