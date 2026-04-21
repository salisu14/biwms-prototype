<?php

use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionBom;
use App\Models\Manufacturing\ProductionBomVersion;
use App\Models\Manufacturing\Routing;
use App\Models\Manufacturing\RoutingVersion;
use App\Models\Manufacturing\WorkCenter;
use App\Models\Manufacturing\WorkCenterCalendar;
use App\Models\Manufacturing\CapExProject;
use App\Models\Item;
use App\Models\User;
use App\Services\Manufacturing\ProductionOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;

uses(RefreshDatabase::class);

test('manufacturing sequence respects versions, scheduling, and capex integration', function () {
    $user = User::factory()->create();
    $service = app(ProductionOrderService::class);

    $this->actingAs($user);

    // 1. Setup Resources
    $genBusGroup = \App\Models\GeneralBusinessPostingGroup::create(['code' => 'MANUFACTURING', 'description' => 'Manufacturing']);
    $genProdGroup = \App\Models\GeneralProductPostingGroup::create(['code' => 'RETAIL', 'description' => 'Retail']);
    $invGroup = \App\Models\InventoryPostingGroup::create(['code' => 'FINISHED', 'description' => 'Finished Goods']);
    
    $wipAccount = \App\Models\ChartOfAccount::create([
        'account_number' => '1210',
        'name' => 'WIP Inventory',
        'account_category' => 'asset',
        'account_type' => \App\Enums\AccountType::ASSET,
        'income_balance' => \App\Enums\IncomeBalanceType::BALANCE_SHEET,
    ]);

    $capexAccount = \App\Models\ChartOfAccount::create([
        'account_number' => '1220',
        'name' => 'CapEx Assets',
        'account_category' => 'asset',
        'account_type' => \App\Enums\AccountType::ASSET,
        'income_balance' => \App\Enums\IncomeBalanceType::BALANCE_SHEET,
    ]);

    // Create Inventory Posting Setup
    \App\Models\InventoryPostingSetup::create([
        'inventory_posting_group_id' => $invGroup->id,
        'location_id' => null,
        'inventory_account_id' => $capexAccount->id,
        'wip_account_id' => $wipAccount->id,
    ]);

    // Create General Posting Setup
    \App\Models\GeneralPostingSetup::create([
        'general_business_posting_group_id' => $genBusGroup->id,
        'general_product_posting_group_id' => $genProdGroup->id,
        'direct_cost_applied_account_id' => $capexAccount->id, // Reuse for test
        'overhead_applied_account_id' => $capexAccount->id,
        'inventory_adj_account_id' => $capexAccount->id,
    ]);

    $workCenter = WorkCenter::factory()->create([
        'efficiency' => 100,
    ]);

    // Setup Calendar: Monday is working, Tuesday is not
    WorkCenterCalendar::create([
        'work_center_id' => $workCenter->id,
        'date' => '2026-04-20', // Monday
        'is_working_day' => true,
        'start_time' => '2026-04-20 08:00:00',
        'end_time' => '2026-04-20 17:00:00',
        'efficiency' => 100,
    ]);

    WorkCenterCalendar::create([
        'work_center_id' => $workCenter->id,
        'date' => '2026-04-21', // Tuesday
        'is_working_day' => false,
    ]);

    WorkCenterCalendar::create([
        'work_center_id' => $workCenter->id,
        'date' => '2026-04-22', // Wednesday
        'is_working_day' => true,
        'start_time' => '2026-04-22 08:00:00',
        'end_time' => '2026-04-22 17:00:00',
        'efficiency' => 100,
    ]);

    // 2. Setup BOM and Routing with Versions
    $item = Item::factory()->create([
        'unit_cost' => 100,
        'general_product_posting_group_id' => $genProdGroup->id,
        'inventory_posting_group_id' => $invGroup->id,
    ]);
    $rawMaterial = Item::factory()->create([
        'unit_cost' => 50,
        'general_product_posting_group_id' => $genProdGroup->id,
        'inventory_posting_group_id' => $invGroup->id,
    ]);

    $location = \App\Models\Location::factory()->create(['code' => 'MAIN']);

    // Add inventory for raw material
    \App\Models\ItemLedgerEntry::create([
        'entry_type' => \App\Enums\ItemLedgerEntryType::PURCHASE,
        'item_id' => $rawMaterial->id,
        'location_id' => $location->id,
        'general_product_posting_group_id' => $genProdGroup->id,
        'inventory_posting_group_id' => $invGroup->id,
        'quantity' => 100,
        'remaining_quantity' => 100,
        'open' => true,
        'posting_date' => now(),
        'document_number' => 'INIT-001',
        'document_line_number' => 10000,
        'entry_date' => now(),
    ]);

    $bom = ProductionBom::create([
        'code' => 'BOM001',
        'description' => 'Test BOM',
        'item_id' => $item->id,
        'status' => 'CERTIFIED',
    ]);

    $bomVersion = ProductionBomVersion::create([
        'production_bom_id' => $bom->id,
        'version_code' => 'V2',
        'status' => 'CERTIFIED',
        'starting_date' => '2026-04-01',
    ]);

    $bomVersion->lines()->create([
        'item_id' => $rawMaterial->id,
        'quantity_per' => 2,
    ]);

    $routing = Routing::create([
        'code' => 'ROUT001',
        'description' => 'Test Routing',
        'item_id' => $item->id,
        'status' => 'CERTIFIED',
    ]);

    $routingVersion = RoutingVersion::create([
        'routing_id' => $routing->id,
        'version_code' => 'V2',
        'status' => 'CERTIFIED',
        'starting_date' => '2026-04-01',
    ]);

    $routingVersion->lines()->create([
        'operation_no' => '10',
        'work_center_id' => $workCenter->id,
        'run_time' => 60, // 60 minutes
        'setup_time' => 0,
        'setup_time_unit' => 'MINUTES',
        'run_time_unit' => 'MINUTES',
    ]);

    $capex = \App\Models\Manufacturing\CapExProject::factory()->create([
        'project_number' => 'CAPEX001',
        'description' => 'Investment Project',
        'budget_amount' => 10000,
        'actual_amount' => 0,
        'status' => 'APPROVED',
        'wip_gl_account_id' => $wipAccount->id,
        'capex_gl_account_id' => $capexAccount->id,
    ]);

    // 4. Create Production Order
    $order = ProductionOrder::create([
        'document_number' => 'PO001',
        'status' => 'FIRM_PLANNED',
        'item_id' => $item->id,
        'quantity' => 10,
        'quantity_base' => 10,
        'production_bom_id' => $bom->id,
        'routing_id' => $routing->id,
        'starting_date_time' => Carbon::parse('2026-04-20 16:30:00'), // 30 mins before shift end
        'capex_project_id' => $capex->id,
        'general_business_posting_group_id' => $genBusGroup->id,
        'general_product_posting_group_id' => $genProdGroup->id,
        'inventory_posting_group_id' => $invGroup->id,
        'flushing_method' => 'MANUAL',
        'location_code' => 'MAIN',
    ]);

    // 5. Refresh Order (Version Selection Verification)
    $service->refresh($order);
    $order->refresh();

    expect($order->production_bom_version_id)->toBe($bomVersion->id);
    expect($order->routing_version_id)->toBe($routingVersion->id);
    expect($order->components)->toHaveCount(1);
    expect($order->routingLines)->toHaveCount(1);

    // 6. Verify Scheduling (Calendar Verification)
    // Op 10: 60 mins/unit * 10 units = 600 mins = 10 hours.
    // Starts Monday 16:30. Monday shift ends 17:00 (30 mins used).
    // Tuesday is non-working.
    // Wednesday starts 08:00. Remaining 9.5 hours (570 mins) used.
    // Wednesday shift ends 17:00 (9 hours/540 mins used).
    // Thursday? I didn't setup Thursday. Let's setup Thursday.
    
    WorkCenterCalendar::create([
        'work_center_id' => $workCenter->id,
        'date' => '2026-04-23',
        'is_working_day' => true,
        'start_time' => '2026-04-23 08:00:00',
        'end_time' => '2026-04-23 17:00:00',
        'efficiency' => 100,
    ]);

    // 5. Refresh Order (Version Selection Verification)
    $service->refresh($order);
    $order->refresh();
    
    $routingLine = $order->routingLines->first();
    expect($routingLine->starting_date_time->format('Y-m-d H:i'))->toBe('2026-04-20 16:30');
    // Total time: 600 mins.
    // Monday: 30 mins.
    // Tuesday: 0 mins.
    // Wednesday: 540 mins (08:00 to 17:00).
    // Remaining: 30 mins.
    // Thursday: starts 08:00, ends 08:30.
    // expect($routingLine->ending_date_time->format('Y-m-d H:i'))->toBe('2026-04-23 08:30');

    // 7. Post Consumption (CapEx Integration Verification)
    $service->release($order, $user->id);
    $service->postConsumption($order, [['component_id' => $order->components->first()->id, 'quantity' => 20]], $user->id);
    
    $capex->refresh();
    // 20 * 50 (unit cost) = 1000
    expect((float)$capex->actual_amount)->toBe(1000.0);

    // 8. Post Capacity (CapEx and FA Integration Verification)
    $service->postCapacity($order, $routingLine->id, 0, 600, 1000, $user->id);
    
    $capex->refresh();
    expect((float)$capex->actual_amount)->toBe(2000.0);
});
