<?php

use App\Enums\AccountType;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemLedgerEntryType;
use App\Enums\JournalBatchStatus;
use App\Enums\ProductionJournalEntryType;
use App\Enums\ProductionOrderStatus;
use App\Models\CapacityLedgerEntry;
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
use App\Models\Manufacturing\WorkCenter;
use App\Models\NumberSeries;
use App\Models\ProductionJournalBatch;
use App\Models\ProductionJournalLine;
use App\Models\ProductionJournalTemplate;
use App\Models\User;
use App\Services\Posting\ProductionJournalPostingRoutine;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('production journal posting routine validates and posts consumption, capacity, output, and scrap correctly', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    // 1. Setup Financial & Posting Groups
    $genBusGroup = GeneralBusinessPostingGroup::create([
        'code' => 'MANUFACTURING',
        'description' => 'Manufacturing',
    ]);
    $genProdGroup = GeneralProductPostingGroup::create([
        'code' => 'RETAIL',
        'description' => 'Retail',
    ]);
    $invGroup = InventoryPostingGroup::create([
        'code' => 'FINISHED',
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
        'account_number' => '1200',
        'name' => 'Inventory',
        'account_category' => 'asset',
        'account_type' => AccountType::ASSET,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);

    $cogsAccount = ChartOfAccount::create([
        'account_number' => '5100',
        'name' => 'COGS',
        'account_category' => 'direct_expense',
        'account_type' => AccountType::EXPENSE,
        'income_balance' => IncomeBalanceType::INCOME_STATEMENT,
    ]);

    InventoryPostingSetup::create([
        'inventory_posting_group_id' => $invGroup->id,
        'location_id' => null,
        'inventory_account_id' => $inventoryAccount->id,
        'wip_account_id' => $wipAccount->id,
    ]);

    GeneralPostingSetup::create([
        'general_business_posting_group_id' => $genBusGroup->id,
        'general_product_posting_group_id' => $genProdGroup->id,
        'direct_cost_applied_account_id' => $cogsAccount->id,
        'overhead_applied_account_id' => $cogsAccount->id,
        'inventory_adj_account_id' => $cogsAccount->id,
    ]);

    $location = Location::factory()->create(['code' => 'MAIN']);

    // 2. Setup Items & Resources
    $fgItem = Item::create([
        'item_code' => 'FG-ITEM',
        'description' => 'Finished Good Item',
        'unit_cost' => 100,
        'general_product_posting_group_id' => $genProdGroup->id,
        'inventory_posting_group_id' => $invGroup->id,
    ]);

    $rawMaterial = Item::create([
        'item_code' => 'RM-ITEM',
        'description' => 'Raw Material Item',
        'unit_cost' => 20,
        'general_product_posting_group_id' => $genProdGroup->id,
        'inventory_posting_group_id' => $invGroup->id,
    ]);

    $workCenter = WorkCenter::factory()->create([
        'direct_unit_cost' => 50,
        'overhead_rate' => 10,
    ]);

    // 3. Create Production Order (Released status)
    $order = ProductionOrder::create([
        'document_number' => 'PO-JNL-001',
        'status' => ProductionOrderStatus::RELEASED,
        'item_id' => $fgItem->id,
        'quantity' => 10,
        'quantity_base' => 10,
        'starting_date_time' => now(),
        'general_business_posting_group_id' => $genBusGroup->id,
        'general_product_posting_group_id' => $genProdGroup->id,
        'inventory_posting_group_id' => $invGroup->id,
        'flushing_method' => 'MANUAL',
        'location_code' => 'MAIN',
    ]);

    // Add component and line to order for testing updates
    $component = $order->components()->create([
        'line_number' => 10000,
        'item_id' => $rawMaterial->id,
        'description' => 'Raw Material Component',
        'unit_of_measure_code' => 'PCS',
        'quantity_per' => 2,
        'expected_quantity' => 20,
        'expected_quantity_base' => 20,
        'remaining_quantity' => 20,
        'consumed_quantity' => 0,
    ]);

    $order->lines()->create([
        'line_number' => 10000,
        'item_id' => $fgItem->id,
        'description' => 'Finished Good Line',
        'quantity' => 10,
        'quantity_base' => 10,
        'unit_of_measure_code' => 'PCS',
    ]);

    // 4. Create Journal Template and Batch
    $numberSeries = NumberSeries::create([
        'code' => 'PROD-JNL',
        'description' => 'Production Journal',
        'prefix' => 'PJ',
        'starting_number' => 1,
        'current_number' => 0,
        'is_active' => true,
    ]);

    $template = ProductionJournalTemplate::create([
        'name' => 'PROD-JNL',
        'description' => 'Production Journal Template',
        'number_series_id' => $numberSeries->id,
        'absorb_overhead' => true,
    ]);

    $batch = ProductionJournalBatch::create([
        'template_id' => $template->id,
        'name' => 'DEFAULT',
        'description' => 'Default Batch',
        'status' => JournalBatchStatus::RELEASED,
        'production_order_id' => $order->id,
    ]);

    // 5. Create Production Journal Lines for all 4 types
    $line1 = ProductionJournalLine::create([
        'batch_id' => $batch->id,
        'line_no' => 10000,
        'posting_date' => now(),
        'entry_type' => ProductionJournalEntryType::Consumption,
        'production_order_id' => $order->id,
        'production_order_no' => $order->document_number,
        'item_id' => $rawMaterial->id,
        'quantity' => 5,
        'quantity_base' => 5,
        'unit_of_measure_code' => 'PCS',
        'unit_cost' => 20,
        'location_id' => $location->id,
        'created_by' => $user->id,
    ]);

    $line2 = ProductionJournalLine::create([
        'batch_id' => $batch->id,
        'line_no' => 20000,
        'posting_date' => now(),
        'entry_type' => ProductionJournalEntryType::Capacity,
        'production_order_id' => $order->id,
        'production_order_no' => $order->document_number,
        'work_center_id' => $workCenter->id,
        'setup_time' => 1,
        'run_time' => 2,
        'output_quantity' => 2,
        'quantity' => 0,
        'quantity_base' => 0,
        'unit_of_measure_code' => 'PCS',
        'created_by' => $user->id,
    ]);

    $line3 = ProductionJournalLine::create([
        'batch_id' => $batch->id,
        'line_no' => 30000,
        'posting_date' => now(),
        'entry_type' => ProductionJournalEntryType::Output,
        'production_order_id' => $order->id,
        'production_order_no' => $order->document_number,
        'item_id' => $fgItem->id,
        'quantity' => 2,
        'quantity_base' => 2,
        'unit_of_measure_code' => 'PCS',
        'unit_cost' => 100,
        'location_id' => $location->id,
        'created_by' => $user->id,
    ]);

    $line4 = ProductionJournalLine::create([
        'batch_id' => $batch->id,
        'line_no' => 40000,
        'posting_date' => now(),
        'entry_type' => ProductionJournalEntryType::Scrap,
        'production_order_id' => $order->id,
        'production_order_no' => $order->document_number,
        'item_id' => $rawMaterial->id,
        'quantity' => 1,
        'quantity_base' => 1,
        'unit_of_measure_code' => 'PCS',
        'unit_cost' => 20,
        'location_id' => $location->id,
        'created_by' => $user->id,
    ]);

    // 6. Post using ProductionJournalPostingRoutine
    $routine = app(ProductionJournalPostingRoutine::class);
    $result = $routine->post($batch);

    // Verify posting success
    expect($result->success)->toBeTrue();
    expect($result->errors)->toBeEmpty();

    // 7. Verify database updates
    // Check Item Ledger Entries (Consumption and Output and Scrap)
    $consumptionEntries = ItemLedgerEntry::where('entry_type', ItemLedgerEntryType::CONSUMPTION)->get();
    expect($consumptionEntries)->toHaveCount(2); // 1 for consumption, 1 for scrap

    $scrapEntry = $consumptionEntries->firstWhere('quantity', -1.0);
    expect($scrapEntry)->not->toBeNull()
        ->and((float) $scrapEntry->cost_amount_actual)->toBe(20.0);

    $consumptionEntry = $consumptionEntries->firstWhere('quantity', -5.0);
    expect($consumptionEntry)->not->toBeNull()
        ->and((float) $consumptionEntry->cost_amount_actual)->toBe(100.0);

    $outputEntry = ItemLedgerEntry::where('entry_type', ItemLedgerEntryType::OUTPUT)->first();
    expect($outputEntry)->not->toBeNull()
        ->and($outputEntry->item_id)->toBe($fgItem->id)
        ->and((float) $outputEntry->quantity)->toBe(2.0)
        ->and((float) $outputEntry->cost_amount_actual)->toBe(200.0);

    // Check Capacity Ledger Entry
    $capacityEntry = CapacityLedgerEntry::where('production_order_id', $order->id)->first();
    expect($capacityEntry)->not->toBeNull()
        ->and($capacityEntry->work_center_id)->toBe($workCenter->id)
        ->and((float) $capacityEntry->setup_time)->toBe(1.0)
        ->and((float) $capacityEntry->run_time)->toBe(2.0)
        ->and((float) $capacityEntry->direct_cost)->toBe(150.0) // (1 + 2) * 50
        ->and((float) $capacityEntry->overhead_cost)->toBe(30.0); // (1 + 2) * 10

    // Check Component updates
    $component->refresh();
    expect((float) $component->actual_quantity_consumed)->toBe(6.0); // 5 consumption + 1 scrap

    // Check Line updates
    $orderLine = $order->lines()->where('line_number', 10000)->first();
    expect((float) $orderLine->finished_quantity)->toBe(2.0)
        ->and((float) $orderLine->remaining_quantity)->toBe(8.0);

    // Check Batch status is posted
    $batch->refresh();
    expect($batch->status->value)->toBe('posted');
});
