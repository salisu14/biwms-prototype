<?php

namespace App\Services\Manufacturing;

use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionBom;
use App\Models\Manufacturing\Routing;
use App\Models\Item;
use App\Models\Manufacturing\WorkCenter;
use Illuminate\Support\Facades\DB;

class ProductionOrderService
{
    /**
     * Create production order from item
     */
    public function createFromItem(
        int $itemId,
        float $quantity,
        ?\DateTime $dueDate = null,
        ?int $userId = null
    ): ProductionOrder {
        $item = Item::findOrFail($itemId);

        return DB::transaction(function () use ($item, $quantity, $dueDate, $userId) {
            $order = ProductionOrder::create([
                'document_number' => ProductionOrder::generateDocumentNumber(),
                'status' => ProductionOrder::STATUS_FIRM_PLANNED,
                'source_type' => ProductionOrder::SOURCE_TYPE_ITEM,
                'source_id' => $item->id,
                'source_no' => $item->item_number,
                'description' => $item->description,
                'item_id' => $item->id,
                'quantity' => $quantity,
                'unit_of_measure_code' => $item->base_unit_of_measure,
                'quantity_base' => $quantity * ($item->qty_per_unit_of_measure ?? 1),
                'due_date' => $dueDate ?? now()->addDays(7),
                'inventory_posting_group_id' => $item->inventory_posting_group_id,
                'general_product_posting_group_id' => $item->general_product_posting_group_id,
                'production_bom_id' => $item->production_bom_id,
                'routing_id' => $item->routing_id,
                'location_code' => $item->location_code,
                'flushing_method' => $item->flushing_method ?? 'MANUAL',
                'costing_method' => $item->costing_method ?? 'STANDARD',
                'unit_cost' => $item->unit_cost,
                'created_by' => $userId ?? auth()->id(),
            ]);

            // Refresh to generate lines, components, and routing
            $order->refreshOrder();

            return $order;
        });
    }

    /**
     * Create production order from sales order (Make-to-Order)
     */
    public function createFromSalesOrder(
        int $salesOrderLineId,
        ?float $quantity = null,
        ?int $userId = null
    ): ProductionOrder {
        $salesLine = \App\Models\SalesOrderLine::findOrFail($salesOrderLineId);

        return DB::transaction(function () use ($salesLine, $quantity, $userId) {
            $item = $salesLine->item;
            $qty = $quantity ?? $salesLine->remaining_quantity;

            $order = ProductionOrder::create([
                'document_number' => ProductionOrder::generateDocumentNumber(),
                'status' => ProductionOrder::STATUS_RELEASED, // Auto-release for MTO
                'source_type' => ProductionOrder::SOURCE_TYPE_SALES_HEADER,
                'source_id' => $salesLine->sales_order_id,
                'source_no' => $salesLine->salesOrder->document_number,
                'description' => "MTO: {$salesLine->salesOrder->document_number}",
                'item_id' => $item->id,
                'quantity' => $qty,
                'unit_of_measure_code' => $salesLine->unit_of_measure_code,
                'due_date' => $salesLine->planned_delivery_date,
                'inventory_posting_group_id' => $item->inventory_posting_group_id,
                'general_product_posting_group_id' => $item->general_product_posting_group_id,
                'production_bom_id' => $item->production_bom_id,
                'routing_id' => $item->routing_id,
                'location_code' => $salesLine->location_code,
                'flushing_method' => $item->flushing_method ?? 'MANUAL',
                'created_by' => $userId ?? auth()->id(),
            ]);

            $order->refreshOrder();

            // Create reservation link
            $this->createReservation($order, $salesLine);

            return $order;
        });
    }

    /**
     * Create production order from family (BC Pattern)
     */
    public function createFromFamily(
        int $familyId,
        float $quantity,
        ?\DateTime $dueDate = null,
        ?int $userId = null
    ): ProductionOrder {
        $family = \App\Models\Manufacturing\Family::findOrFail($familyId);

        return DB::transaction(function () use ($family, $quantity, $dueDate, $userId) {
            $order = ProductionOrder::create([
                'document_number' => ProductionOrder::generateDocumentNumber(),
                'status' => ProductionOrder::STATUS_FIRM_PLANNED,
                'source_type' => ProductionOrder::SOURCE_TYPE_FAMILY,
                'source_id' => $family->id,
                'source_no' => $family->code,
                'description' => $family->description,
                'family_id' => $family->id,
                'quantity' => $quantity,
                'unit_of_measure_code' => $family->unit_of_measure_code,
                'due_date' => $dueDate ?? now()->addDays(7),
                'location_code' => $family->location_code,
                'flushing_method' => 'MANUAL',
                'created_by' => $userId ?? auth()->id(),
            ]);

            // Family orders generate multiple production order lines
            foreach ($family->lines as $familyLine) {
                $order->lines()->create([
                    'item_id' => $familyLine->item_id,
                    'quantity' => $familyLine->quantity * $quantity,
                    'unit_of_measure_code' => $familyLine->unit_of_measure_code,
                    'production_bom_id' => $familyLine->item->production_bom_id,
                    'routing_id' => $familyLine->item->routing_id,
                ]);
            }

            $order->refreshOrder();

            return $order;
        });
    }

    /**
     * Release production order to shop floor
     */
    public function release(int $orderId, int $userId): ProductionOrder
    {
        $order = ProductionOrder::findOrFail($orderId);

        // Validate inventory availability
        foreach ($order->components as $component) {
            $available = $this->getAvailableInventory(
                $component->item_id,
                $component->location_code
            );

            if ($available < $component->expected_quantity) {
                throw new \Exception(
                    "Insufficient inventory for {$component->item->description}. " .
                    "Required: {$component->expected_quantity}, Available: {$available}"
                );
            }
        }

        $order->changeStatus(ProductionOrder::STATUS_RELEASED, $userId);

        // Auto-forward flush if configured
        if ($order->flushing_method === 'FORWARD') {
            $this->forwardFlush($order);
        }

        return $order;
    }

    /**
     * Post consumption journal
     */
    public function postConsumption(
        int $orderId,
        array $lines,
        int $userId,
        ?\DateTime $postingDate = null
    ): void {
        $order = ProductionOrder::findOrFail($orderId);

        if ($order->status !== ProductionOrder::STATUS_RELEASED) {
            throw new \Exception('Production order must be released to post consumption');
        }

        $consumptions = [];
        foreach ($lines as $line) {
            $component = $order->components()->find($line['component_id']);
            if (!$component) continue;

            $consumptions[] = [
                'component_id' => $component->id,
                'quantity' => $line['quantity'],
                'scrap_quantity' => $line['scrap_quantity'] ?? 0,
            ];
        }

        $order->postConsumption($consumptions, $userId, $postingDate);
    }

    /**
     * Post output journal
     */
    public function postOutput(
        int $orderId,
        float $quantity,
        ?int $routingLineId,
        int $userId,
        ?\DateTime $postingDate = null
    ): void {
        $order = ProductionOrder::findOrFail($orderId);
        $order->postOutput($quantity, $userId, $postingDate, $routingLineId);
    }

    /**
     * Post capacity (labor/machine time)
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
        $order->postCapacity($routingLineId, $setupTime, $runTime, $cost, $userId);
    }

    /**
     * Finish production order
     */
    public function finish(int $orderId, int $userId): ProductionOrder
    {
        $order = ProductionOrder::findOrFail($orderId);

        // Validate all operations completed
        $incompleteOps = $order->routingLines()
            ->where('status', '!=', 'COMPLETED')
            ->count();

        if ($incompleteOps > 0) {
            throw new \Exception("Cannot finish: {$incompleteOps} operations incomplete");
        }

        $order->finish($userId);

        return $order;
    }

    /**
     * Calculate production order cost rollup
     */
    public function calculateCostRollup(int $orderId): float
    {
        $order = ProductionOrder::findOrFail($orderId);

        $materialCost = 0;
        foreach ($order->components as $component) {
            $materialCost += $component->expected_quantity * ($component->item?->unit_cost ?? 0);
        }

        $routingCost = 0;
        foreach ($order->routingLines as $line) {
            $setupCost = $line->setup_time * ($line->workCenter?->direct_unit_cost ?? 0);
            $runCost = $line->run_time * ($line->workCenter?->direct_unit_cost ?? 0);
            $overhead = ($setupCost + $runCost) * ($line->workCenter?->indirect_cost_percent ?? 0) / 100;
            $routingCost += $setupCost + $runCost + $overhead;
        }

        $totalCost = $materialCost + $routingCost;
        $unitCost = $order->quantity > 0 ? $totalCost / $order->quantity : 0;

        $order->update([
            'cost_rollup' => $totalCost,
            'unit_cost' => $unitCost,
        ]);

        return $totalCost;
    }

    /**
     * Replan production order (for multi-level)
     */
    public function replan(int $orderId, bool $createSubOrders = false): void
    {
        $order = ProductionOrder::findOrFail($orderId);

        if ($createSubOrders) {
            // Check for sub-assemblies
            foreach ($order->components as $component) {
                $item = $component->item;
                if ($item->replenishment_system === 'PRODUCTION') {
                    // Create sub-level production order
                    $this->createSubProductionOrder($order, $component);
                }
            }
        }

        $order->refreshOrder();
    }

    /**
     * Schedule production order (Backward/Forward per BC pattern)
     */
    public function schedule(
        int $orderId,
        string $direction = 'BACKWARD',
        ?\DateTime $referenceDate = null
    ): void {
        $order = ProductionOrder::findOrFail($orderId);
        $refDate = $referenceDate ?? $order->due_date;

        if ($direction === 'BACKWARD') {
            // BC Pattern: Start from due date, work backwards
            $this->scheduleBackward($order, $refDate);
        } else {
            // BC Pattern: Start from earliest start date
            $this->scheduleForward($order, $refDate);
        }
    }

    /**
     * Change status (Simulated → Planned → Firm Planned → Released → Finished)
     */
    public function changeStatus(
        int $orderId,
        string $newStatus,
        int $userId
    ): ProductionOrder {
        $order = ProductionOrder::findOrFail($orderId);

        $validTransitions = [
            ProductionOrder::STATUS_SIMULATED => [
                ProductionOrder::STATUS_PLANNED,
                ProductionOrder::STATUS_FIRM_PLANNED
            ],
            ProductionOrder::STATUS_PLANNED => [
                ProductionOrder::STATUS_FIRM_PLANNED,
                ProductionOrder::STATUS_SIMULATED
            ],
            ProductionOrder::STATUS_FIRM_PLANNED => [
                ProductionOrder::STATUS_RELEASED,
                ProductionOrder::STATUS_PLANNED
            ],
            ProductionOrder::STATUS_RELEASED => [
                ProductionOrder::STATUS_FINISHED,
                ProductionOrder::STATUS_FIRM_PLANNED
            ],
        ];

        if (!in_array($newStatus, $validTransitions[$order->status] ?? [])) {
            throw new \Exception("Invalid status transition from {$order->status} to {$newStatus}");
        }

        $order->changeStatus($newStatus, $userId);

        return $order;
    }

    // Protected helper methods...

    protected function forwardFlush(ProductionOrder $order): void
    {
        foreach ($order->components as $component) {
            if ($component->flushing_method === 'FORWARD') {
                $order->postConsumption([[
                    'component_id' => $component->id,
                    'quantity' => $component->expected_quantity,
                    'scrap_quantity' => 0,
                ]], $order->created_by, now());
            }
        }
    }

    protected function getAvailableInventory(int $itemId, ?string $locationCode): float
    {
        // Query inventory availability logic
        return \App\Models\ItemLedgerEntry::where('item_id', $itemId)
            ->where('location_code', $locationCode)
            ->where('open', true)
            ->sum('remaining_quantity');
    }

    protected function createReservation(ProductionOrder $order, $salesLine): void
    {
        // Create reservation entry linking production to sales
        \App\Models\ReservationEntry::create([
            'source_type' => 'SALES_LINE',
            'source_id' => $salesLine->id,
            'source_ref_no' => $salesLine->line_no,
            'item_id' => $salesLine->item_id,
            'quantity' => $order->quantity,
            'reservation_status' => 'RESERVED',
            'production_order_id' => $order->id,
        ]);
    }

    protected function createSubProductionOrder(ProductionOrder $parentOrder, $component): ProductionOrder
    {
        return $this->createFromItem(
            $component->item_id,
            $component->expected_quantity,
            $parentOrder->due_date,
            $parentOrder->created_by
        );
    }

    protected function scheduleBackward(ProductionOrder $order, \DateTime $dueDate): void
    {
        // BC Pattern: Due date - 1 day buffer = Ending Date
        $endingDate = (clone $dueDate)->modify('-1 day');

        foreach ($order->routingLines()->orderBy('operation_no', 'desc')->get() as $line) {
            $runTime = $line->run_time * ($order->quantity / $line->lot_size);
            $totalTime = $line->setup_time + $runTime + $line->wait_time + $line->move_time;

            $line->update([
                'ending_date' => $endingDate,
                'starting_date' => (clone $endingDate)->modify("-{$totalTime} minutes"),
            ]);

            $endingDate = $line->starting_date;
        }

        $order->update([
            'ending_date' => (clone $dueDate)->modify('-1 day'),
            'starting_date' => $endingDate,
        ]);
    }

    protected function scheduleForward(ProductionOrder $order, \DateTime $startDate): void
    {
        $currentDate = $startDate;

        foreach ($order->routingLines()->orderBy('operation_no', 'asc')->get() as $line) {
            $runTime = $line->run_time * ($order->quantity / $line->lot_size);
            $totalTime = $line->setup_time + $runTime + $line->wait_time + $line->move_time;

            $line->update([
                'starting_date' => $currentDate,
                'ending_date' => (clone $currentDate)->modify("+{$totalTime} minutes"),
            ]);

            $currentDate = $line->ending_date;
        }

        $order->update([
            'starting_date' => $startDate,
            'ending_date' => $currentDate,
        ]);
    }

    /**
     * Calculate comprehensive cost rollup with CapEx/OpEx separation
     */
    public function calculateCostRollup(int $orderId): array
    {
        $order = ProductionOrder::with([
            'components.item',
            'routingLines.workCenter.fixedAssets',
            'routingLines.machineCenter.fixedAssets',
            'routingLines.capacityEntries',
            'tooling'
        ])->findOrFail($orderId);

        $costBreakdown = [
            'opex' => [
                'direct_materials' => 0,
                'direct_labor' => 0,
                'variable_overhead' => 0,
                'subcontracting' => 0,
                'total_opex' => 0,
            ],
            'capex' => [
                'depreciation_machinery' => 0,
                'depreciation_building' => 0,
                'depreciation_tooling' => 0,
                'allocated_interest' => 0, // Capitalized interest during construction
                'total_capex' => 0,
            ],
            'total_production_cost' => 0,
            'unit_cost' => 0,
        ];

        // 1. OpEx: Direct Materials (from production_order_components)
        foreach ($order->components as $component) {
            $materialCost = $component->expected_quantity * ($component->item?->unit_cost ?? 0);
            $costBreakdown['opex']['direct_materials'] += $materialCost;
        }

        // 2. OpEx & CapEx: Routing/Capacity Analysis
        foreach ($order->routingLines as $routingLine) {
            $routingCosts = $this->analyzeRoutingLineCosts($routingLine, $order->quantity);

            // OpEx portions
            $costBreakdown['opex']['direct_labor'] += $routingCosts['opex']['labor'];
            $costBreakdown['opex']['variable_overhead'] += $routingCosts['opex']['variable_overhead'];
            $costBreakdown['opex']['subcontracting'] += $routingCosts['opex']['subcontracting'];

            // CapEx portions (depreciation allocation)
            $costBreakdown['capex']['depreciation_machinery'] += $routingCosts['capex']['machinery_depreciation'];
            $costBreakdown['capex']['depreciation_building'] += $routingCosts['capex']['building_allocation'];
        }

        // 3. CapEx: Tooling amortization (if using unit-of-production method)
        foreach ($order->tooling as $tool) {
            if ($tool->depreciation_method === 'UNITS_OF_PRODUCTION') {
                $toolingDepreciation = $this->calculateToolingDepreciation($tool, $order->quantity);
                $costBreakdown['capex']['depreciation_tooling'] += $toolingDepreciation;
            }
        }

        // 4. Summarize
        $costBreakdown['opex']['total_opex'] = array_sum($costBreakdown['opex']);
        $costBreakdown['capex']['total_capex'] = array_sum($costBreakdown['capex']);
        $costBreakdown['total_production_cost'] = $costBreakdown['opex']['total_opex']
            + $costBreakdown['capex']['total_capex'];

        $costBreakdown['unit_cost'] = $order->quantity > 0
            ? $costBreakdown['total_production_cost'] / $order->quantity
            : 0;

        // 5. Persist to production order
        $order->update([
            'cost_rollup' => $costBreakdown['total_production_cost'],
            'unit_cost' => $costBreakdown['unit_cost'],
            'cost_breakdown' => json_encode($costBreakdown), // Store detailed breakdown
        ]);

        return $costBreakdown;
    }

    /**
     * Analyze costs for a single routing line, separating OpEx and CapEx
     */
    protected function analyzeRoutingLineCosts($routingLine, float $orderQuantity): array
    {
        $workCenter = $routingLine->workCenter;
        $machineCenter = $routingLine->machineCenter;

        // Time calculations
        $setupTime = $routingLine->setup_time;
        $runTime = $routingLine->run_time * ($orderQuantity / ($routingLine->lot_size ?: 1));
        $totalTime = $setupTime + $runTime;

        $result = [
            'opex' => ['labor' => 0, 'variable_overhead' => 0, 'subcontracting' => 0],
            'capex' => ['machinery_depreciation' => 0, 'building_allocation' => 0],
        ];

        // --- OpEx: Direct Labor (from work center rates) ---
        $directLaborRate = $workCenter?->direct_unit_cost ?? 0;
        $result['opex']['labor'] = $totalTime * $directLaborRate;

        // --- OpEx: Variable Overhead ---
        $indirectPercent = $workCenter?->indirect_cost_percent ?? 0;
        $result['opex']['variable_overhead'] = ($result['opex']['labor'] * $indirectPercent / 100)
            + ($totalTime * ($workCenter?->overhead_rate ?? 0));

        // --- OpEx: Subcontracting ---
        if ($routingLine->subcontractor_id) {
            $result['opex']['subcontracting'] = $routingLine->subcontracting_cost * $orderQuantity;
        }

        // --- CapEx: Machinery Depreciation Allocation ---
        $equipment = $machineCenter ?? $workCenter;
        if ($equipment && $equipment->fixedAssets()->exists()) {
            foreach ($equipment->fixedAssets as $asset) {
                if ($asset->is_active && $asset->acquisition_cost > 0) {
                    $depreciationPerMinute = $this->calculateAssetDepreciationRate($asset);
                    $result['capex']['machinery_depreciation'] += $totalTime * $depreciationPerMinute;
                }
            }
        }

        // --- CapEx: Building/Facility Allocation ---
        if ($workCenter && $workCenter->buildingAsset) {
            $buildingAllocation = $this->allocateBuildingCost($workCenter, $totalTime);
            $result['capex']['building_allocation'] += $buildingAllocation;
        }

        return $result;
    }

    /**
     * Calculate depreciation rate per minute for a fixed asset
     */
    protected function calculateAssetDepreciationRate(FixedAsset $asset): float
    {
        // Annual depreciation / Annual capacity minutes
        $annualDepreciation = $asset->annual_depreciation_amount;
        $annualCapacityMinutes = $asset->annual_capacity_minutes
            ?? (8 * 60 * 250 * ($asset->efficiency_percent / 100)); // Default: 8h/day, 250 days, efficiency

        return $annualCapacityMinutes > 0
            ? $annualDepreciation / $annualCapacityMinutes
            : 0;
    }

    /**
     * Allocate building costs to production based on space and time usage
     */
    protected function allocateBuildingCost(WorkCenter $workCenter, float $minutesUsed): float
    {
        $building = $workCenter->buildingAsset;
        if (!$building) return 0;

        // Square footage allocation × time proportion
        $totalBuildingCost = $building->annual_depreciation_amount + $building->annual_operating_costs;
        $workCenterShare = $workCenter->square_footage / $building->total_square_footage;
        $timeShare = $minutesUsed / (8 * 60 * 250); // Annual minutes

        return $totalBuildingCost * $workCenterShare * $timeShare;
    }

    /**
     * Calculate tooling depreciation using units-of-production method
     */
    protected function calculateToolingDepreciation($tool, float $unitsProduced): float
    {
        $remainingUnits = $tool->useful_life_units - $tool->units_consumed;

        if ($remainingUnits <= 0) {
            // Fully depreciated, but still track usage
            return 0;
        }

        $unitsToDepreciate = min($unitsProduced, $remainingUnits);
        $depreciationPerUnit = $tool->net_book_value / $remainingUnits;

        // Update tool tracking
        $tool->increment('units_consumed', $unitsToDepreciate);
        $tool->decrement('net_book_value', $unitsToDepreciate * $depreciationPerUnit);

        return $unitsToDepreciate * $depreciationPerUnit;
    }
}
