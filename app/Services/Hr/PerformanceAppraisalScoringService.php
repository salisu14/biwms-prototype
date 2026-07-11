<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\PerformanceAppraisal;
use App\Models\PerformanceAppraisalItem;

class PerformanceAppraisalScoringService
{
    /**
     * @return array{final_score: float, sections: array<int, array<string, mixed>>}
     */
    public function calculate(PerformanceAppraisal $appraisal): array
    {
        $sections = [];
        $weightedTotal = 0.0;
        $sectionWeightTotal = 0.0;

        foreach ($appraisal->loadMissing('sections.items')->sections as $section) {
            $items = $section->items->filter(fn (PerformanceAppraisalItem $item): bool => ! $item->is_not_applicable);
            $itemWeightTotal = (float) $items->sum('weight_percent');
            $sectionScore = 0.0;

            foreach ($items as $item) {
                $rating = $this->sourceRating($item);
                if ($rating === null || $itemWeightTotal <= 0) {
                    continue;
                }

                $sectionScore += $rating * ((float) $item->weight_percent / $itemWeightTotal);
            }

            $weight = (float) $section->weight_percent;
            $weightedTotal += $sectionScore * $weight;
            $sectionWeightTotal += $weight;
            $sections[] = [
                'section_id' => $section->id,
                'section_type' => $section->section_type,
                'section_score' => round($sectionScore, 4),
                'weight_percent' => $weight,
                'item_count' => $items->count(),
            ];
        }

        $finalScore = $sectionWeightTotal > 0 ? $weightedTotal / $sectionWeightTotal : 0.0;

        return [
            'final_score' => round($finalScore, 4),
            'sections' => $sections,
        ];
    }

    public function persistCalculatedScore(PerformanceAppraisal $appraisal): PerformanceAppraisal
    {
        $breakdown = $this->calculate($appraisal);
        $appraisal->forceFill([
            'calculated_score' => $breakdown['final_score'],
            'calculation_snapshot' => $breakdown,
        ])->save();

        return $appraisal->fresh();
    }

    private function sourceRating(PerformanceAppraisalItem $item): ?float
    {
        foreach (['final_rating', 'moderated_rating', 'manager_rating', 'secondary_reviewer_rating', 'employee_rating'] as $field) {
            if ($item->{$field} !== null) {
                return (float) $item->{$field};
            }
        }

        return null;
    }
}
