<?php

declare(strict_types=1);

use App\Enums\ItemType;
use App\Enums\ProductionHierarchyStatus;
use App\Enums\ProductionReservationStatus;
use App\Enums\ProductionSupplyType;
use App\Models\Business;
use App\Models\Manufacturing\ProductionHierarchy;
use App\Models\Manufacturing\ProductionHierarchyNode;
use App\Models\Manufacturing\ProductionMaterialReservation;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionOrderSupplyLink;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('adds the phase 2a hierarchy schema and semi finished item type', function (): void {
    expect(ItemType::SEMI_FINISHED->value)->toBe('SEMI_FINISHED')
        ->and(Schema::hasColumns('production_orders', [
            'parent_production_order_id',
            'root_production_order_id',
            'production_level',
            'hierarchy_path',
            'order_origin',
            'source_production_order_component_id',
            'planning_group_id',
            'hierarchy_planning_version',
        ]))->toBeTrue()
        ->and(Schema::hasColumns('production_order_components', [
            'hierarchy_node_id',
            'is_manufactured_requirement',
            'required_supply_quantity_base',
        ]))->toBeTrue()
        ->and(Schema::hasTable('production_hierarchies'))->toBeTrue()
        ->and(Schema::hasTable('production_hierarchy_nodes'))->toBeTrue()
        ->and(Schema::hasTable('production_order_supply_links'))->toBeTrue()
        ->and(Schema::hasTable('production_material_reservations'))->toBeTrue();
});

it('persists hierarchy, supply link, and reservation relationships without posting side effects', function (): void {
    $reservation = ProductionMaterialReservation::factory()->create();
    $supplyLink = $reservation->productionOrderSupplyLink()->first();
    $node = $reservation->productionHierarchyNode()->first();
    $hierarchy = $reservation->productionHierarchy()->first();
    $order = $reservation->productionOrder()->first();
    $component = $reservation->productionOrderComponent()->first();

    expect($reservation->productionOrderSupplyLink->is($supplyLink))->toBeTrue()
        ->and($reservation->productionHierarchyNode->is($node))->toBeTrue()
        ->and($reservation->productionHierarchy->is($hierarchy))->toBeTrue()
        ->and($order->productionHierarchies()->whereKey($hierarchy)->exists())->toBeTrue()
        ->and($order->supplyLinksAsParent()->whereKey($supplyLink)->exists())->toBeTrue()
        ->and($component->supplyLinks()->whereKey($supplyLink)->exists())->toBeTrue()
        ->and($component->materialReservations()->whereKey($reservation)->exists())->toBeTrue()
        ->and($node->materialReservations()->whereKey($reservation)->exists())->toBeTrue();
});

it('allows multiple source-specific supply links for one parent component while preserving duplicate child idempotency', function (): void {
    $firstLink = ProductionOrderSupplyLink::factory()->create();
    $parentComponent = $firstLink->parentComponent;
    $parentOrder = ProductionOrder::query()->findOrFail($firstLink->parent_production_order_id);
    $user = User::factory()->create();
    $childOrder = ProductionOrder::withoutEvents(fn (): ProductionOrder => ProductionOrder::query()->forceCreate([
        'document_number' => 'CHILD-'.fake()->unique()->numerify('######'),
        'status' => $parentOrder->status,
        'item_id' => $parentOrder->item_id,
        'quantity' => $parentOrder->quantity,
        'unit_of_measure_code' => $parentOrder->unit_of_measure_code,
        'quantity_base' => $parentOrder->quantity_base,
        'parent_production_order_id' => $firstLink->parent_production_order_id,
        'root_production_order_id' => $firstLink->root_production_order_id,
        'created_by' => $user->getKey(),
    ]));

    $manualSupply = ProductionOrderSupplyLink::factory()->create([
        'production_hierarchy_id' => $firstLink->production_hierarchy_id,
        'production_hierarchy_node_id' => $firstLink->production_hierarchy_node_id,
        'root_production_order_id' => $firstLink->root_production_order_id,
        'parent_production_order_id' => $firstLink->parent_production_order_id,
        'parent_component_id' => $parentComponent->id,
        'child_production_order_id' => null,
        'supply_type' => ProductionSupplyType::ManualSupply,
    ]);

    expect($manualSupply)->toBeInstanceOf(ProductionOrderSupplyLink::class)
        ->and($parentComponent->supplyLinks()->count())->toBe(2);

    ProductionOrderSupplyLink::factory()->create([
        'production_hierarchy_id' => $firstLink->production_hierarchy_id,
        'production_hierarchy_node_id' => $firstLink->production_hierarchy_node_id,
        'root_production_order_id' => $firstLink->root_production_order_id,
        'parent_production_order_id' => $firstLink->parent_production_order_id,
        'parent_component_id' => $parentComponent->id,
        'child_production_order_id' => $childOrder->id,
        'supply_type' => ProductionSupplyType::GeneratedChildOrder,
    ]);

    expect(fn (): ProductionOrderSupplyLink => ProductionOrderSupplyLink::factory()->create([
        'production_hierarchy_id' => $firstLink->production_hierarchy_id,
        'production_hierarchy_node_id' => $firstLink->production_hierarchy_node_id,
        'root_production_order_id' => $firstLink->root_production_order_id,
        'parent_production_order_id' => $firstLink->parent_production_order_id,
        'parent_component_id' => $parentComponent->id,
        'child_production_order_id' => $childOrder->id,
        'supply_type' => ProductionSupplyType::GeneratedChildOrder,
    ]))->toThrow(QueryException::class);
});

it('enforces production hierarchy immutability and reservation quantity guards', function (): void {
    $hierarchy = ProductionHierarchy::factory()->create(['status' => ProductionHierarchyStatus::Released]);

    expect(fn (): bool => $hierarchy->forceFill(['node_count' => 1])->save())
        ->toThrow(RuntimeException::class);

    $reservation = ProductionMaterialReservation::factory()->create([
        'status' => ProductionReservationStatus::Consumed,
    ]);

    expect(fn (): bool => $reservation->forceFill(['remaining_quantity_base' => '0'])->save())
        ->toThrow(RuntimeException::class);
});

it('blocks cross-business hierarchy links in model guards', function (): void {
    $firstBusiness = Business::query()->create(['code' => 'B1', 'name' => 'Business One']);
    $secondBusiness = Business::query()->create(['code' => 'B2', 'name' => 'Business Two']);
    $hierarchy = ProductionHierarchy::factory()->create(['business_id' => $firstBusiness->id]);
    $parentNode = ProductionHierarchyNode::factory()->create([
        'business_id' => $firstBusiness->id,
        'production_hierarchy_id' => $hierarchy->id,
        'root_production_order_id' => $hierarchy->root_production_order_id,
    ]);

    expect(fn (): ProductionHierarchyNode => ProductionHierarchyNode::factory()->create([
        'business_id' => $secondBusiness->id,
        'production_hierarchy_id' => $parentNode->production_hierarchy_id,
        'root_production_order_id' => $parentNode->root_production_order_id,
        'parent_node_id' => $parentNode->id,
        'node_path' => '1.1',
        'level' => 1,
    ]))->toThrow(RuntimeException::class);
});
