<?php

use App\Enums\ItemLedgerEntryType;
use App\Enums\ProductionOrderStatus;
use App\Models\GeneralProductPostingGroup;
use App\Models\InventoryPostingGroup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\User;
use App\Services\Manufacturing\ProductionOrderService;
use App\Services\NumberSeriesService;
use App\Services\Warehouse\BinAvailabilityService;
use App\Services\Warehouse\PickWorksheetService;
use App\Services\Warehouse\WarehouseReservationService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('release validation aggregates inventory demand across duplicate components', function () {
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

    $finishedGood = Item::create([
        'item_code' => 'FG-RLS-001',
        'description' => 'Finished Good',
        'unit_cost' => 10,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
    ]);
    $componentItem = Item::create([
        'item_code' => 'RM-RLS-001',
        'description' => 'Raw Material',
        'unit_cost' => 5,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
    ]);
    $location = Location::factory()->create(['code' => 'MAIN']);

    ItemLedgerEntry::create([
        'entry_type' => ItemLedgerEntryType::PURCHASE,
        'item_id' => $componentItem->id,
        'location_id' => $location->id,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
        'quantity' => 10,
        'remaining_quantity' => 10,
        'open' => true,
        'posting_date' => now(),
        'document_number' => 'INIT-RLS-001',
        'document_line_number' => 10000,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::create([
        'document_number' => 'PO-RLS-001',
        'status' => ProductionOrderStatus::FIRM_PLANNED,
        'item_id' => $finishedGood->id,
        'quantity' => 1,
        'quantity_base' => 1,
        'location_code' => $location->code,
        'flushing_method' => 'MANUAL',
    ]);

    $order->components()->create([
        'line_number' => 10000,
        'item_id' => $componentItem->id,
        'description' => 'Component A',
        'unit_of_measure_code' => 'PCS',
        'quantity_per' => 1,
        'expected_quantity' => 6,
        'expected_quantity_base' => 6,
        'remaining_quantity' => 0,
        'flushing_method' => 'MANUAL',
        'location_code' => $location->code,
    ]);

    $order->components()->create([
        'line_number' => 20000,
        'item_id' => $componentItem->id,
        'description' => 'Component B',
        'unit_of_measure_code' => 'PCS',
        'quantity_per' => 1,
        'expected_quantity' => 6,
        'expected_quantity_base' => 6,
        'remaining_quantity' => 0,
        'flushing_method' => 'MANUAL',
        'location_code' => $location->code,
    ]);

    $service = app(ProductionOrderService::class);

    expect(fn () => $service->release($order->fresh(), $user->id))
        ->toThrow(Exception::class, 'Insufficient inventory');
});

test('pick creation only processes components with outstanding remaining quantity', function () {
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

    $order = ProductionOrder::create([
        'document_number' => 'PO-PICK-001',
        'status' => ProductionOrderStatus::RELEASED,
        'item_id' => Item::create([
            'item_code' => 'FG-PICK-001',
            'description' => 'Pick Finished Good',
            'unit_cost' => 10,
            'general_product_posting_group_id' => $generalProductPostingGroup->id,
            'inventory_posting_group_id' => $inventoryPostingGroup->id,
        ])->id,
        'quantity' => 1,
        'quantity_base' => 1,
        'location_code' => Location::factory()->create(['code' => 'MAIN'])->code,
        'flushing_method' => 'MANUAL',
    ]);

    $eligibleComponent = $order->components()->create([
        'line_number' => 10000,
        'item_id' => Item::create([
            'item_code' => 'RM-PICK-001',
            'description' => 'Pick Component Eligible',
            'unit_cost' => 2,
            'general_product_posting_group_id' => $generalProductPostingGroup->id,
            'inventory_posting_group_id' => $inventoryPostingGroup->id,
        ])->id,
        'description' => 'Eligible component',
        'unit_of_measure_code' => 'PCS',
        'quantity_per' => 1,
        'expected_quantity' => 5,
        'expected_quantity_base' => 5,
        'remaining_quantity' => 5,
        'flushing_method' => 'MANUAL',
        'location_code' => 'MAIN',
    ]);

    $order->components()->create([
        'line_number' => 20000,
        'item_id' => Item::create([
            'item_code' => 'RM-PICK-002',
            'description' => 'Pick Component Ineligible',
            'unit_cost' => 2,
            'general_product_posting_group_id' => $generalProductPostingGroup->id,
            'inventory_posting_group_id' => $inventoryPostingGroup->id,
        ])->id,
        'description' => 'Ineligible component',
        'unit_of_measure_code' => 'PCS',
        'quantity_per' => 1,
        'expected_quantity' => 5,
        'expected_quantity_base' => 5,
        'remaining_quantity' => 0,
        'flushing_method' => 'MANUAL',
        'location_code' => 'MAIN',
    ]);

    $numberSeriesService = Mockery::mock(NumberSeriesService::class);
    $binAvailabilityService = Mockery::mock(BinAvailabilityService::class);
    $reservationService = Mockery::mock(WarehouseReservationService::class);

    $pickService = Mockery::mock(PickWorksheetService::class, [
        $numberSeriesService,
        $binAvailabilityService,
        $reservationService,
    ])->makePartial();

    $pickService->shouldReceive('createPickFromProductionOrder')
        ->once()
        ->with(Mockery::on(fn ($component) => $component->id === $eligibleComponent->id))
        ->andReturn(null);

    $picks = $pickService->createPicksForProductionOrder($order->fresh());

    expect($picks)->toHaveCount(0);
});
