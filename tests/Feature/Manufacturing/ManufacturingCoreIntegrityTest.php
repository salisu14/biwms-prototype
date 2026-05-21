<?php

use App\Enums\ProductionOrderStatus;
use App\Enums\WarehouseActivityType;
use App\Enums\WarehouseDocumentStatus;
use App\Events\ProductionOrderStatusChanged;
use App\Models\GeneralProductPostingGroup;
use App\Models\InventoryPostingGroup;
use App\Models\Item;
use App\Models\Location;
use App\Models\Manufacturing\ProductionBom;
use App\Models\Manufacturing\ProductionBomLine;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\User;
use App\Models\WarehouseActivity;
use App\Models\WarehouseRequest;
use App\Services\Manufacturing\ProductionOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('production bom recursive cost uses related bom link and applies scrap', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $generalProductPostingGroup = GeneralProductPostingGroup::create([
        'code' => 'RM',
        'description' => 'Raw Materials',
    ]);
    $inventoryPostingGroup = InventoryPostingGroup::create([
        'code' => 'MAIN',
        'description' => 'Main Inventory',
    ]);

    $componentA = Item::create([
        'item_code' => 'COMP-A',
        'description' => 'Component A',
        'unit_cost' => 10,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
    ]);

    $componentB = Item::create([
        'item_code' => 'COMP-B',
        'description' => 'Component B',
        'unit_cost' => 2,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
    ]);

    $subBom = ProductionBom::create([
        'code' => 'BOM-SUB',
        'description' => 'Sub BOM',
    ]);

    $subBom->lines()->create([
        'line_number' => 10000,
        'type' => ProductionBomLine::TYPE_ITEM,
        'item_id' => $componentB->id,
        'quantity_per' => 3,
        'scrap_percent' => 0,
    ]);

    $mainBom = ProductionBom::create([
        'code' => 'BOM-MAIN',
        'description' => 'Main BOM',
    ]);

    $mainBom->lines()->create([
        'line_number' => 10000,
        'type' => ProductionBomLine::TYPE_ITEM,
        'item_id' => $componentA->id,
        'quantity_per' => 2,
        'scrap_percent' => 10,
    ]);

    $mainBom->lines()->create([
        'line_number' => 20000,
        'type' => ProductionBomLine::TYPE_PRODUCTION_BOM,
        'production_bom_id_related' => $subBom->id,
        'quantity_per' => 4,
        'scrap_percent' => 0,
    ]);

    expect($mainBom->calculateCost())->toBe(46.0);
});

test('production order warehouse relations use document number as source reference', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $generalProductPostingGroup = GeneralProductPostingGroup::create([
        'code' => 'FG',
        'description' => 'Finished Goods',
    ]);
    $inventoryPostingGroup = InventoryPostingGroup::create([
        'code' => 'FG',
        'description' => 'Finished Goods',
    ]);

    $item = Item::create([
        'item_code' => 'FG-REL-001',
        'description' => 'Finished Good',
        'unit_cost' => 15,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
    ]);
    $location = Location::factory()->create(['code' => 'MAIN']);

    $order = ProductionOrder::create([
        'document_number' => 'PO-REL-001',
        'status' => ProductionOrderStatus::PLANNED,
        'item_id' => $item->id,
        'quantity' => 1,
        'quantity_base' => 1,
        'location_code' => $location->code,
    ]);

    WarehouseRequest::create([
        'source_document' => 'production_order',
        'source_no' => $order->document_number,
        'source_line_no' => 10000,
        'source_id' => $order->id,
        'request_type' => 'pick',
        'location_id' => $location->id,
        'item_id' => $item->id,
        'quantity' => 1,
        'quantity_base' => 1,
        'unit_of_measure_code' => 'PCS',
        'quantity_outstanding' => 1,
        'status' => 'open',
    ]);

    WarehouseActivity::create([
        'no' => 'ACT-REL-001',
        'activity_type' => WarehouseActivityType::PICK,
        'status' => WarehouseDocumentStatus::OPEN,
        'location_id' => $location->id,
        'source_document' => 'production_order',
        'source_no' => $order->document_number,
        'source_line_no' => 10000,
        'source_id' => $order->id,
    ]);

    expect($order->warehouseRequests()->count())->toBe(1)
        ->and($order->warehouseActivities()->count())->toBe(1);
});

test('production order status changed event carries order and statuses', function () {
    $order = new ProductionOrder(['document_number' => 'PO-EVT-001']);

    $event = new ProductionOrderStatusChanged(
        $order,
        ProductionOrderStatus::FIRM_PLANNED,
        ProductionOrderStatus::RELEASED
    );

    expect($event->order->document_number)->toBe('PO-EVT-001')
        ->and($event->oldStatus)->toBe(ProductionOrderStatus::FIRM_PLANNED)
        ->and($event->newStatus)->toBe(ProductionOrderStatus::RELEASED);
});

test('production order refresh recursively explodes nested bom lines with hierarchy metadata', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $generalProductPostingGroup = GeneralProductPostingGroup::create([
        'code' => 'RM-NEST',
        'description' => 'Raw Materials Nested',
    ]);
    $inventoryPostingGroup = InventoryPostingGroup::create([
        'code' => 'MAIN-NEST',
        'description' => 'Main Inventory Nested',
    ]);

    $mainItem = Item::create([
        'item_code' => 'FG-NEST',
        'description' => 'Nested FG',
        'unit_cost' => 20,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
    ]);

    $packagingItem = Item::create([
        'item_code' => 'PACK-TRAY',
        'description' => 'Paper Tray',
        'unit_cost' => 1,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
    ]);

    $rawItem = Item::create([
        'item_code' => 'RAW-PIECE',
        'description' => 'Raw Piece',
        'unit_cost' => 0.25,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
    ]);

    $subBom = ProductionBom::create([
        'code' => 'BOM-PACK',
        'description' => 'Pack BOM',
    ]);

    $subBom->lines()->create([
        'line_number' => 10000,
        'type' => ProductionBomLine::TYPE_ITEM,
        'item_id' => $rawItem->id,
        'description' => 'Raw Piece',
        'quantity_per' => 12,
        'scrap_percent' => 0,
    ]);

    $subBom->lines()->create([
        'line_number' => 20000,
        'type' => ProductionBomLine::TYPE_ITEM,
        'item_id' => $packagingItem->id,
        'description' => 'Paper Tray',
        'quantity_per' => 1,
        'scrap_percent' => 0,
    ]);

    $mainBom = ProductionBom::create([
        'code' => 'BOM-CARTON',
        'description' => 'Carton BOM',
        'item_id' => $mainItem->id,
    ]);

    $mainBom->lines()->create([
        'line_number' => 10000,
        'type' => ProductionBomLine::TYPE_PRODUCTION_BOM,
        'production_bom_id_related' => $subBom->id,
        'description' => 'Pack Level',
        'quantity_per' => 24,
        'scrap_percent' => 0,
    ]);

    $location = Location::factory()->create(['code' => 'MAIN']);

    $order = ProductionOrder::create([
        'document_number' => 'PO-NEST-001',
        'status' => ProductionOrderStatus::PLANNED,
        'item_id' => $mainItem->id,
        'quantity' => 1,
        'quantity_base' => 1,
        'production_bom_id' => $mainBom->id,
        'flushing_method' => 'MANUAL',
        'location_code' => $location->code,
    ]);

    app(ProductionOrderService::class)->refresh($order);
    $order->refresh();

    expect($order->components)->toHaveCount(2);

    $rawComponent = $order->components->firstWhere('item_id', $rawItem->id);
    $packagingComponent = $order->components->firstWhere('item_id', $packagingItem->id);

    expect((float) $rawComponent->expected_quantity)->toBe(288.0)
        ->and((float) $rawComponent->quantity_per)->toBe(288.0)
        ->and((int) $rawComponent->bom_level)->toBe(2)
        ->and($rawComponent->source_bom_code)->toBe('BOM-PACK')
        ->and($rawComponent->bom_path)->toBe('BOM-CARTON > BOM-PACK');

    expect((float) $packagingComponent->expected_quantity)->toBe(24.0)
        ->and((float) $packagingComponent->quantity_per)->toBe(24.0)
        ->and((int) $packagingComponent->bom_level)->toBe(2)
        ->and($packagingComponent->source_bom_code)->toBe('BOM-PACK');
});

test('production order refresh fails fast on circular nested boms', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $group = GeneralProductPostingGroup::create([
        'code' => 'RM-CIRC',
        'description' => 'Raw Materials Circular',
    ]);
    $inventoryGroup = InventoryPostingGroup::create([
        'code' => 'MAIN-CIRC',
        'description' => 'Main Inventory Circular',
    ]);

    $mainItem = Item::create([
        'item_code' => 'FG-CIRC',
        'description' => 'Circular FG',
        'unit_cost' => 10,
        'general_product_posting_group_id' => $group->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
    ]);

    $bomA = ProductionBom::create(['code' => 'BOM-A', 'description' => 'A']);
    $bomB = ProductionBom::create(['code' => 'BOM-B', 'description' => 'B']);

    $bomA->lines()->create([
        'line_number' => 10000,
        'type' => ProductionBomLine::TYPE_PRODUCTION_BOM,
        'production_bom_id_related' => $bomB->id,
        'quantity_per' => 1,
    ]);

    $bomB->lines()->create([
        'line_number' => 10000,
        'type' => ProductionBomLine::TYPE_PRODUCTION_BOM,
        'production_bom_id_related' => $bomA->id,
        'quantity_per' => 1,
    ]);

    $location = Location::factory()->create(['code' => 'MAIN']);
    $order = ProductionOrder::create([
        'document_number' => 'PO-CIRC-001',
        'status' => ProductionOrderStatus::PLANNED,
        'item_id' => $mainItem->id,
        'quantity' => 1,
        'quantity_base' => 1,
        'production_bom_id' => $bomA->id,
        'location_code' => $location->code,
    ]);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('Circular BOM detected');

    app(ProductionOrderService::class)->refresh($order);
});

test('production order refresh fails fast when sub bom line is missing related bom reference', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $group = GeneralProductPostingGroup::create([
        'code' => 'RM-INV',
        'description' => 'Raw Materials Invalid',
    ]);
    $inventoryGroup = InventoryPostingGroup::create([
        'code' => 'MAIN-INV',
        'description' => 'Main Inventory Invalid',
    ]);

    $mainItem = Item::create([
        'item_code' => 'FG-INV',
        'description' => 'Invalid FG',
        'unit_cost' => 10,
        'general_product_posting_group_id' => $group->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
    ]);

    $bom = ProductionBom::create([
        'code' => 'BOM-INV',
        'description' => 'Invalid BOM',
    ]);

    $bom->lines()->create([
        'line_number' => 10000,
        'type' => ProductionBomLine::TYPE_PRODUCTION_BOM,
        'production_bom_id_related' => null,
        'quantity_per' => 1,
    ]);

    $location = Location::factory()->create(['code' => 'MAIN']);
    $order = ProductionOrder::create([
        'document_number' => 'PO-INV-001',
        'status' => ProductionOrderStatus::PLANNED,
        'item_id' => $mainItem->id,
        'quantity' => 1,
        'quantity_base' => 1,
        'production_bom_id' => $bom->id,
        'location_code' => $location->code,
    ]);

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('PRODUCTION_BOM type must reference a sub BOM');

    app(ProductionOrderService::class)->refresh($order);
});
