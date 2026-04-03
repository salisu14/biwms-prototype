<?php

namespace App\Services\Manufacturing;

use App\Enums\ProductionOrderStatus;
use App\Models\Manufacturing\ProductionOrder;
use Illuminate\Support\Facades\DB;

class ProductionOrderService
{
    /**
     * Refresh production order (safe wrapper)
     */
    public function refresh(int $orderId): ProductionOrder
    {
        $order = ProductionOrder::findOrFail($orderId);

        if ($order->status === ProductionOrderStatus::FINISHED) {
            throw new \Exception('Cannot refresh finished order');
        }

        DB::transaction(function () use ($order) {
            $order->refreshOrder();
        });

        return $order->fresh();
    }

    /**
     * Release production order
     */
    public function release(int $orderId, int $userId): ProductionOrder
    {
        $order = ProductionOrder::with('components.item')->findOrFail($orderId);

        DB::transaction(function () use ($order, $userId) {
            $this->validateBeforeRelease($order);

            $order->changeStatus(ProductionOrderStatus::RELEASED->value, $userId);

            if ($order->flushing_method === 'FORWARD') {
                $this->forwardFlush($order);
            }
        });

        return $order->fresh();
    }

    /**
     * Validate before releasing
     */
    protected function validateBeforeRelease(ProductionOrder $order): void
    {
        if ($order->components->isEmpty()) {
            throw new \Exception('Production order has no components');
        }

        foreach ($order->components as $component) {
            $available = $this->getAvailableInventory(
                $component->item_id,
                $component->location_code
            );

            if ($available < $component->expected_quantity) {
                throw new \Exception(
                    "Insufficient inventory for {$component->item->description}"
                );
            }
        }
    }

    /**
     * Post consumption (safe)
     */
    public function postConsumption(
        int $orderId,
        array $lines,
        int $userId,
        ?\DateTime $postingDate = null
    ): void {
        $order = ProductionOrder::with('components')->findOrFail($orderId);

        if ($order->status !== ProductionOrderStatus::RELEASED) {
            throw new \Exception('Order must be RELEASED');
        }

        DB::transaction(function () use ($order, $lines, $userId, $postingDate) {

            $consumptions = [];

            foreach ($lines as $line) {
                $component = $order->components->firstWhere('id', $line['component_id']);
                if (!$component) continue;

                if ($line['quantity'] <= 0) {
                    throw new \Exception('Quantity must be positive');
                }

                $consumptions[] = [
                    'component_id' => $component->id,
                    'quantity' => $line['quantity'],
                    'scrap_quantity' => $line['scrap_quantity'] ?? 0,
                ];
            }

            $order->postConsumption($consumptions, $userId, $postingDate);
        });
    }

    /**
     * Post output with validation
     */
    public function postOutput(
        int $orderId,
        float $quantity,
        ?int $routingLineId,
        int $userId,
        ?\DateTime $postingDate = null
    ): void {
        $order = ProductionOrder::findOrFail($orderId);

        if ($quantity <= 0) {
            throw new \Exception('Output quantity must be positive');
        }

        if ($quantity > $order->remaining_quantity) {
            throw new \Exception('Cannot overproduce');
        }

        DB::transaction(function () use ($order, $quantity, $routingLineId, $userId, $postingDate) {
            $order->postOutput($quantity, $userId, $postingDate, $routingLineId);
        });
    }

    /**
     * Post capacity safely
     */
    public function postCapacity(
        int $orderId,
        int $routingLineId,
        float $setupTime,
        float $runTime,
        float $cost,
        int $userId
    ): void {
        $order = ProductionOrder::findOrFail($orderId);

        DB::transaction(function () use ($order, $routingLineId, $setupTime, $runTime, $cost, $userId) {
            $order->postCapacity($routingLineId, $setupTime, $runTime, $cost, $userId);
        });
    }

    /**
     * Finish order safely
     */
    public function finish(int $orderId, int $userId): ProductionOrder
    {
        $order = ProductionOrder::with('routingLines')->findOrFail($orderId);

        DB::transaction(function () use ($order, $userId) {

            $this->validateBeforeFinish($order);

            $order->finish($userId);
        });

        return $order->fresh();
    }

    /**
     * Validate before finishing
     */
    protected function validateBeforeFinish(ProductionOrder $order): void
    {
        if ($order->status !== ProductionOrderStatus::RELEASED) {
            throw new \Exception('Only RELEASED orders can be finished');
        }

        $incompleteOps = $order->routingLines()
            ->where('status', '!=', 'COMPLETED')
            ->count();

        if ($incompleteOps > 0) {
            throw new \Exception("{$incompleteOps} operations incomplete");
        }

        if ($order->remaining_quantity > 0) {
            throw new \Exception('Production not fully completed');
        }
    }

    /**
     * Cancel production order
     */
    public function cancel(int $orderId): void
    {
        $order = ProductionOrder::findOrFail($orderId);

        if ($order->status === ProductionOrderStatus::FINISHED) {
            throw new \Exception('Cannot cancel finished order');
        }

        DB::transaction(function () use ($order) {
            $order->update(['status' => 'CANCELLED']);
        });
    }

    /**
     * Reopen finished order (rare, but real ERP case)
     */
    public function reopen(int $orderId): ProductionOrder
    {
        $order = ProductionOrder::findOrFail($orderId);

        if ($order->status !== ProductionOrderStatus::FINISHED) {
            throw new \Exception('Only finished orders can be reopened');
        }

        DB::transaction(function () use ($order) {
            $order->update([
                'status' => ProductionOrderStatus::RELEASED,
                'finished_at' => null,
                'finished_by' => null,
            ]);
        });

        return $order->fresh();
    }

    /**
     * Forward flush
     */
    protected function forwardFlush(ProductionOrder $order): void
    {
        foreach ($order->components as $component) {
            if ($component->flushing_method !== 'FORWARD') continue;

            $order->postConsumption([[
                'component_id' => $component->id,
                'quantity' => $component->expected_quantity,
                'scrap_quantity' => 0,
            ]], $order->created_by);
        }
    }

    /**
     * Inventory availability
     */
    protected function getAvailableInventory(int $itemId, ?string $locationCode): float
    {
        return \App\Models\ItemLedgerEntry::where('item_id', $itemId)
            ->where('location_code', $locationCode)
            ->where('open', true)
            ->sum('remaining_quantity');
    }
}
