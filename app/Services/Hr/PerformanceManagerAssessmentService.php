<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\PerformanceAppraisal;
use App\Services\AuditTrailService;
use Illuminate\Support\Facades\DB;

class PerformanceManagerAssessmentService
{
    public function __construct(private readonly AuditTrailService $auditTrailService) {}

    /**
     * @param  array<int, array{item_id: int, rating?: float|null, comment?: string|null}>  $items
     */
    public function submit(PerformanceAppraisal $appraisal, int $managerEmployeeId, int $userId, array $items, ?string $overallComment = null): PerformanceAppraisal
    {
        if ((int) $appraisal->manager_employee_id !== $managerEmployeeId) {
            throw new \RuntimeException('Manager assessment is limited to assigned reports.');
        }

        if ($appraisal->manager_submitted_at !== null) {
            throw new \RuntimeException('Submitted manager assessment is locked.');
        }

        return DB::transaction(function () use ($appraisal, $items, $overallComment, $userId): PerformanceAppraisal {
            $locked = PerformanceAppraisal::query()->lockForUpdate()->findOrFail($appraisal->id);

            foreach ($items as $payload) {
                $locked->items()->whereKey($payload['item_id'])->update([
                    'manager_rating' => $payload['rating'] ?? null,
                    'manager_comment' => $payload['comment'] ?? null,
                ]);
            }

            $locked->forceFill([
                'manager_overall_comment' => $overallComment,
                'manager_submitted_at' => now(),
                'status' => PerformanceAppraisal::STATUS_MANAGER_REVIEW_SUBMITTED,
            ])->save();

            $this->auditTrailService->recordGeneric('performance', 'manager_assessment_submitted', $locked, userId: $userId);

            return $locked->fresh(['sections.items']);
        });
    }
}
