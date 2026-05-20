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
