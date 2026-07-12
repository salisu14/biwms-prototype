<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\RecruitmentApplication;
use App\Models\RecruitmentApplicationScreening;
use App\Models\RecruitmentScreeningTemplate;
use Illuminate\Support\Facades\DB;

class RecruitmentScreeningService
{
    public function generate(RecruitmentApplication $application, RecruitmentScreeningTemplate $template, ?int $userId = null): RecruitmentApplicationScreening
    {
        return DB::transaction(function () use ($application, $template, $userId): RecruitmentApplicationScreening {
            $screening = RecruitmentApplicationScreening::query()->firstOrCreate([
                'recruitment_application_id' => $application->id,
                'screening_template_id' => $template->id,
            ], [
                'screening_template_version' => $template->version,
                'screened_by' => $userId,
                'status' => 'pending',
            ]);

            if ($screening->items()->exists()) {
                return $screening->fresh(['items']);
            }

            foreach ($template->criteria()->orderBy('sort_order')->get() as $criterion) {
                $screening->items()->create([
                    'source_criterion_id' => $criterion->id,
                    'code' => $criterion->code,
                    'title' => $criterion->title,
                    'criterion_type' => $criterion->criterion_type,
                    'weight_percent' => $criterion->weight_percent,
                    'is_mandatory' => $criterion->is_mandatory,
                    'disqualifying_if_failed' => $criterion->disqualifying_if_failed,
                ]);
            }

            return $screening->fresh(['items']);
        });
    }

    /**
     * @param  array<int, array{item_id:int,result_value?:mixed,passed?:bool,score?:float,comment?:string}>  $results
     */
    public function complete(RecruitmentApplicationScreening $screening, array $results, int $userId): RecruitmentApplicationScreening
    {
        return DB::transaction(function () use ($screening, $results, $userId): RecruitmentApplicationScreening {
            $screening = RecruitmentApplicationScreening::query()->lockForUpdate()->with('items')->findOrFail($screening->id);

            foreach ($results as $result) {
                $item = $screening->items->firstWhere('id', $result['item_id']);

                if (! $item) {
                    continue;
                }

                $item->update([
                    'result_value' => isset($result['result_value']) ? (string) $result['result_value'] : null,
                    'passed' => $result['passed'] ?? null,
                    'score' => $result['score'] ?? null,
                    'reviewer_comment' => $result['comment'] ?? null,
                ]);
            }

            $items = $screening->fresh('items')->items;
            $weighted = $items->sum(fn ($item): float => (float) ($item->score ?? 0) * ((float) ($item->weight_percent ?? 0) / 100));
            $mandatoryPassed = $items->where('is_mandatory', true)->every(fn ($item): bool => $item->passed === true);
            $disqualified = $items->where('disqualifying_if_failed', true)->contains(fn ($item): bool => $item->passed === false);

            $screening->update([
                'status' => 'completed',
                'screened_by' => $userId,
                'total_score' => round($weighted, 4),
                'mandatory_criteria_passed' => $mandatoryPassed,
                'recommendation' => $disqualified ? 'manual_review' : ($mandatoryPassed ? 'proceed' : 'hold'),
                'completed_at' => now(),
            ]);

            return $screening->fresh(['items']);
        });
    }

    public function override(RecruitmentApplicationScreening $screening, string $recommendation, string $reason): RecruitmentApplicationScreening
    {
        if (blank($reason)) {
            throw new \RuntimeException('Screening override requires a reason.');
        }

        $screening->update([
            'status' => 'overridden',
            'override_recommendation' => $recommendation,
            'override_reason' => $reason,
        ]);

        return $screening->fresh();
    }
}
