<?php

namespace App\Services\Manufacturing;

use App\Enums\ItemLedgerEntryType;
use App\Enums\ProductionOrderStatus;
use App\Events\ProductionOrderStatusChanged;
use App\Models\ItemLedgerEntry;
use App\Models\Manufacturing\CapacityLedgerEntry;
use App\Models\Manufacturing\ProductionOrder;
use Illuminate\Support\Facades\DB;

class ProductionOrderService
{
    /**
     * Refresh production order (safe wrapper)
     */
    public function refresh(ProductionOrder $order, bool $lines = true, bool $routing = true, bool $components = true): ProductionOrder
    {
        if ($order->status === ProductionOrderStatus::FINISHED) {
            throw new \Exception('Cannot refresh finished order');
        }

        DB::transaction(function () use ($order, $lines, $routing, $components) {
            $this->refreshOrder($order, $lines, $routing, $components);
        });

        return $order->fresh();
    }

    /**
     * Release production order
     */
    public function release(ProductionOrder $order, int $userId): ProductionOrder
    {
        DB::transaction(function () use ($order, $userId) {
            $this->validateBeforeRelease($order);

            $this->changeStatus($order, ProductionOrderStatus::RELEASED, $userId);

            if ($order->flushing_method === 'FORWARD') {
                $this->forwardFlush($order, $userId);
            }
        });

        return $order->fresh();
    }

    /**
     * Cancel production order
     */
    public function cancel(ProductionOrder $order, ?int $userId = null): void
    {
        if ($order->status === ProductionOrderStatus::FINISHED) {
            throw new \Exception('Cannot cancel finished order');
        }

        DB::transaction(function () use ($order, $userId) {
            $this->changeStatus($order, ProductionOrderStatus::CANCELLED, $userId);
        });
    }

    /**
     * Reopen finished order
     */
    public function reopen(ProductionOrder $order): ProductionOrder
    {
        if ($order->status !== ProductionOrderStatus::FINISHED) {
            throw new \Exception('Only finished orders can be reopened');
        }

        DB::transaction(function () use ($order) {
            $this->changeStatus($order, ProductionOrderStatus::RELEASED);
            $order->update([
                'finished_at' => null,
                'finished_by' => null,
            ]);
        });

        return $order->fresh();
    }

    /**
     * Post consumption
     */
    public function postConsumption(
        ProductionOrder $order,
        array $lines,
        int $userId,
        ?\DateTime $postingDate = null
    ): void {
        if ($order->status !== ProductionOrderStatus::RELEASED) {
            throw new \Exception('Order must be RELEASED');
        }

        $postingDate = $postingDate ?? now();

        DB::transaction(function () use ($order, $lines, $postingDate) {
            foreach ($lines as $line) {
                $component = $order->components->firstWhere('id', $line['component_id']);
                if (! $component) {
                    continue;
                }

                $qty = $line['quantity'];
                $scrapQty = $line['scrap_quantity'] ?? 0;

                if ($qty <= 0) {
                    throw new \Exception('Quantity must be positive');
                }

                ItemLedgerEntry::create([
                    'entry_type' => ItemLedgerEntryType::CONSUMPTION,
                    'item_id' => $component->item_id,
                    'quantity' => -$qty,
                    'remaining_quantity' => 0,
                    'open' => false,
                    'posting_date' => $postingDate,
                    'document_number' => $order->document_number,
                    'external_document_number' => $component->line_number,
                    'source_id' => $order->id,
                    'source_type' => ProductionOrder::class,
                    'location_code' => $component->location_code,
                    'unit_cost' => $component->item->unit_cost,
                    'cost_amount_actual' => $qty * $component->item->unit_cost,
                ]);

                $component->actual_quantity_consumed += $qty;
                $component->actual_scrap_quantity += $scrapQty;
                $component->save();
            }

            $this->createWipGlEntries($order, $postingDate);
        });
    }

    /**
     * Post output
     */
    public function postOutput(
        ProductionOrder $order,
        float $quantity,
        int $userId,
        ?\DateTime $postingDate = null,
        ?int $routingLineId = null
    ): void {
        if ($quantity <= 0) {
            throw new \Exception('Output quantity must be positive');
        }

        if ($quantity > $order->remaining_quantity) {
            throw new \Exception('Cannot overproduce');
        }

        $postingDate = $postingDate ?? now();

        DB::transaction(function () use ($order, $quantity, $postingDate, $routingLineId) {
            ItemLedgerEntry::create([
                'entry_type' => ItemLedgerEntryType::OUTPUT,
                'item_id' => $order->item_id,
                'quantity' => $quantity,
                'remaining_quantity' => $quantity,
                'open' => true,
                'posting_date' => $postingDate,
                'document_number' => $order->document_number,
                'source_id' => $order->id,
                'source_type' => ProductionOrder::class,
                'location_code' => $order->location_code,
                'unit_cost' => 0, // Calculated at finish
            ]);

            if ($routingLineId) {
                $routingLine = $order->routingLines()->find($routingLineId);
                if ($routingLine) {
                    $routingLine->actual_output_quantity += $quantity;
                    $routingLine->save();
                }
            }
        });
    }

    /**
     * Post capacity
     */
    public function postCapacity(
        ProductionOrder $order,
        int $routingLineId,
        float $setupTime,
        float $runTime,
        float $cost,
        int $userId
    ): void {
        $routingLine = $order->routingLines()->find($routingLineId);
        if (! $routingLine) {
            return;
        }

        DB::transaction(function () use ($order, $routingLineId, $routingLine, $setupTime, $runTime, $cost) {
            CapacityLedgerEntry::create([
                'production_order_id' => $order->id,
                'routing_line_id' => $routingLineId,
                'work_center_id' => $routingLine->work_center_id,
                'machine_center_id' => $routingLine->machine_center_id,
                'posting_date' => now(),
                'setup_time' => $setupTime,
                'run_time' => $runTime,
                'setup_time_unit' => $routingLine->setup_time_unit,
                'run_time_unit' => $routingLine->run_time_unit,
                'direct_cost' => $cost,
                'overhead_cost' => $cost * 0.25,
                'total_cost' => $cost * 1.25,
                'document_number' => $order->document_number,
            ]);
        });
    }

    /**
     * Finish production order
     */
    public function finish(ProductionOrder $order, int $userId, ?\DateTime $postingDate = null): ProductionOrder
    {
        $postingDate = $postingDate ?? now();

        DB::transaction(function () use ($order, $userId, $postingDate) {
            $this->validateBeforeFinish($order);

            if ($order->flushing_method === 'BACKWARD') {
                $this->backwardFlushComponents($order, $postingDate, $userId);
            }

            $totalCost = $order->total_actual_cost;
            $totalOutput = $order->itemLedgerEntries()
                ->where('entry_type', ItemLedgerEntryType::OUTPUT)
                ->sum('quantity');

            $unitCost = $totalOutput > 0 ? $totalCost / $totalOutput : 0;

            $order->itemLedgerEntries()
                ->where('entry_type', ItemLedgerEntryType::OUTPUT)
                ->update([
                    'unit_cost' => $unitCost,
                    'cost_amount_actual' => DB::raw("quantity * {$unitCost}"),
                ]);

            $this->createFinishGlEntries($order, $totalCost, $postingDate);

            $this->changeStatus($order, ProductionOrderStatus::FINISHED, $userId);

            if ($order->costing_method === 'STANDARD') {
                $variance = $totalCost - ($order->unit_cost * $totalOutput);
                if (abs($variance) > 0.01) {
                    $this->createVarianceGlEntries($order, $variance, $postingDate);
                }
            }
        });

        return $order->fresh();
    }

    /**
     * Status Management
     */
    public function changeStatus(ProductionOrder $order, ProductionOrderStatus|string $newStatus, ?int $userId = null): void
    {
        if (is_string($newStatus)) {
            $newStatus = ProductionOrderStatus::from($newStatus);
        }

        if (! $order->status->canTransitionTo($newStatus)) {
            throw new \Exception("Invalid status transition from {$order->status->label()} to {$newStatus->label()}");
        }

        if ($newStatus === ProductionOrderStatus::RELEASED) {
            $this->validateBeforeRelease($order);
        }

        if ($newStatus === ProductionOrderStatus::FINISHED) {
            $this->validateForFinish($order);
        }

        $oldStatus = $order->status;
        $order->status = $newStatus;

        if ($newStatus === ProductionOrderStatus::FINISHED) {
            $order->finished_at = now();
            $order->finished_by = $userId;
        }

        $order->save();

        event(new ProductionOrderStatusChanged($order, $oldStatus, $newStatus));
    }

    /**
     * Internal Logic Handlers
     */
    protected function refreshOrder(ProductionOrder $order, bool $lines, bool $routing, bool $components): void
    {
        if ($lines) {
            $this->refreshLines($order);
        }

        if ($routing) {
            $this->refreshRouting($order);
        }

        if ($components) {
            $this->refreshComponents($order);
        }

        $this->scheduleOrder($order);
    }

    protected function refreshLines(ProductionOrder $order): void
    {
        if ($order->itemLedgerEntries()->count() === 0) {
            $order->lines()->delete();
        }

        $order->lines()->create([
            'line_number' => 10000,
            'item_id' => $order->item_id,
            'description' => $order->description,
            'quantity' => $order->quantity,
            'unit_of_measure_code' => $order->unit_of_measure_code ?? $order->item->base_unit_of_measure,
            'quantity_base' => $order->quantity_base,
            'due_date' => $order->due_date,
            'production_bom_id' => $order->production_bom_id,
            'routing_id' => $order->routing_id,
        ]);
    }

    protected function refreshComponents(ProductionOrder $order): void
    {
        if (! $order->production_bom_id) {
            return;
        }

        if ($order->itemLedgerEntries()->where('entry_type', ItemLedgerEntryType::CONSUMPTION)->count() === 0) {
            $order->components()->delete();
        }

        $bom = $order->productionBom;
        if (! $bom) {
            return;
        }

        $lineNo = 10000;
        foreach ($bom->lines as $bomLine) {
            $expectedQty = $bomLine->quantity_per * $order->quantity * (1 + $bomLine->scrap_percent / 100);

            $order->components()->create([
                'line_number' => $lineNo,
                'item_id' => $bomLine->item_id,
                'description' => $bomLine->description,
                'unit_of_measure_code' => $bomLine->unit_of_measure_code,
                'quantity_per' => $bomLine->quantity_per,
                'expected_quantity' => $expectedQty,
                'expected_quantity_base' => $expectedQty * $bomLine->item->qty_per_unit_of_measure,
                'scrap_percent' => $bomLine->scrap_percent,
                'routing_link_code' => $bomLine->routing_link_code,
                'flushing_method' => $bomLine->flushing_method ?? $order->flushing_method,
                'location_code' => $bomLine->location_code ?? $order->location_code,
                'bin_code' => $bomLine->bin_code,
                'due_date' => $order->starting_date_time?->copy()->subDays($bomLine->lead_time_offset_days ?? 0),
            ]);

            $lineNo += 10000;
        }
    }

    protected function refreshRouting(ProductionOrder $order): void
    {
        if (! $order->routing_id) {
            return;
        }

        if ($order->capacityLedgerEntries()->count() === 0) {
            $order->routingLines()->delete();
        }

        $routing = $order->routing;
        if (! $routing) {
            return;
        }

        $lineNo = 10000;
        foreach ($routing->lines as $routingLine) {
            $order->routingLines()->create([
                'line_number' => $lineNo,
                'operation_no' => $routingLine->operation_no,
                'description' => $routingLine->description,
                'work_center_id' => $routingLine->work_center_id,
                'machine_center_id' => $routingLine->machine_center_id,
                'setup_time' => $routingLine->setup_time,
                'run_time' => $routingLine->run_time * $order->quantity,
                'wait_time' => $routingLine->wait_time,
                'move_time' => $routingLine->move_time,
                'setup_time_unit' => $routingLine->setup_time_unit,
                'run_time_unit' => $routingLine->run_time_unit,
                'routing_link_code' => $routingLine->routing_link_code,
                'scrap_factor_percent' => $routingLine->scrap_factor_percent,
            ]);

            $lineNo += 10000;
        }
    }

    protected function scheduleOrder(ProductionOrder $order, bool $forward = false): void
    {
        if ($forward) {
            $currentDate = $order->starting_date_time ?? now();
            foreach ($order->routingLines()->orderBy('line_number')->get() as $routingLine) {
                $routingLine->starting_date_time = $currentDate;
                $routingLine->ending_date_time = $currentDate->copy()->addMinutes($routingLine->total_time_minutes);
                $routingLine->save();
                $currentDate = $routingLine->ending_date_time->copy()->addMinutes($routingLine->move_time);
            }
            $order->ending_date_time = $currentDate;
        } else {
            $currentDate = $order->due_date?->copy()->subDay() ?? now();
            foreach ($order->routingLines()->orderByDesc('line_number')->get() as $routingLine) {
                $routingLine->ending_date_time = $currentDate;
                $routingLine->starting_date_time = $currentDate->copy()->subMinutes($routingLine->total_time_minutes);
                $routingLine->save();
                $currentDate = $routingLine->starting_date_time->copy()->subMinutes($routingLine->wait_time);
            }
            $order->starting_date_time = $currentDate;
        }

        $order->save();
    }

    protected function backwardFlushComponents(ProductionOrder $order, \DateTime $postingDate, int $userId): void
    {
        foreach ($order->components as $component) {
            if ($component->flushing_method !== 'BACKWARD') {
                continue;
            }

            $remainingQty = $component->expected_quantity - $component->actual_quantity_consumed;
            if ($remainingQty <= 0) {
                continue;
            }

            $this->postConsumption($order, [[
                'component_id' => $component->id,
                'quantity' => $remainingQty,
                'scrap_quantity' => 0,
            ]], $userId, $postingDate);
        }
    }

    protected function forwardFlush(ProductionOrder $order, int $userId): void
    {
        foreach ($order->components as $component) {
            if ($component->flushing_method !== 'FORWARD') {
                continue;
            }

            $this->postConsumption($order, [[
                'component_id' => $component->id,
                'quantity' => $component->expected_quantity,
                'scrap_quantity' => 0,
            ]], $userId, $order->starting_date_time);
        }
    }

    /**
     * Validations
     */
    protected function validateBeforeRelease(ProductionOrder $order): void
    {
        if ($order->components->isEmpty()) {
            throw new \Exception('Production order has no components');
        }

        foreach ($order->components as $component) {
            $available = $this->getAvailableInventory($component->item_id, $component->location_code);
            if ($available < $component->expected_quantity) {
                throw new \Exception("Insufficient inventory for {$component->item->description}");
            }
        }
    }

    protected function validateBeforeFinish(ProductionOrder $order): void
    {
        if ($order->status !== ProductionOrderStatus::RELEASED) {
            throw new \Exception('Only RELEASED orders can be finished');
        }

        $incompleteOps = $order->routingLines()->where('status', '!=', 'COMPLETED')->count();
        if ($incompleteOps > 0) {
            throw new \Exception("{$incompleteOps} operations incomplete");
        }

        if ($order->remaining_quantity > 0) {
            throw new \Exception('Production not fully completed');
        }
    }

    protected function validateForFinish(ProductionOrder $order): void
    {
        if ($order->status !== ProductionOrderStatus::RELEASED) {
            throw new \Exception('Only released production orders can be finished');
        }
    }

    /**
     * G/L Entries Logic (Placeholders)
     */
    protected function createWipGlEntries(ProductionOrder $order, \DateTime $postingDate): void
    {
        // Placeholder for WIP G/L entries
    }

    protected function createFinishGlEntries(ProductionOrder $order, float $totalCost, \DateTime $postingDate): void
    {
        // Placeholder for Finished Goods G/L entries
    }

    protected function createVarianceGlEntries(ProductionOrder $order, float $variance, \DateTime $postingDate): void
    {
        // Placeholder for Variance G/L entries
    }

    /**
     * Inventory Helpers
     */
    protected function getAvailableInventory(int $itemId, ?string $locationCode): float
    {
        return ItemLedgerEntry::where('item_id', $itemId)
            ->where('location_code', $locationCode)
            ->where('open', true)
            ->sum('remaining_quantity');
    }

    /**
     * Document Generators
     */
    public function generateDocumentNumber(): string
    {
        $prefix = 'PROD';
        $year = date('Y');
        $count = ProductionOrder::whereYear('created_at', $year)->count() + 1;

        return sprintf('%s-%d-%06d', $prefix, $year, $count);
    }
}
