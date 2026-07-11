<?php

declare(strict_types=1);

namespace App\Services\Hr;

use App\Models\PerformanceAppraisalModerationItem;
use App\Services\AuditTrailService;
use Illuminate\Support\Facades\DB;

class PerformanceAppraisalModerationService
{
    public function __construct(private readonly AuditTrailService $auditTrailService) {}

    public function adjust(PerformanceAppraisalModerationItem $item, float $moderatedScore, string $reason, int $userId): PerformanceAppraisalModerationItem
    {
        if (blank($reason) && abs((float) $item->original_score - $moderatedScore) > 0.0001) {
            throw new \RuntimeException('A moderation reason is required when changing the score.');
        }

        return DB::transaction(function () use ($item, $moderatedScore, $reason, $userId): PerformanceAppraisalModerationItem {
            $locked = PerformanceAppraisalModerationItem::query()->lockForUpdate()->findOrFail($item->id);
            $before = $locked->only(['moderated_score', 'moderation_reason', 'status']);

            $locked->forceFill([
                'moderated_score' => $moderatedScore,
                'moderation_reason' => $reason,
                'status' => abs((float) $locked->original_score - $moderatedScore) > 0.0001 ? 'adjusted' : 'unchanged',
                'moderated_by' => $userId,
                'moderated_at' => now(),
            ])->save();

            $this->auditTrailService->recordGeneric('performance', 'moderation_score_adjusted', $locked, userId: $userId, oldValues: $before, newValues: $locked->only(['moderated_score', 'moderation_reason', 'status']));

            return $locked->fresh();
        });
    }
}
