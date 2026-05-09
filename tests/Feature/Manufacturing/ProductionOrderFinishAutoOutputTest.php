<?php

use App\Enums\AccountType;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemLedgerEntryType;
use App\Enums\ProductionOrderStatus;
use App\Models\ChartOfAccount;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\User;
use App\Services\Manufacturing\ProductionOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('finishing a released production order auto-posts output for remaining quantity', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $generalBusinessPostingGroup = GeneralBusinessPostingGroup::create([
        'code' => 'MANUFACTURING',
        'description' => 'Manufacturing',
    ]);
    $generalProductPostingGroup = GeneralProductPostingGroup::create([
        'code' => 'FG',
        'description' => 'Finished Goods',
    ]);
    $inventoryPostingGroup = InventoryPostingGroup::create([
        'code' => 'FG',
        'description' => 'Finished Goods',
    ]);

    $wipAccount = ChartOfAccount::create([
        'account_number' => '1210',
        'name' => 'WIP Inventory',
        'account_category' => 'asset',
        'account_type' => AccountType::ASSET,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    $inventoryAccount = ChartOfAccount::create([
        'account_number' => '1220',
        'name' => 'Finished Goods Inventory',
        'account_category' => 'asset',
        'account_type' => AccountType::ASSET,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);

    GeneralPostingSetup::create([
        'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'direct_cost_applied_account_id' => $inventoryAccount->id,
        'overhead_applied_account_id' => $inventoryAccount->id,
        'inventory_adj_account_id' => $inventoryAccount->id,
    ]);

    InventoryPostingSetup::create([
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
        'location_id' => null,
        'inventory_account_id' => $inventoryAccount->id,
        'wip_account_id' => $wipAccount->id,
    ]);

    $finishedGood = Item::create([
        'item_code' => 'FG-001',
        'description' => 'Finished Good',
        'unit_cost' => 10,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
    ]);
    $rawMaterial = Item::create([
        'item_code' => 'RM-001',
        'description' => 'Raw Material',
        'unit_cost' => 10,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
    ]);

    $location = Location::factory()->create(['code' => 'MAIN']);

    ItemLedgerEntry::create([
        'entry_type' => ItemLedgerEntryType::PURCHASE,
        'item_id' => $rawMaterial->id,
        'location_id' => $location->id,
        'quantity' => 50,
        'remaining_quantity' => 50,
        'open' => true,
        'posting_date' => now(),
        'document_number' => 'INIT-RAW-001',
        'document_line_number' => 10000,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
        'entry_date' => now(),
    ]);

    $productionOrder = ProductionOrder::create([
        'document_number' => 'PO-AUTO-001',
        'status' => ProductionOrderStatus::FIRM_PLANNED,
        'item_id' => $finishedGood->id,
        'quantity' => 5,
        'quantity_base' => 5,
        'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
        'location_code' => $location->code,
        'flushing_method' => 'BACKWARD',
        'costing_method' => 'FIFO',
    ]);

    $productionOrder->components()->create([
        'line_number' => 10000,
        'item_id' => $rawMaterial->id,
        'description' => 'Raw Material',
        'unit_of_measure_code' => 'PCS',
        'quantity_per' => 1,
        'expected_quantity' => 5,
        'expected_quantity_base' => 5,
        'flushing_method' => 'BACKWARD',
        'location_code' => $location->code,
    ]);

    $service = app(ProductionOrderService::class);
    $service->release($productionOrder, $user->id);
    $service->finish($productionOrder->fresh(), $user->id);

    $outputEntry = ItemLedgerEntry::query()
        ->where('source_type', ProductionOrder::class)
        ->where('source_id', $productionOrder->id)
        ->where('entry_type', ItemLedgerEntryType::OUTPUT)
        ->first();

    expect($outputEntry)->not()->toBeNull();
    expect((float) $outputEntry->quantity)->toBe(5.0);
    expect((float) $outputEntry->remaining_quantity)->toBe(5.0);
    expect($outputEntry->location_id)->toBe($location->id);
    expect($productionOrder->fresh()->status)->toBe(ProductionOrderStatus::FINISHED);
    expect((float) $productionOrder->fresh()->remaining_quantity)->toBe(0.0);
});
