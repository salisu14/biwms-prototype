<?php

namespace App\Services\Manufacturing;

use App\Enums\ItemLedgerEntryType;
use App\Enums\LineType;
use App\Enums\ProductionOrderStatus;
use App\Events\ProductionOrderStatusChanged;
use App\Models\GlEntry;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\Manufacturing\CapacityLedgerEntry;
use App\Models\Manufacturing\ProductionOrder;
use App\Services\PostingService;
use Illuminate\Support\Facades\DB;

class ProductionOrderService
{
    public function __construct(
        protected PostingService $postingService
    ) {}

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

                // G/L Integration: Dr. WIP, Cr. Inventory
                $this->createWipGlEntries(
                    $order,
                    $component->item,
                    $qty * $component->item->unit_cost,
                    $postingDate,
                    "Consumption: {$component->item->description}"
                );
            }
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
            $workCenter = $routingLine->workCenter;
            $totalTime = $setupTime + $runTime;

            // Calculate Indirect Cost (Overhead)
            $indirectCost = 0;
            if ($workCenter) {
                $indirectCost = ($cost * ($workCenter->indirect_cost_percent / 100)) + ($workCenter->overhead_rate * $totalTime);
            }

            $totalCost = $cost + $indirectCost;

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
                'overhead_cost' => $indirectCost,
                'total_cost' => $totalCost,
                'document_number' => $order->document_number,
            ]);

            // G/L Integration: Dr. WIP, Cr. Direct Cost Applied, Cr. Overhead Applied
            $this->createCapacityGlEntries(
                $order,
                $cost,
                $indirectCost,
                now(),
                "Capacity: {$routingLine->description}"
            );
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

            $totalActualCost = $order->total_actual_cost;
            $totalOutput = $order->itemLedgerEntries()
                ->where('entry_type', ItemLedgerEntryType::OUTPUT)
                ->sum('quantity');

            // Determine the cost to record in Inventory
            $inventoryUnitCost = ($order->costing_method === 'STANDARD')
                ? $order->unit_cost
                : ($totalOutput > 0 ? $totalActualCost / $totalOutput : 0);

            $totalInventoryCost = $totalOutput * $inventoryUnitCost;

            // Update Output entries with the determined cost
            $order->itemLedgerEntries()
                ->where('entry_type', ItemLedgerEntryType::OUTPUT)
                ->update([
                    'unit_cost' => $inventoryUnitCost,
                    'cost_amount_actual' => $totalInventoryCost,
                ]);

            // G/L Integration:
            // 1. Move from WIP to Inventory (at the cost we recorded in Inventory)
            $this->createFinishGlEntries($order, $totalInventoryCost, $postingDate);

            // 2. Clear remaining WIP by posting to Variance (if any)
            $variance = $totalActualCost - $totalInventoryCost;
            if (abs($variance) > 0.01) {
                $this->createVarianceGlEntries($order, $variance, $postingDate);
            }

            $this->changeStatus($order, ProductionOrderStatus::FINISHED, $userId);
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

    protected function createWipGlEntries(ProductionOrder $order, Item $item, float $amount, \DateTime $postingDate, string $description): void
    {
        $location = Location::where('location_code', $order->location_code)->first();
        $locationId = $location?->id;

        // 1. Inventory Account (Credit) - for the component being consumed
        $inventorySetup = InventoryPostingSetup::getFor($item->inventory_posting_group_id, $locationId);
        if (! $inventorySetup || ! $inventorySetup->inventory_account_id) {
            throw new \Exception("Inventory account missing for component {$item->item_number}");
        }

        // 2. WIP Account (Debit) - for the parent production order item
        $parentSetup = InventoryPostingSetup::getFor($order->inventory_posting_group_id, $locationId);
        if (! $parentSetup || ! $parentSetup->wip_account_id) {
            throw new \Exception("WIP account missing for production order {$order->document_number}");
        }

        $transactionNumber = (GlEntry::max('transaction_number') ?? 0) + 1;

        // WIP Entry (Debit)
        GlEntry::create([
            'entry_number' => (GlEntry::max('entry_number') ?? 0) + 1,
            'transaction_number' => $transactionNumber,
            'chart_of_account_id' => $parentSetup->wip_account_id,
            'debit_amount' => $amount,
            'credit_amount' => 0,
            'amount' => $amount,
            'posting_date' => $postingDate,
            'document_date' => $postingDate,
            'document_number' => $order->document_number,
            'description' => $description,
            'sourceable_type' => ProductionOrder::class,
            'sourceable_id' => $order->id,
            'user_id' => auth()->id(),
        ]);

        // Inventory Entry (Credit)
        GlEntry::create([
            'entry_number' => (GlEntry::max('entry_number') ?? 0) + 1,
            'transaction_number' => $transactionNumber,
            'chart_of_account_id' => $inventorySetup->inventory_account_id,
            'debit_amount' => 0,
            'credit_amount' => $amount,
            'amount' => -$amount,
            'posting_date' => $postingDate,
            'document_date' => $postingDate,
            'document_number' => $order->document_number,
            'description' => $description,
            'sourceable_type' => ProductionOrder::class,
            'sourceable_id' => $order->id,
            'user_id' => auth()->id(),
        ]);
    }

    protected function createCapacityGlEntries(ProductionOrder $order, float $directCost, float $indirectCost, \DateTime $postingDate, string $description): void
    {
        $location = Location::where('location_code', $order->location_code)->first();
        $locationId = $location?->id;

        // 1. WIP Account (Debit)
        $parentSetup = InventoryPostingSetup::getFor($order->inventory_posting_group_id, $locationId);
        if (! $parentSetup || ! $parentSetup->wip_account_id) {
            throw new \Exception("WIP account missing for production order {$order->document_number}");
        }

        // 2. Direct Cost Applied (Credit)
        $genSetup = $order->getPostingSetup();
        if (! $genSetup) {
            throw new \Exception("General Posting Setup missing for item {$order->item->item_number}");
        }

        $appliedAccount = $genSetup->getDirectCostAppliedAccount();
        if (! $appliedAccount) {
            throw new \Exception('Direct Cost Applied account missing in General Posting Setup');
        }

        $overheadAccount = $genSetup->getOverheadAppliedAccount();

        $transactionNumber = (GlEntry::max('transaction_number') ?? 0) + 1;
        $totalCost = $directCost + $indirectCost;

        // WIP Entry (Debit)
        GlEntry::create([
            'entry_number' => (GlEntry::max('entry_number') ?? 0) + 1,
            'transaction_number' => $transactionNumber,
            'chart_of_account_id' => $parentSetup->wip_account_id,
            'debit_amount' => $totalCost,
            'credit_amount' => 0,
            'amount' => $totalCost,
            'posting_date' => $postingDate,
            'document_date' => $postingDate,
            'document_number' => $order->document_number,
            'description' => $description,
            'sourceable_type' => ProductionOrder::class,
            'sourceable_id' => $order->id,
            'user_id' => auth()->id(),
        ]);

        // Direct Applied Entry (Credit)
        GlEntry::create([
            'entry_number' => (GlEntry::max('entry_number') ?? 0) + 1,
            'transaction_number' => $transactionNumber,
            'chart_of_account_id' => $appliedAccount->id,
            'debit_amount' => 0,
            'credit_amount' => $directCost,
            'amount' => -$directCost,
            'posting_date' => $postingDate,
            'document_date' => $postingDate,
            'document_number' => $order->document_number,
            'description' => $description.' (Direct)',
            'sourceable_type' => ProductionOrder::class,
            'sourceable_id' => $order->id,
            'user_id' => auth()->id(),
        ]);

        // Overhead Applied Entry (Credit)
        if ($indirectCost > 0 && $overheadAccount) {
            GlEntry::create([
                'entry_number' => (GlEntry::max('entry_number') ?? 0) + 1,
                'transaction_number' => $transactionNumber,
                'chart_of_account_id' => $overheadAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $indirectCost,
                'amount' => -$indirectCost,
                'posting_date' => $postingDate,
                'document_date' => $postingDate,
                'document_number' => $order->document_number,
                'description' => $description.' (Overhead)',
                'sourceable_type' => ProductionOrder::class,
                'sourceable_id' => $order->id,
                'user_id' => auth()->id(),
            ]);
        }
    }

    protected function createFinishGlEntries(ProductionOrder $order, float $totalWip, \DateTime $postingDate): void
    {
        $location = Location::where('location_code', $order->location_code)->first();
        $locationId = $location?->id;

        // 1. Inventory Account (Debit) - for the finished good
        $parentSetup = InventoryPostingSetup::getFor($order->inventory_posting_group_id, $locationId);
        if (! $parentSetup || ! $parentSetup->inventory_account_id) {
            throw new \Exception("Inventory account missing for finished item {$order->item->item_number}");
        }

        if (! $parentSetup->wip_account_id) {
            throw new \Exception("WIP account missing for finished item {$order->item->item_number}");
        }

        $transactionNumber = (GlEntry::max('transaction_number') ?? 0) + 1;

        // Inventory Entry (Debit)
        GlEntry::create([
            'entry_number' => (GlEntry::max('entry_number') ?? 0) + 1,
            'transaction_number' => $transactionNumber,
            'chart_of_account_id' => $parentSetup->inventory_account_id,
            'debit_amount' => $totalWip,
            'credit_amount' => 0,
            'amount' => $totalWip,
            'posting_date' => $postingDate,
            'document_date' => $postingDate,
            'document_number' => $order->document_number,
            'description' => "Finish Production: {$order->document_number}",
            'sourceable_type' => ProductionOrder::class,
            'sourceable_id' => $order->id,
            'user_id' => auth()->id(),
        ]);

        // WIP Entry (Credit)
        GlEntry::create([
            'entry_number' => (GlEntry::max('entry_number') ?? 0) + 1,
            'transaction_number' => $transactionNumber,
            'chart_of_account_id' => $parentSetup->wip_account_id,
            'debit_amount' => 0,
            'credit_amount' => $totalWip,
            'amount' => -$totalWip,
            'posting_date' => $postingDate,
            'document_date' => $postingDate,
            'document_number' => $order->document_number,
            'description' => "Finish Production: {$order->document_number}",
            'sourceable_type' => ProductionOrder::class,
            'sourceable_id' => $order->id,
            'user_id' => auth()->id(),
        ]);
    }

    protected function createVarianceGlEntries(ProductionOrder $order, float $variance, \DateTime $postingDate): void
    {
        if ($variance == 0) {
            return;
        }

        $location = Location::where('location_code', $order->location_code)->first();
        $locationId = $location?->id;

        // 1. WIP Account (to clear remaining WIP)
        $parentSetup = InventoryPostingSetup::getFor($order->inventory_posting_group_id, $locationId);
        if (! $parentSetup || ! $parentSetup->wip_account_id) {
            throw new \Exception('WIP account missing for production order variance');
        }

        // 2. Production Variance Account
        $genSetup = $order->getPostingSetup();
        $varianceAccountLine = $genSetup?->lines()->where('line_type', LineType::PRODUCTION_VARIANCE)->first();
        if (! $varianceAccountLine) {
            // Fallback to COGS or Adjustment if no specific variance account
            $varianceAccount = $genSetup?->getInventoryAdjustmentAccount();
        } else {
            $varianceAccount = $varianceAccountLine->chartOfAccount;
        }

        if (! $varianceAccount) {
            throw new \Exception('Production Variance account missing in General Posting Setup');
        }

        $transactionNumber = (GlEntry::max('transaction_number') ?? 0) + 1;

        // WIP Adjustment
        GlEntry::create([
            'entry_number' => (GlEntry::max('entry_number') ?? 0) + 1,
            'transaction_number' => $transactionNumber,
            'chart_of_account_id' => $parentSetup->wip_account_id,
            'debit_amount' => $variance < 0 ? abs($variance) : 0,
            'credit_amount' => $variance > 0 ? $variance : 0,
            'amount' => -$variance,
            'posting_date' => $postingDate,
            'document_date' => $postingDate,
            'document_number' => $order->document_number,
            'description' => "Production Variance: {$order->document_number}",
            'sourceable_type' => ProductionOrder::class,
            'sourceable_id' => $order->id,
            'user_id' => auth()->id(),
        ]);

        // Variance Entry
        GlEntry::create([
            'entry_number' => (GlEntry::max('entry_number') ?? 0) + 1,
            'transaction_number' => $transactionNumber,
            'chart_of_account_id' => $varianceAccount->id,
            'debit_amount' => $variance > 0 ? $variance : 0,
            'credit_amount' => $variance < 0 ? abs($variance) : 0,
            'amount' => $variance,
            'posting_date' => $postingDate,
            'document_date' => $postingDate,
            'document_number' => $order->document_number,
            'description' => "Production Variance: {$order->document_number}",
            'sourceable_type' => ProductionOrder::class,
            'sourceable_id' => $order->id,
            'user_id' => auth()->id(),
        ]);
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
