<?php

declare(strict_types=1);

namespace App\Services\Manufacturing;

use App\Enums\CostingMethod;
use App\Enums\ManufacturingCostComponent;
use App\Models\Item;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionOrderComponent;
use App\Models\Manufacturing\ProductionOrderRoutingLine;
use App\Models\ProductionExpectedCostSnapshot;
use App\Models\ValueEntry;
use App\Services\Inventory\CostingPeriodService;
use App\Services\Inventory\ValueEntryAccountingOrchestrator;
use App\Support\DecimalMath;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use UnitEnum;

class ExpectedManufacturingCostService
{
    public function __construct(
        private readonly CostingPeriodService $costingPeriodService,
        private readonly ValueEntryAccountingOrchestrator $accountingOrchestrator,
    ) {}

    /**
     * @return array{snapshot: ProductionExpectedCostSnapshot, value_entries: list<ValueEntry>}
     */
    public function calculate(
        ProductionOrder $order,
        ?float $outputQuantityBase = null,
        mixed $costingDate = null,
        ?int $userId = null,
        bool $createValueEntries = true
    ): array {
        $costingDate = Carbon::parse($costingDate ?? now())->startOfDay();
        $this->costingPeriodService->assertAdjustmentAllowed($costingDate);

        return DB::transaction(function () use ($order, $outputQuantityBase, $costingDate, $userId, $createValueEntries): array {
            /** @var ProductionOrder $lockedOrder */
            $lockedOrder = ProductionOrder::query()
                ->with([
                    'item',
                    'productionBom',
                    'productionBomVersion',
                    'routing',
                    'routingVersion',
                    'components.item',
                    'routingLines.workCenter',
                    'routingLines.machineCenter',
                ])
                ->lockForUpdate()
                ->findOrFail($order->id);

            $quantityBase = $outputQuantityBase ?? (float) $lockedOrder->quantity_base;
            if ($quantityBase <= 0.0) {
                throw new RuntimeException('Expected manufacturing cost requires a positive output quantity.');
            }

            $componentDetails = $lockedOrder->components
                ->map(fn (ProductionOrderComponent $component): array => $this->componentDetail($lockedOrder, $component, $quantityBase))
                ->values()
                ->all();

            $routingDetails = $lockedOrder->routingLines
                ->map(fn (ProductionOrderRoutingLine $routingLine): array => $this->routingDetail($lockedOrder, $routingLine, $quantityBase))
                ->values()
                ->all();

            $expectedMaterial = array_sum(array_column($componentDetails, 'expected_amount'));
            $expectedCapacity = array_sum(array_column($routingDetails, 'expected_direct_amount'));
            $expectedOverhead = array_sum(array_column($routingDetails, 'expected_overhead_amount'));
            $expectedTotal = round($expectedMaterial + $expectedCapacity + $expectedOverhead, 4);

            $identity = $this->calculationIdentity($lockedOrder, $quantityBase, $costingDate);
            $snapshot = ProductionExpectedCostSnapshot::query()->firstOrCreate(
                ['calculation_identity' => $identity],
                [
                    'production_order_id' => $lockedOrder->id,
                    'finished_item_id' => $lockedOrder->item_id,
                    'production_bom_id' => $lockedOrder->production_bom_id,
                    'production_bom_version_id' => $lockedOrder->production_bom_version_id,
                    'routing_id' => $lockedOrder->routing_id,
                    'routing_version_id' => $lockedOrder->routing_version_id,
                    'production_quantity_base' => DecimalMath::quantity($quantityBase),
                    'costing_date' => $costingDate->toDateString(),
                    'expected_material_cost' => DecimalMath::amount($expectedMaterial),
                    'expected_capacity_cost' => DecimalMath::amount($expectedCapacity),
                    'expected_overhead_cost' => DecimalMath::amount($expectedOverhead),
                    'expected_output_cost' => DecimalMath::amount($expectedTotal),
                    'expected_total_cost' => DecimalMath::amount($expectedTotal),
                    'status' => 'calculated',
                    'component_details' => $componentDetails,
                    'routing_details' => $routingDetails,
                    'cost_source_details' => [
                        'material' => array_column($componentDetails, 'cost_source', 'component_id'),
                        'capacity' => 'production_order_routing_line_rates',
                        'queue_time_costed' => false,
                        'wait_time_costed' => false,
                    ],
                    'metadata' => [
                        'phase_1d_expected_manufacturing_cost' => true,
                        'order_document_number' => $lockedOrder->document_number,
                    ],
                    'calculated_by' => $userId,
                    'calculated_at' => now(),
                ],
            );

            $valueEntries = $createValueEntries
                ? $this->createExpectedValueEntries($lockedOrder, $snapshot, $componentDetails, $routingDetails, $costingDate, $userId)
                : [];

            return [
                'snapshot' => $snapshot->fresh(),
                'value_entries' => $valueEntries,
            ];
        });
    }

    private function componentDetail(ProductionOrder $order, ProductionOrderComponent $component, float $outputQuantityBase): array
    {
        $plannedOutputBase = max((float) $order->quantity_base, 0.00000001);
        $ratio = $outputQuantityBase / $plannedOutputBase;
        $expectedQuantityBase = (float) $component->expected_quantity_base > 0.0
            ? (float) $component->expected_quantity_base * $ratio
            : (float) $component->quantity_per * $outputQuantityBase * (1 + ((float) $component->scrap_percent / 100));
        $item = $component->item;
        $unitCost = $this->expectedUnitCost($item, $component);
        $amount = round($expectedQuantityBase * $unitCost['unit_cost'], 4);

        return [
            'component_id' => $component->id,
            'line_number' => $component->line_number,
            'item_id' => $component->item_id,
            'item_no' => $item?->item_code,
            'quantity_per' => (float) $component->quantity_per,
            'expected_quantity_base' => round($expectedQuantityBase, 8),
            'unit_cost' => round($unitCost['unit_cost'], 8),
            'expected_amount' => $amount,
            'scrap_percent' => (float) $component->scrap_percent,
            'cost_source' => $unitCost['source'],
            'costing_method' => $unitCost['costing_method'],
        ];
    }

    /**
     * @return array{unit_cost: float, source: string, costing_method: string}
     */
    private function expectedUnitCost(?Item $item, ProductionOrderComponent $component): array
    {
        $costingMethod = $item?->costing_method instanceof CostingMethod
            ? $item->costing_method
            : CostingMethod::tryFrom((string) $item?->costing_method);

        if ($costingMethod === CostingMethod::STANDARD) {
            return [
                'unit_cost' => (float) ($item?->standard_cost ?: $component->unit_cost ?: $item?->unit_cost ?: 0),
                'source' => 'item_standard_cost',
                'costing_method' => CostingMethod::STANDARD->value,
            ];
        }

        return [
            'unit_cost' => (float) ($component->unit_cost ?: $item?->unit_cost ?: $item?->last_direct_cost ?: 0),
            'source' => 'current_expected_unit_cost',
            'costing_method' => $costingMethod?->value ?? (string) ($item?->costing_method?->value ?? $item?->costing_method ?? ''),
        ];
    }

    private function routingDetail(ProductionOrder $order, ProductionOrderRoutingLine $routingLine, float $outputQuantityBase): array
    {
        $plannedOutputBase = max((float) ($routingLine->expected_output_quantity ?: $order->quantity_base), 0.00000001);
        $ratio = $outputQuantityBase / $plannedOutputBase;
        $center = $routingLine->machineCenter ?? $routingLine->workCenter;
        $setupMinutes = $this->minutes((float) $routingLine->setup_time, (string) $routingLine->setup_time_unit);
        $runMinutes = $this->minutes((float) $routingLine->run_time, (string) $routingLine->run_time_unit) * $ratio;
        $costedMinutes = $setupMinutes + $runMinutes;
        $directRate = (float) ($routingLine->direct_cost ?: $center?->direct_unit_cost ?: 0);
        $overheadRate = (float) ($routingLine->overhead_cost ?: $center?->overhead_rate ?: 0);
        $directAmount = round($costedMinutes * $directRate, 4);
        $overheadAmount = round($costedMinutes * $overheadRate, 4);

        return [
            'routing_line_id' => $routingLine->id,
            'line_number' => $routingLine->line_number,
            'operation_no' => $routingLine->operation_no,
            'work_center_id' => $routingLine->work_center_id,
            'machine_center_id' => $routingLine->machine_center_id,
            'setup_time_minutes' => round($setupMinutes, 8),
            'run_time_minutes' => round($runMinutes, 8),
            'wait_time_minutes' => (float) $routingLine->wait_time,
            'queue_time_minutes' => 0.0,
            'costed_time_minutes' => round($costedMinutes, 8),
            'direct_rate' => round($directRate, 8),
            'overhead_rate' => round($overheadRate, 8),
            'expected_direct_amount' => $directAmount,
            'expected_overhead_amount' => $overheadAmount,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $componentDetails
     * @param  list<array<string, mixed>>  $routingDetails
     * @return list<ValueEntry>
     */
    private function createExpectedValueEntries(
        ProductionOrder $order,
        ProductionExpectedCostSnapshot $snapshot,
        array $componentDetails,
        array $routingDetails,
        Carbon $postingDate,
        ?int $userId
    ): array {
        $entries = [];

        foreach ($componentDetails as $detail) {
            if (abs((float) $detail['expected_amount']) <= 0.0001) {
                continue;
            }

            $entries[] = $this->createExpectedValueEntry(
                order: $order,
                snapshot: $snapshot,
                itemNo: (string) ($detail['item_no'] ?? $detail['item_id']),
                itemLedgerEntryType: 6,
                component: ManufacturingCostComponent::ExpectedDirectMaterial,
                quantity: -abs((float) $detail['expected_quantity_base']),
                amount: (float) $detail['expected_amount'],
                postingDate: $postingDate,
                sourceLineNo: (int) $detail['line_number'],
                description: 'Expected production material cost',
                metadata: $detail,
                userId: $userId,
            );
        }

        foreach ($routingDetails as $detail) {
            if (abs((float) $detail['expected_direct_amount']) > 0.0001) {
                $entries[] = $this->createExpectedValueEntry(
                    order: $order,
                    snapshot: $snapshot,
                    itemNo: (string) ($order->item?->item_code ?? $order->item_id),
                    itemLedgerEntryType: 8,
                    component: ManufacturingCostComponent::ExpectedDirectCapacity,
                    quantity: (float) $detail['costed_time_minutes'],
                    amount: (float) $detail['expected_direct_amount'],
                    postingDate: $postingDate,
                    sourceLineNo: (int) $detail['line_number'],
                    description: 'Expected production capacity cost',
                    metadata: $detail,
                    userId: $userId,
                );
            }

            if (abs((float) $detail['expected_overhead_amount']) > 0.0001) {
                $entries[] = $this->createExpectedValueEntry(
                    order: $order,
                    snapshot: $snapshot,
                    itemNo: (string) ($order->item?->item_code ?? $order->item_id),
                    itemLedgerEntryType: 10,
                    component: ManufacturingCostComponent::ExpectedCapacityOverhead,
                    quantity: (float) $detail['costed_time_minutes'],
                    amount: (float) $detail['expected_overhead_amount'],
                    postingDate: $postingDate,
                    sourceLineNo: (int) $detail['line_number'],
                    description: 'Expected production capacity overhead',
                    metadata: $detail,
                    userId: $userId,
                );
            }
        }

        if ((float) $snapshot->expected_total_cost > 0.0001) {
            $entries[] = $this->createExpectedValueEntry(
                order: $order,
                snapshot: $snapshot,
                itemNo: (string) ($order->item?->item_code ?? $order->item_id),
                itemLedgerEntryType: 7,
                component: ManufacturingCostComponent::ExpectedOutput,
                quantity: (float) $snapshot->production_quantity_base,
                amount: (float) $snapshot->expected_total_cost,
                postingDate: $postingDate,
                sourceLineNo: 0,
                description: 'Expected production output cost',
                metadata: ['snapshot_id' => $snapshot->id],
                userId: $userId,
            );
        }

        return $entries;
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    private function createExpectedValueEntry(
        ProductionOrder $order,
        ProductionExpectedCostSnapshot $snapshot,
        string $itemNo,
        int $itemLedgerEntryType,
        ManufacturingCostComponent $component,
        float $quantity,
        float $amount,
        Carbon $postingDate,
        int $sourceLineNo,
        string $description,
        array $metadata,
        ?int $userId
    ): ValueEntry {
        $idempotencyKey = hash('sha256', implode('|', [
            'expected-manufacturing-value-entry',
            $snapshot->id,
            $component->value,
            $itemNo,
            $sourceLineNo,
            DecimalMath::quantity($quantity),
            DecimalMath::amount($amount),
        ]));

        /** @var ValueEntry $entry */
        $entry = ValueEntry::query()->firstOrCreate(
            ['idempotency_key' => $idempotencyKey],
            [
                'entry_no' => (ValueEntry::query()->max('entry_no') ?? 0) + 1,
                'item_ledger_entry_type' => $itemLedgerEntryType,
                'item_no' => $itemNo,
                'location_code' => (string) ($order->location_code ?: 'MAIN'),
                'posting_date' => $postingDate->toDateString(),
                'valuation_date' => $postingDate->toDateString(),
                'document_type' => 'PRODUCTION_EXPECTED_COST',
                'document_no' => $order->document_number,
                'document_line_no' => $sourceLineNo,
                'description' => $description,
                'quantity' => DecimalMath::quantity($quantity),
                'invoiced_quantity' => 0,
                'valued_quantity' => DecimalMath::quantity($quantity),
                'remaining_quantity' => 0,
                'costing_method' => $order->costing_method instanceof UnitEnum ? $order->costing_method->value : (string) $order->costing_method,
                'cost_component' => $component->value,
                'value_entry_state' => 'expected',
                'cost_amount_actual' => 0,
                'cost_amount_expected' => DecimalMath::amount($amount),
                'cost_amount_actual_acy' => 0,
                'cost_amount_expected_acy' => DecimalMath::amount($amount),
                'unit_cost' => DecimalMath::unitCost(abs($quantity) > 0 ? $amount / abs($quantity) : 0),
                'unit_cost_acy' => DecimalMath::unitCost(abs($quantity) > 0 ? $amount / abs($quantity) : 0),
                'single_level_material_cost' => $component === ManufacturingCostComponent::ExpectedDirectMaterial ? DecimalMath::amount($amount) : 0,
                'single_level_capacity_cost' => $component === ManufacturingCostComponent::ExpectedDirectCapacity ? DecimalMath::amount($amount) : 0,
                'single_level_overhead_cost' => $component === ManufacturingCostComponent::ExpectedCapacityOverhead ? DecimalMath::amount($amount) : 0,
                'source_type' => 'PRODUCTION_EXPECTED_COST',
                'source_module' => 'manufacturing',
                'source_id' => $snapshot->id,
                'source_number' => $snapshot->calculation_identity,
                'source_no' => (string) $order->id,
                'source_line_no' => $sourceLineNo,
                'production_order_no' => $order->document_number,
                'production_order_line_no' => (string) $sourceLineNo,
                'prod_order_line_item_no' => $order->item?->item_code,
                'expected_cost' => true,
                'accounting_metadata' => [
                    'phase_1d_expected_manufacturing_cost' => true,
                    'snapshot_id' => $snapshot->id,
                    'calculation_identity' => $snapshot->calculation_identity,
                    'detail' => $metadata,
                ],
                'user_id' => $userId ? (string) $userId : null,
            ],
        );

        if (config('accounts.post_expected_inventory_cost_to_gl', false)) {
            $this->accountingOrchestrator->post($entry);
        }

        return $entry->fresh();
    }

    private function calculationIdentity(ProductionOrder $order, float $quantityBase, Carbon $costingDate): string
    {
        return hash('sha256', implode('|', [
            'expected-manufacturing-cost',
            $order->id,
            $order->document_number,
            DecimalMath::quantity($quantityBase),
            $costingDate->toDateString(),
            $order->production_bom_id,
            $order->production_bom_version_id,
            $order->routing_id,
            $order->routing_version_id,
        ]));
    }

    private function minutes(float $time, string $unit): float
    {
        return match (strtoupper($unit)) {
            'HOURS', 'HOUR', 'HR', 'HRS' => $time * 60,
            'DAYS', 'DAY' => $time * 1440,
            default => $time,
        };
    }
}
