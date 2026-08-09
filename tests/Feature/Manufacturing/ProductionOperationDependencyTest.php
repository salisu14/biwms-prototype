<?php

declare(strict_types=1);

use App\Enums\ItemLedgerEntryType;
use App\Enums\ProductionHierarchyStatus;
use App\Enums\ProductionIntermediateHandoffStatus;
use App\Enums\ProductionOperationDependencyReadiness;
use App\Enums\ProductionOperationDependencyStatus;
use App\Enums\ProductionOperationDependencyType;
use App\Enums\ProductionOperationExecutionStatus;
use App\Enums\ProductionOrderSourceType;
use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionReservationStatus;
use App\Enums\ProductionReservationType;
use App\Enums\ProductionSupplyLinkStatus;
use App\Enums\ProductionSupplyType;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralProductPostingGroup;
use App\Models\InventoryPostingGroup;
use App\Models\Item;
use App\Models\ItemApplicationEntry;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\Manufacturing\ProductionHierarchy;
use App\Models\Manufacturing\ProductionIntermediateHandoff;
use App\Models\Manufacturing\ProductionMaterialReservation;
use App\Models\Manufacturing\ProductionOperationDependency;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionOrderComponent;
use App\Models\Manufacturing\ProductionOrderRoutingLine;
use App\Models\Manufacturing\ProductionOrderSupplyLink;
use App\Models\User;
use App\Services\Manufacturing\ProductionGenealogyService;
use App\Services\Manufacturing\ProductionOperationDependencyGenerationService;
use App\Services\Manufacturing\ProductionOperationDependencyProgressService;
use App\Services\Manufacturing\ProductionOperationDependencyReadinessService;
use App\Services\Manufacturing\ProductionOperationExecutionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('generates child output to parent operation dependencies idempotently', function (): void {
    $fixture = phase2bDependencyFixture();

    $first = app(ProductionOperationDependencyGenerationService::class)->generateForHierarchy($fixture['hierarchy']);
    $second = app(ProductionOperationDependencyGenerationService::class)->generateForHierarchy($fixture['hierarchy']);

    $dependency = ProductionOperationDependency::query()->firstOrFail();
    $handoff = ProductionIntermediateHandoff::query()->firstOrFail();

    expect($first)->toHaveCount(1)
        ->and($second)->toHaveCount(1)
        ->and(ProductionOperationDependency::query()->count())->toBe(1)
        ->and(ProductionIntermediateHandoff::query()->count())->toBe(1)
        ->and($dependency->upstream_routing_line_id)->toBe($fixture['childFinalOperation']->id)
        ->and($dependency->downstream_routing_line_id)->toBe($fixture['parentFirstOperation']->id)
        ->and($dependency->dependency_type)->toBe(ProductionOperationDependencyType::OutputAvailableToStart)
        ->and((float) $dependency->required_quantity_base)->toBe(100.0)
        ->and($handoff->production_operation_dependency_id)->toBe($dependency->id)
        ->and((float) $handoff->quantity_required_base)->toBe(100.0);
});

it('refuses ambiguous dependency mapping instead of falling back to first parent operation', function (): void {
    $fixture = phase2bDependencyFixture();
    $component = $fixture['component'];
    $component->forceFill(['routing_link_code' => null])->save();

    expect(fn () => app(ProductionOperationDependencyGenerationService::class)->generateForHierarchy($fixture['hierarchy']))
        ->toThrow(RuntimeException::class, 'Dependency mapping requires review');

    expect(ProductionOperationDependency::query()->count())->toBe(0)
        ->and(ProductionIntermediateHandoff::query()->count())->toBe(0);
});

it('allows safe dependency mapping inference when parent has one operation', function (): void {
    $fixture = phase2bDependencyFixture();
    $fixture['parentSecondOperation']->delete();
    $fixture['component']->forceFill(['routing_link_code' => null])->save();

    $dependency = app(ProductionOperationDependencyGenerationService::class)
        ->generateForHierarchy($fixture['hierarchy'])[0];

    expect($dependency->downstream_routing_line_id)->toBe($fixture['parentFirstOperation']->id)
        ->and($dependency->metadata['mapping'])->toBe('single_parent_operation');
});

it('maps child output to the explicitly linked parent operation and not the first operation', function (): void {
    $fixture = phase2bDependencyFixture();
    $fixture['parentFirstOperation']->forceFill(['routing_link_code' => 'WATER'])->save();
    $fixture['parentSecondOperation']->forceFill(['routing_link_code' => 'FILL'])->save();
    $fixture['component']->forceFill(['routing_link_code' => 'FILL'])->save();

    $dependency = app(ProductionOperationDependencyGenerationService::class)
        ->generateForHierarchy($fixture['hierarchy']->fresh())[0];

    expect($dependency->downstream_routing_line_id)->toBe($fixture['parentSecondOperation']->id)
        ->and($dependency->downstream_routing_line_id)->not->toBe($fixture['parentFirstOperation']->id)
        ->and($dependency->metadata['mapping'])->toBe('component_routing_link_code');
});

it('reports dependency readiness from child output availability', function (): void {
    $fixture = phase2bDependencyFixture();
    $dependency = app(ProductionOperationDependencyGenerationService::class)
        ->generateForHierarchy($fixture['hierarchy'])[0];

    $blocked = app(ProductionOperationDependencyReadinessService::class)
        ->readinessForRoutingLine($fixture['parentFirstOperation']);

    expect($blocked->ready)->toBeFalse()
        ->and($blocked->classification)->toBe(ProductionOperationDependencyReadiness::WaitingForUpstreamOutput);

    phase2bMarkSupplyAvailable($fixture['supplyLink'], '100');
    app(ProductionOperationDependencyProgressService::class)->syncForSupplyLink($fixture['supplyLink']->fresh());

    $ready = app(ProductionOperationDependencyReadinessService::class)
        ->readinessForRoutingLine($fixture['parentFirstOperation']);

    expect($ready->ready)->toBeTrue()
        ->and($dependency->fresh()->status)->toBe(ProductionOperationDependencyStatus::Fulfilled)
        ->and((float) $dependency->fresh()->fulfilled_quantity_base)->toBe(100.0);
});

it('tracks partial handoffs and consumption cumulatively without duplicating state', function (): void {
    $fixture = phase2bDependencyFixture();
    $dependency = app(ProductionOperationDependencyGenerationService::class)
        ->generateForHierarchy($fixture['hierarchy'])[0];

    phase2bMarkSupplyAvailable($fixture['supplyLink'], '40');
    app(ProductionOperationDependencyProgressService::class)->syncForSupplyLink($fixture['supplyLink']->fresh());

    $handoff = $dependency->fresh()->handoffs()->firstOrFail();
    expect((float) $dependency->fresh()->fulfilled_quantity_base)->toBe(40.0)
        ->and((float) $handoff->quantity_available_base)->toBe(40.0)
        ->and($handoff->status)->toBe(ProductionIntermediateHandoffStatus::PartiallyAvailable);

    phase2bMarkSupplyAvailable($fixture['supplyLink'], '70');
    phase2bMarkSupplyConsumed($fixture['supplyLink'], '25');
    app(ProductionOperationDependencyProgressService::class)->syncForSupplyLink($fixture['supplyLink']->fresh());
    app(ProductionOperationDependencyProgressService::class)->syncForSupplyLink($fixture['supplyLink']->fresh());

    $handoff = $handoff->fresh();
    expect(ProductionIntermediateHandoff::query()->count())->toBe(1)
        ->and((float) $dependency->fresh()->fulfilled_quantity_base)->toBe(70.0)
        ->and((float) $handoff->quantity_available_base)->toBe(70.0)
        ->and((float) $handoff->quantity_transferred_base)->toBe(25.0)
        ->and($handoff->status)->toBe(ProductionIntermediateHandoffStatus::PartiallyConsumed);

    phase2bMarkSupplyConsumed($fixture['supplyLink'], '45');
    app(ProductionOperationDependencyProgressService::class)->syncForSupplyLink($fixture['supplyLink']->fresh());

    expect((float) $handoff->fresh()->quantity_transferred_base)->toBe(45.0);
});

it('supports minimum start quantity thresholds without requiring full supply', function (): void {
    $fixture = phase2bDependencyFixture();
    $dependency = app(ProductionOperationDependencyGenerationService::class)
        ->generateForHierarchy($fixture['hierarchy'])[0];
    $dependency->forceFill(['minimum_start_quantity_base' => '40.00000000'])->save();

    phase2bMarkSupplyAvailable($fixture['supplyLink'], '39');
    app(ProductionOperationDependencyProgressService::class)->syncForSupplyLink($fixture['supplyLink']->fresh());

    $readiness = app(ProductionOperationDependencyReadinessService::class)
        ->readinessForRoutingLine($fixture['parentFirstOperation']);
    expect($readiness->ready)->toBeFalse()
        ->and($readiness->classification)->toBe(ProductionOperationDependencyReadiness::PartiallyReady);

    phase2bMarkSupplyAvailable($fixture['supplyLink'], '40');
    app(ProductionOperationDependencyProgressService::class)->syncForSupplyLink($fixture['supplyLink']->fresh());

    $readiness = app(ProductionOperationDependencyReadinessService::class)
        ->readinessForRoutingLine($fixture['parentFirstOperation']);
    expect($readiness->ready)->toBeTrue()
        ->and($dependency->fresh()->status)->toBe(ProductionOperationDependencyStatus::Ready);
});

it('caps overproduced child supply at the dependency required quantity', function (): void {
    $fixture = phase2bDependencyFixture();
    $dependency = app(ProductionOperationDependencyGenerationService::class)
        ->generateForHierarchy($fixture['hierarchy'])[0];

    phase2bMarkSupplyAvailable($fixture['supplyLink'], '110');
    phase2bMarkSupplyConsumed($fixture['supplyLink'], '125');
    app(ProductionOperationDependencyProgressService::class)->syncForSupplyLink($fixture['supplyLink']->fresh());

    $handoff = $dependency->fresh()->handoffs()->firstOrFail();
    expect((float) $dependency->fresh()->fulfilled_quantity_base)->toBe(100.0)
        ->and((float) $handoff->quantity_available_base)->toBe(100.0)
        ->and((float) $handoff->quantity_transferred_base)->toBe(100.0)
        ->and($handoff->status)->toBe(ProductionIntermediateHandoffStatus::Consumed);
});

it('blocks shop floor operation start until inter-order dependencies are satisfied', function (): void {
    $fixture = phase2bDependencyFixture();
    app(ProductionOperationDependencyGenerationService::class)->generateForHierarchy($fixture['hierarchy']);

    $execution = app(ProductionOperationExecutionService::class)
        ->getOrCreateExecution($fixture['parentOrder'], $fixture['parentFirstOperation']);

    expect(fn () => app(ProductionOperationExecutionService::class)->startSetup($execution))
        ->toThrow(RuntimeException::class, 'Operation cannot start: Upstream output is not available yet.');

    phase2bMarkSupplyAvailable($fixture['supplyLink'], '100');
    app(ProductionOperationDependencyProgressService::class)->syncForSupplyLink($fixture['supplyLink']->fresh());

    $started = app(ProductionOperationExecutionService::class)->startSetup($execution->fresh());

    expect($started->status)->toBe(ProductionOperationExecutionStatus::SetupStarted);
});

it('keeps downstream operation blocked while upstream output has an active quality hold', function (): void {
    $fixture = phase2bDependencyFixture();
    app(ProductionOperationDependencyGenerationService::class)->generateForHierarchy($fixture['hierarchy']);
    phase2bMarkSupplyAvailable($fixture['supplyLink'], '100');
    app(ProductionOperationDependencyProgressService::class)->syncForSupplyLink($fixture['supplyLink']->fresh());

    $upstreamExecution = app(ProductionOperationExecutionService::class)
        ->getOrCreateExecution($fixture['childOrder'], $fixture['childFinalOperation']);
    $upstreamExecution->qualityHolds()->create([
        'status' => 'active',
        'reason' => 'QC hold',
        'placed_at' => now(),
    ]);

    $readiness = app(ProductionOperationDependencyReadinessService::class)
        ->readinessForRoutingLine($fixture['parentFirstOperation']);

    expect($readiness->ready)->toBeFalse()
        ->and($readiness->classification)->toBe(ProductionOperationDependencyReadiness::WaitingForQualityRelease);
});

it('blocks downstream readiness when upstream order is cancelled', function (): void {
    $fixture = phase2bDependencyFixture();
    $dependency = app(ProductionOperationDependencyGenerationService::class)
        ->generateForHierarchy($fixture['hierarchy'])[0];

    $fixture['childOrder']->forceFill(['status' => ProductionOrderStatus::CANCELLED])->save();
    app(ProductionOperationDependencyProgressService::class)->syncDependency($dependency);

    $readiness = app(ProductionOperationDependencyReadinessService::class)
        ->readinessForRoutingLine($fixture['parentFirstOperation']);

    expect($readiness->ready)->toBeFalse()
        ->and($readiness->classification)->toBe(ProductionOperationDependencyReadiness::UpstreamCancelled)
        ->and($dependency->fresh()->status)->toBe(ProductionOperationDependencyStatus::Invalid);
});

it('detects direct and indirect dependency cycles', function (): void {
    $fixture = phase2bDependencyFixture();

    ProductionOperationDependency::query()->create(phase2bDependencyPayload(
        $fixture,
        $fixture['childFirstOperation'],
        $fixture['parentFirstOperation'],
        'cycle-a',
    ));
    ProductionOperationDependency::query()->create(phase2bDependencyPayload(
        $fixture,
        $fixture['parentFirstOperation'],
        $fixture['childFirstOperation'],
        'cycle-b',
    ));

    expect(fn () => app(ProductionOperationDependencyGenerationService::class)->assertAcyclic($fixture['hierarchy']))
        ->toThrow(RuntimeException::class, 'Production operation dependency graph contains a cycle.');
});

it('traces genealogy backward from finished output through intermediate output to raw material', function (): void {
    $fixture = phase2bDependencyFixture();

    $rawInbound = phase2bLedger($fixture, $fixture['rawMaterial'], ItemLedgerEntryType::PURCHASE, 100, null, 'RAW-LOT-001');
    $childConsumption = phase2bLedger($fixture, $fixture['rawMaterial'], ItemLedgerEntryType::CONSUMPTION, -20, $fixture['childOrder'], 'RAW-LOT-001');
    $childOutput = phase2bLedger($fixture, $fixture['intermediateItem'], ItemLedgerEntryType::OUTPUT, 100, $fixture['childOrder'], 'BULK-LOT-001');
    $parentConsumption = phase2bLedger($fixture, $fixture['intermediateItem'], ItemLedgerEntryType::CONSUMPTION, -100, $fixture['parentOrder'], 'BULK-LOT-001');
    $parentOutput = phase2bLedger($fixture, $fixture['finishedItem'], ItemLedgerEntryType::OUTPUT, 50, $fixture['parentOrder'], 'FG-LOT-001');

    ItemApplicationEntry::query()->create([
        'inbound_item_ledger_entry_id' => $rawInbound->id,
        'outbound_item_ledger_entry_id' => $childConsumption->id,
        'applied_quantity' => 20,
        'application_date' => now()->toDateString(),
        'application_source' => 'test',
        'costing_method' => 'FIFO',
        'unit_cost' => 1,
        'cost_amount' => 20,
        'is_reversed' => false,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    ItemApplicationEntry::query()->create([
        'inbound_item_ledger_entry_id' => $childOutput->id,
        'outbound_item_ledger_entry_id' => $parentConsumption->id,
        'applied_quantity' => 100,
        'application_date' => now()->toDateString(),
        'application_source' => 'test',
        'costing_method' => 'FIFO',
        'unit_cost' => 1,
        'cost_amount' => 100,
        'is_reversed' => false,
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $trace = app(ProductionGenealogyService::class)->traceBackwardFromOutput($parentOutput);

    expect($trace['production_order_no'])->toBe($fixture['parentOrder']->document_number)
        ->and($trace['inputs'][0]['sources'][0]['source_lot_number'])->toBe('BULK-LOT-001')
        ->and($trace['inputs'][0]['sources'][0]['child_output']['inputs'][0]['sources'][0]['source_lot_number'])->toBe('RAW-LOT-001');
});

it('traces genealogy forward from raw lot and ignores reversed applications', function (): void {
    $fixture = phase2bDependencyFixture();

    $rawInbound = phase2bLedger($fixture, $fixture['rawMaterial'], ItemLedgerEntryType::PURCHASE, 100, null, 'GIN-2026-008');
    $reversedConsumption = phase2bLedger($fixture, $fixture['rawMaterial'], ItemLedgerEntryType::CONSUMPTION, -5, $fixture['childOrder'], 'GIN-2026-008');
    $activeConsumption = phase2bLedger($fixture, $fixture['rawMaterial'], ItemLedgerEntryType::CONSUMPTION, -20, $fixture['childOrder'], 'GIN-2026-008');
    $childOutput = phase2bLedger($fixture, $fixture['intermediateItem'], ItemLedgerEntryType::OUTPUT, 100, $fixture['childOrder'], 'BULK-LOT-001');

    ItemApplicationEntry::query()->create([
        'inbound_item_ledger_entry_id' => $rawInbound->id,
        'outbound_item_ledger_entry_id' => $reversedConsumption->id,
        'applied_quantity' => 5,
        'application_date' => now()->toDateString(),
        'application_source' => 'test',
        'costing_method' => 'FIFO',
        'unit_cost' => 1,
        'cost_amount' => 5,
        'is_reversed' => true,
        'idempotency_key' => (string) Str::uuid(),
    ]);
    ItemApplicationEntry::query()->create([
        'inbound_item_ledger_entry_id' => $rawInbound->id,
        'outbound_item_ledger_entry_id' => $activeConsumption->id,
        'applied_quantity' => 20,
        'application_date' => now()->toDateString(),
        'application_source' => 'test',
        'costing_method' => 'FIFO',
        'unit_cost' => 1,
        'cost_amount' => 20,
        'is_reversed' => false,
        'idempotency_key' => (string) Str::uuid(),
    ]);

    $trace = app(ProductionGenealogyService::class)->traceForwardFromInput($rawInbound);

    expect($trace['lot_number'])->toBe('GIN-2026-008')
        ->and($trace['used_by'])->toHaveCount(1)
        ->and($trace['used_by'][0]['consumption_ledger_entry_id'])->toBe($activeConsumption->id)
        ->and($trace['used_by'][0]['outputs'][0]['ledger_entry_id'])->toBe($childOutput->id);
});

it('reports unresolved dependency mappings in hierarchy reconciliation', function (): void {
    $fixture = phase2bDependencyFixture();
    $fixture['component']->forceFill(['routing_link_code' => null])->save();

    Artisan::call('biwms:manufacturing-hierarchy-reconcile', ['--json' => true]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($report['unresolved_dependency_mapping'])->toHaveCount(1)
        ->and($report['unresolved_dependency_mapping'][0]['message'])->toBe('Dependency mapping requires review.');
});

/**
 * @return array<string, mixed>
 */
function phase2bDependencyFixture(): array
{
    $user = User::factory()->create();
    $businessGroup = GeneralBusinessPostingGroup::query()->create(['code' => 'MFG-2B', 'description' => 'MFG 2B']);
    $productGroup = GeneralProductPostingGroup::query()->create(['code' => 'FG-2B', 'description' => 'FG 2B']);
    $inventoryGroup = InventoryPostingGroup::query()->create(['code' => 'INV-2B', 'description' => 'INV 2B']);
    $location = Location::query()->create(['code' => 'MAIN', 'name' => 'Main']);

    $finishedItem = Item::factory()->create([
        'item_code' => 'FG-2B',
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
    ]);
    $intermediateItem = Item::factory()->create([
        'item_code' => 'BULK-2B',
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
    ]);
    $rawMaterial = Item::factory()->create([
        'item_code' => 'RAW-2B',
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
    ]);

    $parentOrder = phase2bProductionOrder('PO-PACK-2B', $finishedItem, $businessGroup, $productGroup, $inventoryGroup, $user);
    $childOrder = phase2bProductionOrder('PO-BULK-2B', $intermediateItem, $businessGroup, $productGroup, $inventoryGroup, $user);

    $parentFirstOperation = phase2bRoutingLine($parentOrder, 10000, '10', 'Tray Packing', 'PACK');
    $parentSecondOperation = phase2bRoutingLine($parentOrder, 20000, '20', 'Carton Packing');
    $childFirstOperation = phase2bRoutingLine($childOrder, 10000, '10', 'Extraction');
    $childFinalOperation = phase2bRoutingLine($childOrder, 30000, '30', 'Mixing');

    $component = ProductionOrderComponent::query()->create([
        'production_order_id' => $parentOrder->id,
        'line_number' => 10000,
        'item_id' => $intermediateItem->id,
        'description' => 'Bulk herbal liquid',
        'unit_of_measure_code' => 'L',
        'quantity_per' => 1,
        'expected_quantity' => 100,
        'expected_quantity_base' => 100,
        'remaining_quantity' => 100,
        'routing_link_code' => 'PACK',
        'is_manufactured_requirement' => true,
        'required_supply_quantity_base' => 100,
    ]);

    $hierarchy = ProductionHierarchy::query()->create([
        'root_production_order_id' => $parentOrder->id,
        'planning_version' => 1,
        'status' => ProductionHierarchyStatus::ChildrenGenerated,
        'node_count' => 2,
        'manufactured_component_count' => 1,
        'planned_quantity_base' => 50,
        'planned_uom_code' => 'CT',
        'created_by' => $user->id,
    ]);

    $supplyLink = ProductionOrderSupplyLink::query()->create([
        'production_hierarchy_id' => $hierarchy->id,
        'root_production_order_id' => $parentOrder->id,
        'parent_production_order_id' => $parentOrder->id,
        'parent_component_id' => $component->id,
        'child_production_order_id' => $childOrder->id,
        'item_id' => $intermediateItem->id,
        'unit_of_measure_code' => 'L',
        'supply_type' => ProductionSupplyType::GeneratedChildOrder,
        'status' => ProductionSupplyLinkStatus::ChildOrderCreated,
        'required_quantity_base' => 100,
        'planned_supply_quantity_base' => 100,
        'produced_quantity_base' => 0,
        'supplied_quantity_base' => 0,
        'consumed_quantity_base' => 0,
        'idempotency_key' => (string) Str::uuid(),
    ]);

    ProductionMaterialReservation::query()->create([
        'production_hierarchy_id' => $hierarchy->id,
        'production_order_id' => $parentOrder->id,
        'production_order_component_id' => $component->id,
        'production_order_supply_link_id' => $supplyLink->id,
        'item_id' => $intermediateItem->id,
        'child_production_order_id' => $childOrder->id,
        'reservation_type' => ProductionReservationType::ChildOutput,
        'status' => ProductionReservationStatus::Active,
        'quantity_base' => 100,
        'remaining_quantity_base' => 100,
        'idempotency_key' => (string) Str::uuid(),
    ]);

    return compact(
        'user',
        'finishedItem',
        'intermediateItem',
        'rawMaterial',
        'location',
        'parentOrder',
        'childOrder',
        'parentFirstOperation',
        'childFirstOperation',
        'childFinalOperation',
        'parentSecondOperation',
        'component',
        'hierarchy',
        'supplyLink',
    );
}

function phase2bProductionOrder(
    string $documentNumber,
    Item $item,
    GeneralBusinessPostingGroup $businessGroup,
    GeneralProductPostingGroup $productGroup,
    InventoryPostingGroup $inventoryGroup,
    User $user,
): ProductionOrder {
    return ProductionOrder::query()->create([
        'document_number' => $documentNumber,
        'status' => ProductionOrderStatus::RELEASED,
        'source_type' => ProductionOrderSourceType::ITEM,
        'source_no' => $item->item_code,
        'item_id' => $item->id,
        'description' => $item->description,
        'quantity' => 100,
        'quantity_base' => 100,
        'unit_of_measure_code' => 'PCS',
        'conversion_factor' => 1,
        'general_business_posting_group_id' => $businessGroup->id,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
        'location_code' => 'MAIN',
        'costing_method' => 'FIFO',
        'flushing_method' => 'MANUAL',
        'created_by' => $user->id,
    ]);
}

function phase2bRoutingLine(ProductionOrder $order, int $lineNumber, string $operationNo, string $description, ?string $routingLinkCode = null): ProductionOrderRoutingLine
{
    return ProductionOrderRoutingLine::query()->create([
        'production_order_id' => $order->id,
        'line_number' => $lineNumber,
        'operation_no' => $operationNo,
        'description' => $description,
        'expected_output_quantity' => $order->quantity_base,
        'routing_link_code' => $routingLinkCode,
    ]);
}

function phase2bMarkSupplyAvailable(ProductionOrderSupplyLink $link, string $quantityBase): void
{
    ProductionOrderSupplyLink::allowServiceMutation(function () use ($link, $quantityBase): void {
        $link->forceFill([
            'produced_quantity_base' => $quantityBase,
            'supplied_quantity_base' => $quantityBase,
            'status' => ProductionSupplyLinkStatus::Available,
        ])->save();
    });
}

function phase2bMarkSupplyConsumed(ProductionOrderSupplyLink $link, string $quantityBase): void
{
    ProductionOrderSupplyLink::allowServiceMutation(function () use ($link, $quantityBase): void {
        $link->forceFill([
            'consumed_quantity_base' => $quantityBase,
            'status' => ProductionSupplyLinkStatus::PartiallySupplied,
        ])->save();
    });
}

/**
 * @return array<string, mixed>
 */
function phase2bDependencyPayload(array $fixture, ProductionOrderRoutingLine $upstream, ProductionOrderRoutingLine $downstream, string $key): array
{
    return [
        'production_hierarchy_id' => $fixture['hierarchy']->id,
        'root_production_order_id' => $fixture['parentOrder']->id,
        'upstream_production_order_id' => $upstream->production_order_id,
        'upstream_routing_line_id' => $upstream->id,
        'downstream_production_order_id' => $downstream->production_order_id,
        'downstream_routing_line_id' => $downstream->id,
        'item_id' => $fixture['intermediateItem']->id,
        'dependency_type' => ProductionOperationDependencyType::OutputAvailableToStart,
        'status' => ProductionOperationDependencyStatus::Planned,
        'required_quantity_base' => 1,
        'minimum_start_quantity_base' => 1,
        'fulfilled_quantity_base' => 0,
        'source' => 'test',
        'idempotency_key' => $key,
    ];
}

/**
 * @param  array<string, mixed>  $fixture
 */
function phase2bLedger(array $fixture, Item $item, ItemLedgerEntryType $entryType, float $quantity, ?ProductionOrder $order = null, ?string $lotNumber = null): ItemLedgerEntry
{
    return ItemLedgerEntry::query()->create([
        'entry_type' => $entryType,
        'item_id' => $item->id,
        'location_id' => $fixture['location']->id,
        'quantity' => $quantity,
        'remaining_quantity' => max(0, $quantity),
        'open' => $quantity > 0,
        'posting_date' => now(),
        'document_number' => $order?->document_number ?? 'RAW-INBOUND',
        'document_line_number' => 10000,
        'source_id' => $order?->id,
        'source_type' => $order ? ProductionOrder::class : null,
        'lot_number' => $lotNumber,
        'cost_amount_actual' => abs($quantity),
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'entry_date' => now(),
    ]);
}
