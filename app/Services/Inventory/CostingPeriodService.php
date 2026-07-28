<?php

declare(strict_types=1);

namespace App\Services\Inventory;

use App\Models\CostingPeriod;
use Carbon\Carbon;
use DateTimeInterface;
use RuntimeException;

class CostingPeriodService
{
    public function assertApplicationMutable(DateTimeInterface|string|null $postingDate): void
    {
        $period = $this->periodFor($postingDate);

        if ($period?->is_closed) {
            throw new RuntimeException('Item applications cannot be changed in a closed costing period.');
        }
    }

    public function adjustmentPostingDate(DateTimeInterface|string|null $sourcePostingDate): Carbon
    {
        $period = $this->periodFor($sourcePostingDate);

        if (! $period?->is_closed) {
            return Carbon::parse($sourcePostingDate ?? now())->startOfDay();
        }

        if (! $period->cost_adjustment_posting_date) {
            throw new RuntimeException('Closed costing period does not define an allowed cost adjustment posting date.');
        }

        return Carbon::parse($period->cost_adjustment_posting_date)->startOfDay();
    }

    public function assertAdjustmentAllowed(DateTimeInterface|string|null $sourcePostingDate): void
    {
        $period = $this->periodFor($sourcePostingDate);

        if (! $period?->is_closed) {
            return;
        }

        if (! $period->adjustment_allowed_through || Carbon::parse($sourcePostingDate)->gt($period->adjustment_allowed_through)) {
            throw new RuntimeException('Cost adjustment is not allowed for this closed costing period.');
        }
    }

    private function periodFor(DateTimeInterface|string|null $date): ?CostingPeriod
    {
        if (! $date) {
            return null;
        }

        return CostingPeriod::query()
            ->containingDate(Carbon::parse($date)->toDateString())
            ->first();
    }
}
