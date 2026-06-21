<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\WarehouseActivity;
use App\Models\WarehouseRequest;
use App\Services\Warehouse\WarehousePostingService;

class WarehouseActivityObserver
{
    public function __construct(
        private readonly WarehousePostingService $postingService
    ) {}

    public function updated(WarehouseActivity $activity): void
    {
        // When status changes to COMPLETED, post entries
        if ($activity->isComplete() && $activity->getOriginal('status') !== 'completed') {
            $this->completeActivity($activity);
        }
    }

    private function completeActivity(WarehouseActivity $activity): void
    {
        foreach ($activity->lines as $line) {
            if ($line->line_status === 'completed' && $line->quantity_handled > 0) {
                $this->postingService->postWarehouseEntry($line, $line->quantity_handled);
            }
        }

        // Update source warehouse request
        WarehouseRequest::where('warehouse_activity_id', $activity->id)
            ->update(['status' => 'completed']);
    }
}
