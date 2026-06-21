<?php

namespace App\Services\Manufacturing;

use App\Enums\DocumentType;
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
use App\Models\Manufacturing\ProductionBom;
use App\Models\Manufacturing\ProductionBomLine;
use App\Models\Manufacturing\ProductionBomVersion;
use App\Models\Manufacturing\ProductionBomVersionLine;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionOrderRoutingLine;
use App\Models\Manufacturing\RoutingVersion;
use App\Models\User;
use App\Services\Inventory\CostingService;
use App\Services\Inventory\ValueEntryService;
use App\Services\NumberSeriesService;
use App\Services\Posting\InventoryPostingResolverService;
use App\Services\PostingService;
use App\Services\Warehouse\PickWorksheetService;
use App\Services\Warehouse\PutAwayWorksheetService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class ProductionOrderService
{
    private const MAX_DECIMAL_15_4 = 99999999999.9999;

    private const MAX_BOM_EXPLOSION_DEPTH = 25;

    private const MAX_CAPACITY_COST_TO_ORDER_VALUE_RATIO = 100;

    public function __construct(
        protected PostingService $postingService,
        protected PickWorksheetService $pickService,
        protected PutAwayWorksheetService $putAwayService,
        protected CostingService $costingService,
        protected InventoryPostingResolverService $inventoryPostingResolver
    ) {}

    /**
     * Refresh production order (safe wrapper)
     *
     * @throws \Throwable
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
     *
     * @throws \Throwable
     */
    public function release(ProductionOrder $order, int $userId): ProductionOrder
    {
        DB::transaction(function () use ($order, $userId) {
            $this->changeStatus($order, ProductionOrderStatus::RELEASED, $userId);

            if (str_contains((string) $order->flushing_method, 'FORWARD')) {
                $this->forwardFlush($order, $userId);
            }

            // Create Warehouse Picks for components
            $this->pickService->createPicksForProductionOrder($order);
        });

        return $order->fresh();
    }

    /**
     * Cancel production order
     *
     * @throws \Throwable
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
     *
     * @throws \Throwable
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
     *
     * @throws \Throwable
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

                $qty = (float) $line['quantity'];
                $scrapQty = (float) ($line['scrap_quantity'] ?? 0);

                if ($qty <= 0) {
                    throw new \Exception('Quantity must be positive');
                }

                // ✅ FIXED: Calculate Base Quantity based on component UoM ratio
                $conversionFactor = ($component->expected_quantity_base > 0 && $component->expected_quantity > 0)
                    ? (float) $component->expected_quantity_base / (float) $component->expected_quantity
                    : 1.0;
                $qtyBase = $qty * $conversionFactor;
                $remainingQuantityBase = max(
                    0.0,
                    (float) $component->expected_quantity_base - (float) $component->actual_quantity_consumed
                );

                if ($qtyBase > $remainingQuantityBase + 0.0001) {
                    throw new \Exception('Cannot consume more than the remaining component quantity');
                }

                $actualUnitCost = $this->costingService->getUnitCost(
                    $component->item,
                    $component->location,
                    null, // lot
                    $postingDate->format('Y-m-d')
                );

                ItemLedgerEntry::create([
                    'entry_type' => ItemLedgerEntryType::CONSUMPTION,
                    'item_id' => $component->item_id,
                    // ✅ FIXED: Always use negative BASE quantity for consumption
                    'quantity' => -$qtyBase,
                    'remaining_quantity' => 0,
                    'open' => false,
                    'posting_date' => $postingDate,
                    'document_number' => $order->document_number,
                    'document_line_number' => $component->line_number,
                    'source_id' => $order->id,
                    'source_type' => ProductionOrder::class,
                    'location_id' => $component->location?->id,
                    // ✅ FIXED: Removed 'unit_cost', use total actual cost instead
                    'cost_amount_actual' => $qtyBase * $actualUnitCost,
                    'dimensions' => $order->dimension_set_id,
                    'general_product_posting_group_id' => $component->item->general_product_posting_group_id,
                    'inventory_posting_group_id' => $component->item->inventory_posting_group_id,
                    'entry_date' => now(),
                ]);

                // ✅ FIXED: Track consumption in base quantities to prevent math errors
                $component->actual_quantity_consumed = (float) $component->actual_quantity_consumed + $qtyBase;
                $component->actual_scrap_quantity = (float) $component->actual_scrap_quantity + $scrapQty;
                $component->remaining_quantity = max(
                    0,
                    (float) $component->expected_quantity_base - (float) $component->actual_quantity_consumed
                );
                $component->save();

                // Update CapEx Project if linked
                if ($order->capex_project_id && $order->capexProject) {
                    $order->capexProject->increment('actual_amount', $qtyBase * $actualUnitCost);
                }

                // G/L Integration: Dr. WIP, Cr. Inventory
                $this->createWipGlEntries(
                    $order,
                    $component->item,
                    $qtyBase * $actualUnitCost,
                    $postingDate,
                    "Consumption: {$component->item->description}"
                );
            }
        });
    }

    /**
     * Post output
     *
     * @throws \Throwable
     */
    public function postOutput(
        ProductionOrder $order,
        float $quantityBase,
        int $userId,
        ?\DateTime $postingDate = null,
        ?int $routingLineId = null
    ): void {
        Gate::forUser(User::query()->findOrFail($userId))->authorize('postOutput', $order);

        if ($quantityBase <= 0) {
            throw new \Exception('Output quantity must be positive');
        }

        // Note: $quantityBase passed here MUST be in BASE units.
        if ($quantityBase > (float) $order->remaining_quantity) {
            throw new \Exception('Cannot overproduce');
        }

        $postingDate = $postingDate ?? now();

        DB::transaction(function () use ($order, $quantityBase, $postingDate, $routingLineId) {
            $expectedUnitCost = (float) ($order->cost_rollup ?? $order->unit_cost ?? 0);
            $locationId = Location::query()
                ->where('code', $order->location_code)
                ->value('id');

            ItemLedgerEntry::create([
                'entry_type' => ItemLedgerEntryType::OUTPUT,
                'item_id' => $order->item_id,
                // ✅ FIXED: Use BASE quantity for Output
                'quantity' => $quantityBase,
                'remaining_quantity' => $quantityBase,
                'open' => true,
                'posting_date' => $postingDate,
                'document_number' => $order->document_number,
                'document_line_number' => $order->lines()->firstWhere('item_id', $order->item_id)?->line_number ?? 10000,
                'source_id' => $order->id,
                'source_type' => ProductionOrder::class,
                'location_id' => $locationId,
                // ✅ FIXED: Removed 'unit_cost', use total expected/actual cost columns
                'cost_amount_expected' => $quantityBase * $expectedUnitCost,
                'cost_amount_actual' => $quantityBase * $expectedUnitCost, // Actuals updated at finish
                'dimensions' => $order->dimension_set_id,
                'general_product_posting_group_id' => $order->general_product_posting_group_id,
                'inventory_posting_group_id' => $order->inventory_posting_group_id,
                'entry_date' => now(),
            ]);

            if ($routingLineId) {
                $routingLine = $order->routingLines()->find($routingLineId);
                if ($routingLine) {
                    $routingLine->actual_output_quantity = (float) $routingLine->actual_output_quantity + $quantityBase;
                    $routingLine->save();
                }
            }

            // Create Put-away for finished goods
            $orderLines = $order->lines()->where('item_id', $order->item_id)->get();
            foreach ($orderLines as $orderLine) {
                try {
                    $this->putAwayService->createPutAwayFromProductionOutput($orderLine, $quantityBase);
                } catch (\RuntimeException $exception) {
                    Log::warning('Put-away generation skipped during production output posting', [
                        'production_order_id' => $order->id,
                        'production_order_no' => $order->document_number,
                        'production_order_line_id' => $orderLine->id,
                        'reason' => $exception->getMessage(),
                    ]);
                }
            }
        });
    }

    /**
     * Post capacity
     *
     * @throws \Throwable
     */
    public function postCapacity(
        ProductionOrder $order,
        int $routingLineId,
        float $setupTime,
        float $runTime,
        ?float $cost,
        int $userId
    ): void {
        $cost = (float) ($cost ?? 0);
        $routingLine = $order->routingLines()->find($routingLineId);
        if (! $routingLine) {
            return;
        }

        DB::transaction(function () use ($order, $routingLineId, $routingLine, $setupTime, $runTime, $userId, &$cost) {
            $remainingSetupTime = max(0.0, (float) $routingLine->setup_time - (float) $routingLine->actual_setup_time);
            $remainingRunTime = max(0.0, (float) $routingLine->run_time - (float) $routingLine->actual_run_time);

            if ($setupTime > $remainingSetupTime + 0.0001 || $runTime > $remainingRunTime + 0.0001) {
                throw new \Exception('Cannot post more capacity than the remaining operation time');
            }

            $workCenter = $routingLine->workCenter;
            $machineCenter = $routingLine->machineCenter;
            $center = $this->resolveCapacityCostCenter($routingLine);
            $totalTime = $setupTime + $runTime;
            $costingTime = $totalTime;
            $autoDerivedCost = false;

            if ($cost <= 0 && $center) {
                // Derive cost from center rates
                $autoDerivedCost = true;
                $cost = $costingTime * ((float) ($center->direct_unit_cost ?? 0));
            }

            // Calculate Indirect Cost (Overhead)
            $indirectCost = 0;
            if ($center) {
                $indirectCost = ($cost * ((float) $center->indirect_cost_percent / 100)) + (((float) $center->overhead_rate) * $costingTime);
            }

            $totalCost = $cost + $indirectCost;

            // If values overflow DECIMAL(15,4), try a conservative auto-conversion for minute-based operations.
            if (
                $autoDerivedCost
                && $this->exceedsDecimal154($totalCost)
                && $this->isMinuteBasedTimeUnit((string) $routingLine->setup_time_unit, (string) $routingLine->run_time_unit)
            ) {
                $costingTime = $totalTime / 60;
                $cost = $costingTime * ((float) ($center?->direct_unit_cost ?? 0));
                $indirectCost = ($cost * ((float) ($center?->indirect_cost_percent ?? 0) / 100)) + (((float) ($center?->overhead_rate ?? 0)) * $costingTime);
                $totalCost = $cost + $indirectCost;
            }

            if ($this->exceedsDecimal154($cost) || $this->exceedsDecimal154($indirectCost) || $this->exceedsDecimal154($totalCost)) {
                throw new \Exception(
                    'Capacity cost is too large to post. '.
                    "Direct={$cost}, Overhead={$indirectCost}, Total={$totalCost}. ".
                    'Review run/setup time and center rates.'
                );
            }

            $this->assertCapacityCostIsReasonable(
                order: $order,
                totalCost: $totalCost,
                directCost: $cost,
                indirectCost: $indirectCost,
                centerCode: (string) ($center?->code ?? ''),
                timeUnit: (string) ($routingLine->run_time_unit ?? $routingLine->setup_time_unit ?? '')
            );

            $capacityLedgerEntry = CapacityLedgerEntry::create([
                'production_order_id' => $order->id,
                'routing_line_id' => $routingLineId,
                'work_center_id' => $routingLine->work_center_id,
                'machine_center_id' => $routingLine->machine_center_id,
                'fixed_asset_id' => $center?->fixed_asset_id,
                'capex_project_id' => $order->capex_project_id,
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

            app(ValueEntryService::class)->ensureForCapacityLedgerEntry($capacityLedgerEntry, $userId);

            // Keep operation progress/status in sync regardless of caller (UI, API, jobs).
            $routingLine->actual_setup_time = (float) $routingLine->actual_setup_time + (float) $setupTime;
            $routingLine->actual_run_time = (float) $routingLine->actual_run_time + (float) $runTime;
            $routingLine->status = $routingLine->actual_run_time >= (float) $routingLine->run_time
                ? 'COMPLETED'
                : 'IN_PROGRESS';
            $routingLine->save();

            // Update CapEx Project if linked
            if ($order->capex_project_id && $order->capexProject) {
                $order->capexProject->increment('actual_amount', $totalCost);
            }

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

    private function exceedsDecimal154(float $value): bool
    {
        return abs($value) > self::MAX_DECIMAL_15_4;
    }

    private function isMinuteBasedTimeUnit(string $setupUnit, string $runUnit): bool
    {
        $minuteAliases = ['MIN', 'MINS', 'MINUTE', 'MINUTES'];

        return in_array(strtoupper($setupUnit), $minuteAliases, true)
            || in_array(strtoupper($runUnit), $minuteAliases, true);
    }

    private function assertCapacityCostIsReasonable(
        ProductionOrder $order,
        float $totalCost,
        float $directCost,
        float $indirectCost,
        string $centerCode,
        string $timeUnit
    ): void {
        if ($totalCost <= 0) {
            return;
        }

        $plannedUnitCost = (float) ($order->cost_rollup ?: $order->unit_cost ?: 0);
        // ✅ FIXED: Use quantity_base instead of quantity
        $plannedOrderValue = abs((float) $order->quantity_base * $plannedUnitCost);

        if ($plannedOrderValue <= 0) {
            return;
        }

        $capacityGuardRatio = (float) config('manufacturing.capacity_guard_ratio', self::MAX_CAPACITY_COST_TO_ORDER_VALUE_RATIO);
        $capacityGuardRatio = $capacityGuardRatio > 0 ? $capacityGuardRatio : self::MAX_CAPACITY_COST_TO_ORDER_VALUE_RATIO;
        $maxAllowedCapacityCost = $plannedOrderValue * $capacityGuardRatio;

        if ($totalCost > $maxAllowedCapacityCost) {
            $centerLabel = $centerCode !== '' ? $centerCode : 'N/A';
            $unitLabel = $timeUnit !== '' ? strtoupper($timeUnit) : 'N/A';

            throw new \Exception(
                'Capacity cost appears unrealistic for this production order. '.
                "Center={$centerLabel}, TimeUnit={$unitLabel}, ".
                "Direct={$directCost}, Overhead={$indirectCost}, Total={$totalCost}, ".
                "PlannedOrderValue={$plannedOrderValue}, ".
                "Threshold={$capacityGuardRatio}x. ".
                'Review machine/work center rates and time units before posting.'
            );
        }
    }

    /**
     * Finish production order
     *
     * @throws \Throwable
     */
    public function finish(ProductionOrder $order, int $userId, ?\DateTime $postingDate = null): ProductionOrder
    {
        Gate::forUser(User::query()->findOrFail($userId))->authorize('finish', $order);

        $postingDate = $postingDate ?? now();
        $order = $order->fresh();

        if ($order->status === ProductionOrderStatus::FINISHED || $order->posted) {
            throw new \Exception('Production order is already finished');
        }

        DB::transaction(function () use ($order, $userId, $postingDate) {
            if ($order->flushing_method === 'BACKWARD') {
                $this->backwardFlushComponents($order, $postingDate, $userId);
            }

            $remainingOutputQuantityBase = (float) $order->fresh()->remaining_quantity;
            if ($remainingOutputQuantityBase > 0) {
                $this->postOutput($order->fresh(), $remainingOutputQuantityBase, $userId, $postingDate);
                $order = $order->fresh();
            }

            // Auto-post remaining planned capacity so operations can be completed at finish.
            $this->autoPostRemainingCapacity($order, $userId);
            $order = $order->fresh();

            $this->validateBeforeFinish($order);

            // ✅ FIXED: Cast to float to prevent math errors
            $totalActualCost = (float) ($order->total_actual_cost ?? 0);
            $totalOutput = (float) $order->itemLedgerEntries()
                ->where('entry_type', ItemLedgerEntryType::OUTPUT)
                ->sum('quantity');

            // Determine the cost to record in Inventory
            $inventoryUnitCost = ($order->costing_method === 'STANDARD')
                ? (float) $order->unit_cost
                : ($totalOutput > 0 ? $totalActualCost / $totalOutput : 0);

            // Update Output entries with the determined cost
            $outputEntries = $order->itemLedgerEntries()
                ->where('entry_type', ItemLedgerEntryType::OUTPUT)
                ->get();

            $totalInventoryCost = 0;
            foreach ($outputEntries as $entry) {
                // ✅ FIXED: Explicitly cast to float
                $actualCostForEntry = (float) $entry->quantity * (float) $inventoryUnitCost;

                $entry->update([
                    // ✅ FIXED: Removed 'unit_cost' (column doesn't exist on Item Ledger)
                    'cost_amount_actual' => $actualCostForEntry,
                ]);

                app(ValueEntryService::class)->ensureForItemLedgerEntry($entry->fresh());

                $totalInventoryCost += $actualCostForEntry;
            }

            $order->lines()
                ->where('item_id', $order->item_id)
                ->update([
                    'unit_cost' => $inventoryUnitCost,
                    'cost_amount' => (float) $order->quantity * (float) $inventoryUnitCost,
                ]);

            // G/L Integration:
            // 1. Move from WIP to Inventory (at the cost we recorded in Inventory)
            $this->createFinishGlEntries($order, $totalInventoryCost, $postingDate);

            // 2. Clear remaining WIP by posting to Variance (if any)
            $variance = $totalActualCost - $totalInventoryCost;
            if (abs($variance) > 0.01) {
                $this->createVarianceGlEntries($order, $variance, $postingDate);

                // Update CapEx Project for variance if linked
                if ($order->capex_project_id && $order->capexProject) {
                    $order->capexProject->increment('actual_amount', $variance);
                }
            }

            $this->changeStatus($order, ProductionOrderStatus::FINISHED, $userId);

            $order->forceFill([
                'posted' => true,
                'posted_at' => now(),
                'posted_by' => $userId,
            ])->save();
        });

        return $order->fresh();
    }

    protected function autoPostRemainingCapacity(ProductionOrder $order, int $userId): void
    {
        foreach ($order->routingLines as $routingLine) {
            if ($routingLine->status === 'COMPLETED') {
                continue;
            }

            $remainingSetup = max(0, (float) $routingLine->setup_time - (float) $routingLine->actual_setup_time);
            $remainingRun = max(0, (float) $routingLine->run_time - (float) $routingLine->actual_run_time);

            if ($remainingSetup <= 0 && $remainingRun <= 0) {
                $routingLine->status = 'COMPLETED';
                $routingLine->save();

                continue;
            }

            $this->postCapacity(
                order: $order,
                routingLineId: (int) $routingLine->id,
                setupTime: $remainingSetup,
                runTime: $remainingRun,
                cost: 0.0,
                userId: $userId
            );
        }
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

        $lineUnitCost = $this->resolveOrderLineUnitCost($order);

        $lineData = [
            'item_id' => $order->item_id,
            'description' => $order->description,
            'quantity' => $order->quantity,
            'unit_of_measure_code' => $order->unit_of_measure_code ?? $order->item->base_unit_of_measure,
            'quantity_base' => $order->quantity_base,
            'unit_cost' => $lineUnitCost,
            'cost_amount' => (float) $order->quantity * $lineUnitCost,
            'due_date' => $order->due_date,
            'production_bom_id' => $order->production_bom_id,
            'routing_id' => $order->routing_id,
        ];

        $existingLine = $order->lines()->where('line_number', 10000)->first();

        if ($existingLine) {
            $existingLine->fill($lineData);
            $existingLine->save();

            return;
        }

        $order->lines()->create([
            'line_number' => 10000,
            ...$lineData,
        ]);
    }

    private function resolveOrderLineUnitCost(ProductionOrder $order): float
    {
        if ($order->cost_rollup !== null) {
            return (float) $order->cost_rollup;
        }

        if ($order->unit_cost !== null) {
            return (float) $order->unit_cost;
        }

        return (float) ($order->item?->unit_cost ?? 0);
    }

    protected function refreshComponents(ProductionOrder $order): void
    {
        if (! $order->production_bom_id) {
            return;
        }

        $hasPostedConsumption = $order->itemLedgerEntries()
            ->where('entry_type', ItemLedgerEntryType::CONSUMPTION)
            ->exists();

        if ($hasPostedConsumption) {
            throw new \RuntimeException(
                'Cannot refresh components after consumption has been posted. '.
                'Create a new production order or reverse consumption first.'
            );
        }

        // Check for active version if not set
        if (! $order->production_bom_version_id && $order->productionBom) {
            $version = $order->productionBom->getActiveVersion($order->starting_date_time ?? now());
            if ($version) {
                $order->production_bom_version_id = $version->id;
                $order->save();
            }
        }

        $bom = $order->productionBom;
        $version = $order->production_bom_version_id ? ProductionBomVersion::find($order->production_bom_version_id) : null;

        if (! $bom) {
            return;
        }

        // Rebuild component snapshot from the selected BOM/version to avoid stale lines.
        $order->components()->delete();

        $rootLines = $this->resolveBomLines($bom, $order->starting_date_time ?? now(), $version);

        $explodedLines = [];
        $visitedBomPath = [];

        $this->explodeBomLines(
            order: $order,
            lines: $rootLines,
            parentOrderQuantity: (float) $order->quantity,
            accumulatedQuantityPer: 1.0,
            level: 1,
            path: [$bom->code],
            visitedBomPath: $visitedBomPath,
            target: $explodedLines
        );

        $lineNo = 10000;
        foreach ($explodedLines as $explodedLine) {
            $order->components()->updateOrCreate([
                'line_number' => $lineNo,
            ], [
                'line_number' => $lineNo,
                ...$explodedLine,
            ]);

            $lineNo += 10000;
        }
    }

    /**
     * @param  iterable<int, ProductionBomLine|ProductionBomVersionLine>  $lines
     * @param  array<int, bool>  $visitedBomPath
     * @param  array<int, array<string, mixed>>  $target
     * @param  array<int, string>  $path
     */
    protected function explodeBomLines(
        ProductionOrder $order,
        iterable $lines,
        float $parentOrderQuantity,
        float $accumulatedQuantityPer,
        int $level,
        array $path,
        array &$visitedBomPath,
        array &$target
    ): void {
        if ($level > self::MAX_BOM_EXPLOSION_DEPTH) {
            throw new \RuntimeException(
                'BOM explosion depth exceeded maximum allowed levels ('.self::MAX_BOM_EXPLOSION_DEPTH.').'
            );
        }

        foreach ($lines as $bomLine) {
            $lineQuantityPer = $this->resolveNormalizedBomLineQuantityPer($bomLine);
            $lineScrapPercent = (float) $bomLine->scrap_percent;
            $effectiveQuantityPer = $accumulatedQuantityPer * $lineQuantityPer;
            $expectedQty = $parentOrderQuantity * $lineQuantityPer * (1 + $lineScrapPercent / 100);

            if ($bomLine->type === ProductionBomLine::TYPE_ITEM) {
                if (! $bomLine->item_id || ! $bomLine->item) {
                    throw new \RuntimeException(
                        "Invalid BOM line {$bomLine->line_number}: ITEM type must reference a valid item."
                    );
                }

                $expectedQtyBase = $this->convertBomQuantityToItemBase((float) $expectedQty, $bomLine);

                $target[] = [
                    'item_id' => $bomLine->item_id,
                    'description' => $bomLine->description,
                    'unit_of_measure_code' => $bomLine->unit_of_measure_code,
                    'quantity_per' => $effectiveQuantityPer,
                    'expected_quantity' => $expectedQty,
                    'expected_quantity_base' => $expectedQtyBase,
                    // ✅ FIXED: Track remaining in Base Quantity
                    'remaining_quantity' => $expectedQtyBase,
                    'scrap_percent' => $lineScrapPercent,
                    'routing_link_code' => $bomLine->routing_link_code,
                    'flushing_method' => $bomLine->flushing_method ?? $order->flushing_method,
                    'location_code' => $bomLine->location_code ?? $order->location_code,
                    'bin_code' => $bomLine->bin_code,
                    'due_date' => $order->starting_date_time?->copy()->subDays($bomLine->lead_time_offset_days ?? 0),
                    'bom_level' => $level,
                    'bom_path' => implode(' > ', $path),
                    'source_bom_code' => end($path) ?: null,
                ];

                continue;
            }

            if ($bomLine->type !== ProductionBomLine::TYPE_PRODUCTION_BOM) {
                continue;
            }

            if (! $bomLine->production_bom_id_related) {
                throw new \RuntimeException(
                    "Invalid BOM line {$bomLine->line_number}: PRODUCTION_BOM type must reference a sub BOM."
                );
            }

            $subBomId = (int) $bomLine->production_bom_id_related;
            if (isset($visitedBomPath[$subBomId])) {
                $pathLabel = implode(' > ', [...$path, (string) $bomLine->relatedBom?->code]);
                throw new \RuntimeException("Circular BOM detected in path: {$pathLabel}");
            }

            $relatedBom = $bomLine->relatedBom;
            if (! $relatedBom) {
                throw new \RuntimeException(
                    "Invalid BOM line {$bomLine->line_number}: referenced sub BOM does not exist."
                );
            }

            $visitedBomPath[$subBomId] = true;
            $subLines = $this->resolveBomLines($relatedBom, $order->starting_date_time ?? now());

            $subOrderQuantity = $parentOrderQuantity * $lineQuantityPer * (1 + $lineScrapPercent / 100);
            $subPath = [...$path, $relatedBom->code];

            $this->explodeBomLines(
                order: $order,
                lines: $subLines,
                parentOrderQuantity: $subOrderQuantity,
                accumulatedQuantityPer: $effectiveQuantityPer,
                level: $level + 1,
                path: $subPath,
                visitedBomPath: $visitedBomPath,
                target: $target
            );

            unset($visitedBomPath[$subBomId]);
        }
    }

    private function resolveCapacityCostCenter(ProductionOrderRoutingLine $routingLine): mixed
    {
        $priority = (string) config('manufacturing.capacity_cost_center_priority', 'machine_center_first');
        $priority = strtolower(trim($priority));

        if ($priority === 'work_center_first') {
            return $routingLine->workCenter ?? $routingLine->machineCenter;
        }

        return $routingLine->machineCenter ?? $routingLine->workCenter;
    }

    private function resolveNormalizedBomLineQuantityPer(ProductionBomLine|ProductionBomVersionLine $bomLine): float
    {
        $lineQuantityPer = (float) $bomLine->quantity_per;
        $basisQuantity = 1.0;

        if ($bomLine instanceof ProductionBomVersionLine) {
            $basisQuantity = max(1.0, (float) ($bomLine->version?->quantity_per ?? 1.0));
        }

        return $lineQuantityPer / $basisQuantity;
    }

    private function resolveBomLines(
        ProductionBom $bom,
        \DateTimeInterface $effectiveDate,
        ?ProductionBomVersion $forcedVersion = null
    ) {
        $version = $forcedVersion ?? $bom->getActiveVersion(\DateTime::createFromInterface($effectiveDate));

        return $version
            ? $version->lines()->with(['item', 'relatedBom'])->orderBy('line_number')->get()
            : $bom->lines()->with(['item', 'relatedBom'])->orderBy('line_number')->get();
    }

    protected function refreshRouting(ProductionOrder $order): void
    {
        if (! $order->routing_id) {
            return;
        }

        if ($order->capacityLedgerEntries()->count() === 0) {
            $order->routingLines()->delete();
        }

        // Check for active version if not set
        if (! $order->routing_version_id && $order->routing) {
            $version = $order->routing->getActiveVersion($order->starting_date_time ?? now());
            if ($version) {
                $order->routing_version_id = $version->id;
                $order->save();
            }
        }

        $routing = $order->routing;
        $version = $order->routing_version_id ? RoutingVersion::find($order->routing_version_id) : null;

        if (! $routing) {
            return;
        }

        $lines = $version ? $version->lines : $routing->lines;

        $lineNo = 10000;
        foreach ($lines as $routingLine) {
            $lotSize = max((float) ($routingLine->lot_size ?? 1), 1.0);
            $concurrentCapacities = max((int) ($routingLine->concurrent_capacities ?? 1), 1);
            $expectedRunTime = ((float) $routingLine->run_time * ((float) $order->quantity / $lotSize)) / $concurrentCapacities;

            $order->routingLines()->updateOrCreate([
                'line_number' => $lineNo,
            ], [
                'line_number' => $lineNo,
                'operation_no' => $routingLine->operation_no,
                'description' => $routingLine->description,
                'work_center_id' => $routingLine->work_center_id,
                'machine_center_id' => $routingLine->machine_center_id,
                'setup_time' => $routingLine->setup_time,
                'run_time' => $expectedRunTime,
                'wait_time' => $routingLine->wait_time,
                'move_time' => $routingLine->move_time,
                'setup_time_unit' => $routingLine->setup_time_unit,
                'run_time_unit' => $routingLine->run_time_unit,
                'routing_link_code' => $routingLine->routing_link_code,
            ]);

            $lineNo += 10000;
        }
    }

    protected function scheduleOrder(ProductionOrder $order, bool $forward = true): void
    {
        if ($forward) {
            $currentDateTime = $order->starting_date_time ?? now();
            foreach ($order->routingLines()->orderBy('line_number')->get() as $routingLine) {
                $workCenter = $routingLine->workCenter;
                if ($workCenter) {
                    $availableStart = $workCenter->getNextWorkingDateTime($currentDateTime, true);
                    if ($availableStart) {
                        $routingLine->starting_date_time = $availableStart;
                        $routingLine->ending_date_time = Carbon::instance($availableStart)->addMinutes($routingLine->total_time_minutes);
                        $routingLine->save();
                        $currentDateTime = $routingLine->ending_date_time->copy()->addMinutes($routingLine->move_time);
                    }
                }
            }
            $order->ending_date_time = $currentDateTime;
        } else {
            $currentDateTime = $order->due_date?->copy()->subDay() ?? now();
            foreach ($order->routingLines()->orderByDesc('line_number')->get() as $routingLine) {
                $workCenter = $routingLine->workCenter;
                if ($workCenter) {
                    $availableEnd = $workCenter->getNextWorkingDateTime($currentDateTime, false);
                    if ($availableEnd) {
                        $routingLine->ending_date_time = $availableEnd;
                        $routingLine->starting_date_time = Carbon::instance($availableEnd)->subMinutes($routingLine->total_time_minutes);
                        $routingLine->save();
                        $currentDateTime = $routingLine->starting_date_time->copy()->subMinutes($routingLine->wait_time);
                    }
                }
            }
            $order->starting_date_time = $currentDateTime;
        }

        $order->save();
    }

    protected function backwardFlushComponents(ProductionOrder $order, \DateTime $postingDate, int $userId): void
    {
        foreach ($order->components as $component) {
            if (! str_contains((string) $component->flushing_method, 'BACKWARD')) {
                continue;
            }

            // ✅ FIXED: Compare base quantities
            $remainingQtyBase = (float) $component->expected_quantity_base - (float) $component->actual_quantity_consumed;
            if ($remainingQtyBase <= 0) {
                continue;
            }

            // Pass the base quantity to postConsumption (conversion factor will evaluate to 1.0)
            $this->postConsumption($order, [[
                'component_id' => $component->id,
                'quantity' => $remainingQtyBase,
                'scrap_quantity' => 0,
            ]], $userId, $postingDate);
        }
    }

    protected function forwardFlush(ProductionOrder $order, int $userId): void
    {
        foreach ($order->components as $component) {
            if (! str_contains((string) $component->flushing_method, 'FORWARD')) {
                continue;
            }

            // ✅ FIXED: Flush the full expected base quantity
            $remainingQtyBase = (float) $component->expected_quantity_base - (float) $component->actual_quantity_consumed;
            if ($remainingQtyBase <= 0) {
                continue;
            }

            $this->postConsumption($order, [[
                'component_id' => $component->id,
                'quantity' => $remainingQtyBase,
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

        $remainingByItemAndLocation = [];

        foreach ($order->components as $component) {
            $key = $component->item_id.'|'.$component->location_code;

            if (! array_key_exists($key, $remainingByItemAndLocation)) {
                $remainingByItemAndLocation[$key] = $this->getAvailableInventory($component->item_id, $component->location_code);
            }

            $requiredQuantityBase = (float) ($component->expected_quantity_base ?: $component->expected_quantity);

            if ($remainingByItemAndLocation[$key] < $requiredQuantityBase) {
                $itemDescription = $component->item?->description ?? "item #{$component->item_id}";
                $requiredQuantity = $requiredQuantityBase;
                $availableQuantity = (float) $remainingByItemAndLocation[$key];
                $locationLabel = $component->location_code ? " at {$component->location_code}" : '';

                throw new \Exception(
                    "Insufficient inventory for {$itemDescription}{$locationLabel}. ".
                    'Required: '.number_format($requiredQuantity, 4).
                    ', Available: '.number_format($availableQuantity, 4)
                );
            }

            $remainingByItemAndLocation[$key] -= $requiredQuantityBase;
        }
    }

    protected function validateBeforeFinish(ProductionOrder $order): void
    {
        if ($order->status !== ProductionOrderStatus::RELEASED) {
            throw new \Exception('Only RELEASED orders can be finished');
        }

        $this->validateManualFlushingConsumption($order);

        // Normalize stale routing statuses based on posted/actual time before validation.
        $order->routingLines()
            ->whereRaw('actual_run_time >= run_time')
            ->where('status', '!=', 'COMPLETED')
            ->update(['status' => 'COMPLETED']);

        $order->routingLines()
            ->whereRaw('(coalesce(actual_setup_time, 0) + coalesce(actual_run_time, 0)) > 0')
            ->whereRaw('actual_run_time < run_time')
            ->where('status', '=', 'PLANNED')
            ->update(['status' => 'IN_PROGRESS']);

        $incompleteOps = $order->routingLines()->where('status', '!=', 'COMPLETED')->count();
        if ($incompleteOps > 0) {
            throw new \Exception("{$incompleteOps} operations incomplete");
        }

        if ((float) $order->remaining_quantity > 0) {
            throw new \Exception('Production not fully completed');
        }
    }

    protected function validateManualFlushingConsumption(ProductionOrder $order): void
    {
        $flushingMethod = strtoupper((string) $order->flushing_method);
        if (! str_contains($flushingMethod, 'MANUAL')) {
            return;
        }

        // ✅ FIXED: Check unconsumed BASE quantities
        $remainingConsumption = (float) $order->components()
            ->selectRaw('sum(coalesce(expected_quantity_base, 0) - coalesce(actual_quantity_consumed, 0)) as remaining')
            ->value('remaining');
        $remainingConsumption = max(0.0, $remainingConsumption);

        if ($remainingConsumption > 0.0001) {
            throw new \Exception(
                'Cannot finish MANUAL flush order with unconsumed components. '.
                'Post component consumption first. '.
                'Remaining component quantity: '.number_format($remainingConsumption, 4)
            );
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
        $location = Location::where('code', $order->location_code)->first();
        $inventoryAccount = $this->inventoryPostingResolver->resolveInventoryAccount($item, $location);
        $wipAccount = $this->inventoryPostingResolver->resolveWipAccount(
            (int) $order->inventory_posting_group_id,
            $location
        );

        $transactionNumber = (GlEntry::max('transaction_number') ?? 0) + 1;

        // WIP Entry (Debit)
        $this->createGlEntry([
            'transaction_number' => $transactionNumber,
            'chart_of_account_id' => $wipAccount->id,
            'debit_amount' => $amount,
            'credit_amount' => 0,
            'amount' => $amount,
            'posting_date' => $postingDate,
            'document_date' => $postingDate,
            'document_type' => DocumentType::PRODUCTION_ORDER,
            'document_number' => $order->document_number,
            'description' => $description,
            'sourceable_type' => ProductionOrder::class,
            'sourceable_id' => $order->id,
            'user_id' => auth()->id(),
            'dimensions' => $order->dimension_set_id,
            'shortcut_dimension_1_code' => $order->shortcut_dimension_1_code,
            'shortcut_dimension_2_code' => $order->shortcut_dimension_2_code,
        ]);

        // Inventory Entry (Credit)
        $this->createGlEntry([
            'transaction_number' => $transactionNumber,
            'chart_of_account_id' => $inventoryAccount->id,
            'debit_amount' => 0,
            'credit_amount' => $amount,
            'amount' => -$amount,
            'posting_date' => $postingDate,
            'document_date' => $postingDate,
            'document_type' => DocumentType::PRODUCTION_ORDER,
            'document_number' => $order->document_number,
            'description' => $description,
            'sourceable_type' => ProductionOrder::class,
            'sourceable_id' => $order->id,
            'user_id' => auth()->id(),
            'dimensions' => $order->dimension_set_id,
            'shortcut_dimension_1_code' => $order->shortcut_dimension_1_code,
            'shortcut_dimension_2_code' => $order->shortcut_dimension_2_code,
        ]);
    }

    protected function createCapacityGlEntries(ProductionOrder $order, float $directCost, float $indirectCost, \DateTime $postingDate, string $description): void
    {
        $location = Location::where('code', $order->location_code)->first();
        $wipAccount = $this->inventoryPostingResolver->resolveWipAccount(
            (int) $order->inventory_posting_group_id,
            $location
        );

        // 2. Direct Cost Applied (Credit)
        $genSetup = $order->getPostingSetup();
        if (! $genSetup) {
            throw new \Exception("General Posting Setup missing for item {$order->item->item_code}");
        }

        $appliedAccount = $genSetup->getDirectCostAppliedAccount();
        if (! $appliedAccount) {
            throw new \Exception('Direct Cost Applied account missing in General Posting Setup');
        }

        $overheadAccount = $genSetup->getOverheadAppliedAccount();

        $transactionNumber = (GlEntry::max('transaction_number') ?? 0) + 1;
        $totalCost = $directCost + $indirectCost;

        // WIP Entry (Debit)
        $this->createGlEntry([
            'transaction_number' => $transactionNumber,
            'chart_of_account_id' => $wipAccount->id,
            'debit_amount' => $totalCost,
            'credit_amount' => 0,
            'amount' => $totalCost,
            'posting_date' => $postingDate,
            'document_date' => $postingDate,
            'document_type' => DocumentType::PRODUCTION_ORDER,
            'document_number' => $order->document_number,
            'description' => $description,
            'sourceable_type' => ProductionOrder::class,
            'sourceable_id' => $order->id,
            'user_id' => auth()->id(),
            'dimensions' => $order->dimension_set_id,
            'shortcut_dimension_1_code' => $order->shortcut_dimension_1_code,
            'shortcut_dimension_2_code' => $order->shortcut_dimension_2_code,
        ]);

        // Direct Applied Entry (Credit)
        $this->createGlEntry([
            'transaction_number' => $transactionNumber,
            'chart_of_account_id' => $appliedAccount->id,
            'debit_amount' => 0,
            'credit_amount' => $directCost,
            'amount' => -$directCost,
            'posting_date' => $postingDate,
            'document_date' => $postingDate,
            'document_type' => DocumentType::PRODUCTION_ORDER,
            'document_number' => $order->document_number,
            'description' => $description.' (Direct)',
            'sourceable_type' => ProductionOrder::class,
            'sourceable_id' => $order->id,
            'user_id' => auth()->id(),
            'dimensions' => $order->dimension_set_id,
            'shortcut_dimension_1_code' => $order->shortcut_dimension_1_code,
            'shortcut_dimension_2_code' => $order->shortcut_dimension_2_code,
        ]);

        // Overhead Applied Entry (Credit)
        if ($indirectCost > 0 && $overheadAccount) {
            $this->createGlEntry([
                'transaction_number' => $transactionNumber,
                'chart_of_account_id' => $overheadAccount->id,
                'debit_amount' => 0,
                'credit_amount' => $indirectCost,
                'amount' => -$indirectCost,
                'posting_date' => $postingDate,
                'document_date' => $postingDate,
                'document_type' => DocumentType::PRODUCTION_ORDER,
                'document_number' => $order->document_number,
                'description' => $description.' (Overhead)',
                'sourceable_type' => ProductionOrder::class,
                'sourceable_id' => $order->id,
                'user_id' => auth()->id(),
                'dimensions' => $order->dimension_set_id,
                'shortcut_dimension_1_code' => $order->shortcut_dimension_1_code,
                'shortcut_dimension_2_code' => $order->shortcut_dimension_2_code,
            ]);
        }
    }

    protected function createFinishGlEntries(ProductionOrder $order, float $totalWip, \DateTime $postingDate): void
    {
        $location = Location::where('code', $order->location_code)->first();
        $inventoryAccount = $this->inventoryPostingResolver->resolveInventoryAccount($order->item, $location);
        $wipAccount = $this->inventoryPostingResolver->resolveWipAccount(
            (int) $order->inventory_posting_group_id,
            $location
        );

        $transactionNumber = (GlEntry::max('transaction_number') ?? 0) + 1;

        // Inventory Entry (Debit)
        $this->createGlEntry([
            'transaction_number' => $transactionNumber,
            'chart_of_account_id' => $inventoryAccount->id,
            'debit_amount' => $totalWip,
            'credit_amount' => 0,
            'amount' => $totalWip,
            'posting_date' => $postingDate,
            'document_date' => $postingDate,
            'document_type' => DocumentType::PRODUCTION_ORDER,
            'document_number' => $order->document_number,
            'description' => "Finish Production: {$order->document_number}",
            'sourceable_type' => ProductionOrder::class,
            'sourceable_id' => $order->id,
            'user_id' => auth()->id(),
            'dimensions' => $order->dimension_set_id,
            'shortcut_dimension_1_code' => $order->shortcut_dimension_1_code,
            'shortcut_dimension_2_code' => $order->shortcut_dimension_2_code,
        ]);

        // WIP Entry (Credit)
        $this->createGlEntry([
            'transaction_number' => $transactionNumber,
            'chart_of_account_id' => $wipAccount->id,
            'debit_amount' => 0,
            'credit_amount' => $totalWip,
            'amount' => -$totalWip,
            'posting_date' => $postingDate,
            'document_date' => $postingDate,
            'document_type' => DocumentType::PRODUCTION_ORDER,
            'document_number' => $order->document_number,
            'description' => "Finish Production: {$order->document_number}",
            'sourceable_type' => ProductionOrder::class,
            'sourceable_id' => $order->id,
            'user_id' => auth()->id(),
            'dimensions' => $order->dimension_set_id,
            'shortcut_dimension_1_code' => $order->shortcut_dimension_1_code,
            'shortcut_dimension_2_code' => $order->shortcut_dimension_2_code,
        ]);
    }

    protected function createVarianceGlEntries(ProductionOrder $order, float $variance, \DateTime $postingDate): void
    {
        if ($variance == 0) {
            return;
        }

        $location = Location::where('code', $order->location_code)->first();
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
        $this->createGlEntry([
            'transaction_number' => $transactionNumber,
            'chart_of_account_id' => $parentSetup->wip_account_id,
            'debit_amount' => $variance < 0 ? abs($variance) : 0,
            'credit_amount' => $variance > 0 ? $variance : 0,
            'amount' => -$variance,
            'posting_date' => $postingDate,
            'document_date' => $postingDate,
            'document_type' => DocumentType::PRODUCTION_ORDER,
            'document_number' => $order->document_number,
            'description' => "Production Variance: {$order->document_number}",
            'sourceable_type' => ProductionOrder::class,
            'sourceable_id' => $order->id,
            'user_id' => auth()->id(),
            'dimensions' => $order->dimension_set_id,
            'shortcut_dimension_1_code' => $order->shortcut_dimension_1_code,
            'shortcut_dimension_2_code' => $order->shortcut_dimension_2_code,
        ]);

        // Variance Entry
        $this->createGlEntry([
            'transaction_number' => $transactionNumber,
            'chart_of_account_id' => $varianceAccount->id,
            'debit_amount' => $variance > 0 ? $variance : 0,
            'credit_amount' => $variance < 0 ? abs($variance) : 0,
            'amount' => $variance,
            'posting_date' => $postingDate,
            'document_date' => $postingDate,
            'document_type' => DocumentType::PRODUCTION_ORDER,
            'document_number' => $order->document_number,
            'description' => "Production Variance: {$order->document_number}",
            'sourceable_type' => ProductionOrder::class,
            'sourceable_id' => $order->id,
            'user_id' => auth()->id(),
            'dimensions' => $order->dimension_set_id,
            'shortcut_dimension_1_code' => $order->shortcut_dimension_1_code,
            'shortcut_dimension_2_code' => $order->shortcut_dimension_2_code,
        ]);
    }

    /**
     * Inventory Helpers
     */
    protected function getAvailableInventory(int $itemId, ?string $locationCode): float
    {
        $query = ItemLedgerEntry::query()
            ->where('item_id', $itemId)
            ->where('open', true);

        if (filled($locationCode)) {
            $locationId = Location::query()
                ->where('code', $locationCode)
                ->value('id');

            if (! $locationId) {
                return 0;
            }

            $query->where('location_id', $locationId);
        }

        $ledgerBalance = (float) $query->sum('remaining_quantity');

        if ($ledgerBalance > 0) {
            return $ledgerBalance;
        }

        // Fallback for setups that maintain stock directly on the item card
        // (e.g. seeded/opening balances without open item ledger layers).
        $itemInventory = null;

        if (filled($locationCode)) {
            $locationId = Location::query()
                ->where('code', $locationCode)
                ->value('id');

            if ($locationId) {
                $itemInventory = Item::query()
                    ->whereKey($itemId)
                    ->where('location_id', $locationId)
                    ->value('inventory');
            }
        }

        // Item-card inventory acts as global stock fallback in this implementation.
        if ($itemInventory === null) {
            $itemInventory = Item::query()
                ->whereKey($itemId)
                ->value('inventory');
        }

        return (float) ($itemInventory ?? 0);
    }

    protected function createGlEntry(array $attributes): GlEntry
    {
        $attributes['entry_number'] ??= (int) (GlEntry::query()->max('entry_number') ?? 0) + 1;

        return GlEntry::create($attributes);
    }

    /**
     * Document Generators
     */
    public function generateDocumentNumber(): string
    {
        return app(NumberSeriesService::class)->getNextNoFromSeries(
            ['PROD-ORDER', 'PRODUCTION_ORDER', 'PROD'],
            null,
            'Production Order'
        );
    }

    protected function convertBomQuantityToItemBase(float $quantity, ProductionBomLine|ProductionBomVersionLine $bomLine): float
    {
        $item = $bomLine->item;
        $uomCode = (string) ($bomLine->unit_of_measure_code ?? '');

        if (! $item || $uomCode === '') {
            return $quantity;
        }

        $baseUomCode = (string) ($item->base_unit_of_measure ?? $item->baseUom?->uom_code ?? '');

        if ($baseUomCode !== '' && strtoupper($uomCode) === strtoupper($baseUomCode)) {
            return $quantity;
        }

        $assignment = $item->uoms()
            ->where('uom_code', $uomCode)
            ->first();

        $factor = (float) ($assignment?->pivot?->conversion_factor ?? 1.0);

        return $quantity * $factor;
    }
}
