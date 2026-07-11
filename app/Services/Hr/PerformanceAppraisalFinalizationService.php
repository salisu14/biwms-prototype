<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\PerformanceAppraisal;
use App\Models\PerformanceAppraisalAcknowledgement;
use App\Services\AuditTrailService;
use Illuminate\Support\Facades\DB;

class PerformanceAppraisalFinalizationService
{
    public function __construct(
        private readonly PerformanceAppraisalScoringService $scoringService,
        private readonly AuditTrailService $auditTrailService,
    ) {}

    public function finalize(PerformanceAppraisal $appraisal, int $userId, ?string $finalComment = null): PerformanceAppraisal
    {
        return DB::transaction(function () use ($appraisal, $userId, $finalComment): PerformanceAppraisal {
            $locked = PerformanceAppraisal::query()->lockForUpdate()->findOrFail($appraisal->id);

            if ($locked->manager_submitted_at === null) {
                throw new \RuntimeException('Manager assessment must be submitted before finalization.');
            }

            $scored = $this->scoringService->persistCalculatedScore($locked);
            $finalScore = (float) ($scored->moderated_score ?? $scored->calculated_score ?? 0);
            $ratingLevel = $scored->ratingScale?->levelForScore($finalScore);

            $scored->forceFill([
                'final_score' => $finalScore,
                'final_rating_level_id' => $ratingLevel?->id,
                'final_comment' => $finalComment,
                'status' => PerformanceAppraisal::STATUS_FINALIZED,
                'finalized_at' => now(),
            ])->save();

            $this->auditTrailService->recordGeneric('performance', 'appraisal_finalized', $scored, userId: $userId, metadata: [
                'final_score' => $finalScore,
                'rating_level_id' => $ratingLevel?->id,
            ]);

            return $scored->fresh();
        });
    }

    public function acknowledge(PerformanceAppraisal $appraisal, int $employeeId, int $userId, string $status = 'acknowledged', ?string $comment = null): PerformanceAppraisalAcknowledgement
    {
        if ((int) $appraisal->employee_id !== $employeeId) {
            throw new \RuntimeException('Employees can only acknowledge their own appraisal.');
        }

        return DB::transaction(function () use ($appraisal, $employeeId, $userId, $status, $comment): PerformanceAppraisalAcknowledgement {
            $locked = PerformanceAppraisal::query()->lockForUpdate()->findOrFail($appraisal->id);
            if ($locked->status !== PerformanceAppraisal::STATUS_FINALIZED) {
                throw new \RuntimeException('Only finalized appraisals can be acknowledged.');
            }

            $acknowledgement = PerformanceAppraisalAcknowledgement::query()->create([
                'performance_appraisal_id' => $locked->id,
                'employee_id' => $employeeId,
                'acknowledgement_status' => $status,
                'employee_comment' => $comment,
                'acknowledged_at' => now(),
                'submitted_by' => $userId,
            ]);

            $locked->forceFill([
                'status' => $status === 'disputed' ? PerformanceAppraisal::STATUS_DISPUTED : PerformanceAppraisal::STATUS_ACKNOWLEDGED,
                'acknowledged_at' => now(),
            ])->save();

            $this->auditTrailService->recordGeneric('performance', 'appraisal_acknowledged', $locked, userId: $userId, metadata: ['acknowledgement_status' => $status]);

            return $acknowledgement;
        });
    }
}
