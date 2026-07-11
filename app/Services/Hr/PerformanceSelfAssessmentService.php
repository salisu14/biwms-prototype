<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\PerformanceAppraisal;
use App\Services\AuditTrailService;
use Illuminate\Support\Facades\DB;

class PerformanceSelfAssessmentService
{
    public function __construct(private readonly AuditTrailService $auditTrailService) {}

    /**
     * @param  array<int, array{item_id: int, rating?: float|null, comment?: string|null}>  $items
     */
    public function submit(PerformanceAppraisal $appraisal, int $employeeId, int $userId, array $items, ?string $overallComment = null): PerformanceAppraisal
    {
        if ((int) $appraisal->employee_id !== $employeeId) {
            throw new \RuntimeException('Employees can only submit their own self-assessment.');
        }

        if ($appraisal->self_submitted_at !== null) {
            throw new \RuntimeException('Submitted self-assessment is locked.');
        }

        return DB::transaction(function () use ($appraisal, $items, $overallComment, $userId): PerformanceAppraisal {
            $locked = PerformanceAppraisal::query()->lockForUpdate()->findOrFail($appraisal->id);

            foreach ($items as $payload) {
                $locked->items()->whereKey($payload['item_id'])->update([
                    'employee_rating' => $payload['rating'] ?? null,
                    'employee_comment' => $payload['comment'] ?? null,
                ]);
            }

            $locked->forceFill([
                'employee_overall_comment' => $overallComment,
                'self_submitted_at' => now(),
                'status' => PerformanceAppraisal::STATUS_SELF_ASSESSMENT_SUBMITTED,
            ])->save();

            $this->auditTrailService->recordGeneric('performance', 'self_assessment_submitted', $locked, userId: $userId);

            return $locked->fresh(['sections.items']);
        });
    }
}
