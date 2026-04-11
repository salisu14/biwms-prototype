<?php

declare(strict_types=1);

namespace App\Observers;

use App\Models\Manufacturing\ProductionOrder;
use App\Services\Warehouse\PickWorksheetService;
use App\Services\Warehouse\PutAwayWorksheetService;

class ProductionOrderObserver
{
    public function __construct(
        private readonly PickWorksheetService $pickService,
        private readonly PutAwayWorksheetService $putAwayService
    ) {}

    public function released(ProductionOrder $order): void
    {
        // When production order is released, create pick worksheets for components
        if ($order->location->require_pick) {
            $this->pickService->createPicksForProductionOrder($order);
        }
    }

    public function outputPosted(ProductionOrder $order, float $quantity): void
    {
        // When output is posted, create put-away for finished goods
        foreach ($order->lines as $line) {
            $this->putAwayService->createPutAwayFromProductionOutput($line, $quantity);
        }
    }

    public function finished(ProductionOrder $order): void
    {
        // Clean up any remaining open warehouse requests
        $order->warehouseRequests()
            ->where('status', 'open')
            ->update(['status' => 'cancelled']);
    }
}
