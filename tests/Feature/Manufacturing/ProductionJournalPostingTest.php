<?php

use App\Enums\AccountType;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemLedgerEntryType;
use App\Enums\JournalBatchStatus;
use App\Enums\ProductionJournalEntryType;
use App\Enums\ProductionOrderStatus;
use App\Filament\Resources\ProductionOrders\Actions\ProductionOrderActions;
use App\Models\CapacityLedgerEntry;
use App\Models\ChartOfAccount;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\GlEntry;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\WorkCenter;
use App\Models\NumberSeries;
use App\Models\Permission;
use App\Models\ProductionJournalBatch;
use App\Models\ProductionJournalLine;
use App\Models\ProductionJournalTemplate;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\ValueEntry;
use App\Services\Manufacturing\ProductionOrderService;
use App\Services\Posting\ProductionJournalPostingRoutine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

test('production journal posting routine validates and posts consumption, capacity, output, and scrap correctly', function () {
    $user = User::factory()->create();
    grantProductionPostingPermissions($user);
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

it('posts production output entered in order uom as base quantity', function () {
    $user = User::factory()->create();
    grantProductionPostingPermissions($user);
    $this->actingAs($user);

    $location = Location::factory()->create(['code' => 'MAIN']);
    $baseUom = UnitOfMeasure::query()->create([
        'uom_code' => 'PCS',
        'description' => 'Pieces',
        'is_base_uom' => true,
    ]);
    $orderUom = UnitOfMeasure::query()->create([
        'uom_code' => 'CT',
        'description' => 'Carton',
        'is_base_uom' => false,
    ]);

    $finishedGood = Item::factory()->create([
        'item_code' => 'FG-CT',
        'description' => 'Carton Finished Good',
        'unit_cost' => 3,
        'base_uom_id' => $baseUom->id,
    ]);

    DB::table('item_uom_assignments')->insert([
        [
            'item_id' => $finishedGood->id,
            'uom_id' => $baseUom->id,
            'uom_type' => 'BASE',
            'conversion_factor' => 1,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'item_id' => $finishedGood->id,
            'uom_id' => $orderUom->id,
            'uom_type' => 'MANUFACTURING',
            'conversion_factor' => 288,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $order = ProductionOrder::query()->create([
        'document_number' => 'PO-UOM-001',
        'status' => ProductionOrderStatus::RELEASED,
        'item_id' => $finishedGood->id,
        'description' => 'One carton output',
        'quantity' => 1,
        'quantity_base' => 288,
        'unit_of_measure_code' => 'CT',
        'starting_date_time' => now(),
        'general_product_posting_group_id' => $finishedGood->general_product_posting_group_id,
        'inventory_posting_group_id' => $finishedGood->inventory_posting_group_id,
        'cost_rollup' => 3,
        'flushing_method' => 'MANUAL',
        'location_code' => $location->code,
    ]);

    $order->lines()->create([
        'line_number' => 10000,
        'item_id' => $finishedGood->id,
        'description' => 'Carton output line',
        'quantity' => 1,
        'quantity_base' => 288,
        'unit_of_measure_code' => 'CT',
        'location_code' => $location->code,
    ]);

    expect($order->quantityInOrderUom())->toBe(1.0)
        ->and($order->orderUomCode())->toBe('CT')
        ->and(ProductionOrderActions::postOutputDefaultQuantity($order))->toBe(1.0)
        ->and(ProductionOrderActions::postOutputHelperText($order))->toBe('Quantity to post in CT. Base equivalent: 288 PCS.');

    $quantityInOrderUom = 1.0;
    $quantityBase = ProductionOrderActions::convertOrderUomToBase($order, $quantityInOrderUom);

    expect($quantityBase)->toBe(288.0);

    app(ProductionOrderService::class)->postOutput($order, $quantityBase, $user->id);

    $outputEntry = ItemLedgerEntry::query()
        ->where('entry_type', ItemLedgerEntryType::OUTPUT)
        ->where('document_number', 'PO-UOM-001')
        ->first();

    expect($outputEntry)->not->toBeNull()
        ->and((float) $outputEntry->quantity)->toBe(288.0)
        ->and((float) $outputEntry->cost_amount_actual)->toBe(864.0);

    $valueEntry = ValueEntry::query()
        ->where('item_ledger_entry_no', $outputEntry->entry_number)
        ->where('document_no', 'PO-UOM-001')
        ->first();

    expect($valueEntry)->not->toBeNull()
        ->and((float) $valueEntry->quantity)->toBe(288.0)
        ->and((float) $valueEntry->cost_amount_actual)->toBe((float) $outputEntry->cost_amount_actual)
        ->and((float) $valueEntry->unit_cost)->toBe(3.0)
        ->and($valueEntry->item_ledger_entry_no)->toBe($outputEntry->entry_number)
        ->and($valueEntry->production_order_no)->toBe('PO-UOM-001')
        ->and($valueEntry->production_order_line_no)->toBe('10000')
        ->and($valueEntry->prod_order_line_item_no)->toBe('FG-CT')
        ->and($valueEntry->document_no)->toBe('PO-UOM-001')
        ->and($valueEntry->document_line_no)->toBe(10000)
        ->and($valueEntry->item_no)->toBe('FG-CT')
        ->and($valueEntry->location_code)->toBe('MAIN')
        ->and($valueEntry->itemLedgerEntry->is($outputEntry))->toBeTrue()
        ->and($valueEntry->productionOrder->is($order))->toBeTrue();

    $overproductionQuantityBase = ProductionOrderActions::convertOrderUomToBase($order->fresh(), 1.0);

    expect(fn () => app(ProductionOrderService::class)->postOutput($order->fresh(), $overproductionQuantityBase, $user->id))
        ->toThrow(Exception::class, 'Cannot overproduce');
});

it('updates output value entry costs and marks the order posted when finishing', function () {
    $user = User::factory()->create();
    grantProductionPostingPermissions($user);
    $this->actingAs($user);

    $inventoryAccount = ChartOfAccount::create([
        'account_number' => '1200-FINISH',
        'name' => 'Finished Goods Inventory',
        'account_category' => 'asset',
        'account_type' => AccountType::ASSET,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    $wipAccount = ChartOfAccount::create([
        'account_number' => '1210-FINISH',
        'name' => 'WIP Inventory',
        'account_category' => 'asset',
        'account_type' => AccountType::ASSET,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    $genProdGroup = GeneralProductPostingGroup::create([
        'code' => 'FINISH',
        'description' => 'Finished Goods',
    ]);
    $invGroup = InventoryPostingGroup::create([
        'code' => 'FINISH',
        'description' => 'Finished Goods',
    ]);
    $location = Location::factory()->create(['code' => 'MAIN']);

    InventoryPostingSetup::create([
        'inventory_posting_group_id' => $invGroup->id,
        'location_id' => $location->id,
        'inventory_account_id' => $inventoryAccount->id,
        'wip_account_id' => $wipAccount->id,
    ]);

    $baseUom = UnitOfMeasure::query()->create([
        'uom_code' => 'PCS',
        'description' => 'Pieces',
        'is_base_uom' => true,
    ]);
    $orderUom = UnitOfMeasure::query()->create([
        'uom_code' => 'CT',
        'description' => 'Carton',
        'is_base_uom' => false,
    ]);

    $finishedGood = Item::factory()->create([
        'item_code' => 'FG-FINISH',
        'description' => 'Finished Cost Good',
        'unit_cost' => 0,
        'base_uom_id' => $baseUom->id,
        'general_product_posting_group_id' => $genProdGroup->id,
        'inventory_posting_group_id' => $invGroup->id,
    ]);
    $rawMaterial = Item::factory()->create([
        'item_code' => 'RM-FINISH',
        'description' => 'Raw Finish Material',
        'unit_cost' => 4.5,
        'base_uom_id' => $baseUom->id,
        'general_product_posting_group_id' => $genProdGroup->id,
        'inventory_posting_group_id' => $invGroup->id,
    ]);

    DB::table('item_uom_assignments')->insert([
        [
            'item_id' => $finishedGood->id,
            'uom_id' => $baseUom->id,
            'uom_type' => 'BASE',
            'conversion_factor' => 1,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'item_id' => $finishedGood->id,
            'uom_id' => $orderUom->id,
            'uom_type' => 'MANUFACTURING',
            'conversion_factor' => 288,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $order = ProductionOrder::query()->create([
        'document_number' => 'PO-FINISH-001',
        'status' => ProductionOrderStatus::RELEASED,
        'item_id' => $finishedGood->id,
        'description' => 'Finish with actual cost',
        'quantity' => 1,
        'quantity_base' => 288,
        'unit_of_measure_code' => 'CT',
        'starting_date_time' => now(),
        'general_product_posting_group_id' => $genProdGroup->id,
        'inventory_posting_group_id' => $invGroup->id,
        'costing_method' => 'FIFO',
        'unit_cost' => 0,
        'cost_rollup' => 0,
        'flushing_method' => 'MANUAL',
        'location_code' => $location->code,
    ]);

    $order->lines()->create([
        'line_number' => 10000,
        'item_id' => $finishedGood->id,
        'description' => 'Carton output line',
        'quantity' => 1,
        'quantity_base' => 288,
        'unit_of_measure_code' => 'CT',
        'location_code' => $location->code,
    ]);

    app(ProductionOrderService::class)->postOutput($order, 288.0, $user->id);

    $outputEntry = ItemLedgerEntry::query()
        ->where('entry_type', ItemLedgerEntryType::OUTPUT)
        ->where('document_number', 'PO-FINISH-001')
        ->firstOrFail();
    $outputValueEntry = ValueEntry::query()
        ->where('item_ledger_entry_no', $outputEntry->entry_number)
        ->firstOrFail();

    expect((float) $outputValueEntry->cost_amount_actual)->toBe(0.0);

    ItemLedgerEntry::create([
        'entry_type' => ItemLedgerEntryType::CONSUMPTION,
        'item_id' => $rawMaterial->id,
        'quantity' => -288,
        'remaining_quantity' => 0,
        'open' => false,
        'posting_date' => now(),
        'document_number' => $order->document_number,
        'document_line_number' => 20000,
        'source_id' => $order->id,
        'source_type' => ProductionOrder::class,
        'location_id' => $location->id,
        'cost_amount_actual' => 1296,
        'dimensions' => $order->dimension_set_id,
        'general_product_posting_group_id' => $rawMaterial->general_product_posting_group_id,
        'inventory_posting_group_id' => $rawMaterial->inventory_posting_group_id,
        'entry_date' => now(),
    ]);

    app(ProductionOrderService::class)->finish($order->fresh(), $user->id);

    $order->refresh();
    $outputEntry->refresh();
    $outputValueEntry->refresh();

    expect($order->status)->toBe(ProductionOrderStatus::FINISHED)
        ->and($order->posted)->toBeTrue()
        ->and($order->posted_at)->not->toBeNull()
        ->and($order->posted_by)->toBe($user->id)
        ->and((float) $outputEntry->quantity)->toBe(288.0)
        ->and((float) $outputEntry->cost_amount_actual)->toBe(1296.0)
        ->and((float) $outputValueEntry->quantity)->toBe(288.0)
        ->and((float) $outputValueEntry->cost_amount_actual)->toBe(1296.0)
        ->and((float) $outputValueEntry->unit_cost)->toBe(4.5)
        ->and($outputValueEntry->production_order_no)->toBe('PO-FINISH-001')
        ->and($outputValueEntry->production_order_line_no)->toBe('10000')
        ->and($outputValueEntry->document_no)->toBe('PO-FINISH-001')
        ->and($outputValueEntry->document_line_no)->toBe(10000)
        ->and($outputValueEntry->item_no)->toBe('FG-FINISH')
        ->and($outputValueEntry->location_code)->toBe('MAIN')
        ->and(ValueEntry::query()->where('item_ledger_entry_no', $outputEntry->entry_number)->count())->toBe(1)
        ->and(ItemLedgerEntry::query()
            ->where('entry_type', ItemLedgerEntryType::OUTPUT)
            ->where('document_number', 'PO-FINISH-001')
            ->count())->toBe(1);

    expect(fn () => app(ProductionOrderService::class)->finish($order->fresh(), $user->id))
        ->toThrow(Exception::class, 'Production order is already finished');

    expect(ValueEntry::query()->where('item_ledger_entry_no', $outputEntry->entry_number)->count())->toBe(1)
        ->and(ItemLedgerEntry::query()
            ->where('entry_type', ItemLedgerEntryType::OUTPUT)
            ->where('document_number', 'PO-FINISH-001')
            ->count())->toBe(1);
});

it('reconciles consumption capacity wip value entries and finish gl for an order uom production order', function () {
    $user = User::factory()->create();
    grantProductionPostingPermissions($user);
    $this->actingAs($user);

    $inventoryAccount = ChartOfAccount::create([
        'account_number' => '1200-REC',
        'name' => 'Inventory Reconciliation',
        'account_category' => 'asset',
        'account_type' => AccountType::ASSET,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    $wipAccount = ChartOfAccount::create([
        'account_number' => '1210-REC',
        'name' => 'WIP Reconciliation',
        'account_category' => 'asset',
        'account_type' => AccountType::ASSET,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    $directAppliedAccount = ChartOfAccount::create([
        'account_number' => '5100-REC',
        'name' => 'Direct Applied Reconciliation',
        'account_category' => 'direct_expense',
        'account_type' => AccountType::EXPENSE,
        'income_balance' => IncomeBalanceType::INCOME_STATEMENT,
    ]);
    $overheadAppliedAccount = ChartOfAccount::create([
        'account_number' => '5200-REC',
        'name' => 'Overhead Applied Reconciliation',
        'account_category' => 'direct_expense',
        'account_type' => AccountType::EXPENSE,
        'income_balance' => IncomeBalanceType::INCOME_STATEMENT,
    ]);

    $businessGroup = GeneralBusinessPostingGroup::create([
        'code' => 'MAN-REC',
        'description' => 'Manufacturing Reconciliation',
    ]);
    $productGroup = GeneralProductPostingGroup::create([
        'code' => 'FG-REC',
        'description' => 'Finished Reconciliation',
    ]);
    $inventoryGroup = InventoryPostingGroup::create([
        'code' => 'FG-REC',
        'description' => 'Finished Reconciliation',
    ]);
    $location = Location::factory()->create(['code' => 'MAIN']);

    InventoryPostingSetup::create([
        'inventory_posting_group_id' => $inventoryGroup->id,
        'location_id' => $location->id,
        'inventory_account_id' => $inventoryAccount->id,
        'wip_account_id' => $wipAccount->id,
    ]);
    GeneralPostingSetup::create([
        'general_business_posting_group_id' => $businessGroup->id,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_adj_account_id' => $directAppliedAccount->id,
        'direct_cost_applied_account_id' => $directAppliedAccount->id,
        'overhead_applied_account_id' => $overheadAppliedAccount->id,
    ]);

    $baseUom = UnitOfMeasure::query()->create([
        'uom_code' => 'PCS',
        'description' => 'Pieces',
        'is_base_uom' => true,
    ]);
    $orderUom = UnitOfMeasure::query()->create([
        'uom_code' => 'CT',
        'description' => 'Carton',
        'is_base_uom' => false,
    ]);

    $finishedGood = Item::factory()->create([
        'item_code' => 'FG-REC',
        'description' => 'Finished Reconciliation Good',
        'unit_cost' => 0,
        'base_uom_id' => $baseUom->id,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
    ]);
    $rawMaterial = Item::factory()->create([
        'item_code' => 'RM-REC',
        'description' => 'Raw Reconciliation Material',
        'unit_cost' => 4.5,
        'base_uom_id' => $baseUom->id,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
    ]);

    DB::table('item_uom_assignments')->insert([
        [
            'item_id' => $finishedGood->id,
            'uom_id' => $baseUom->id,
            'uom_type' => 'BASE',
            'conversion_factor' => 1,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'item_id' => $finishedGood->id,
            'uom_id' => $orderUom->id,
            'uom_type' => 'MANUFACTURING',
            'conversion_factor' => 288,
            'is_default' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $workCenter = WorkCenter::factory()->create([
        'code' => 'WC-REC',
        'direct_unit_cost' => 25,
        'indirect_cost_percent' => 20,
        'overhead_rate' => 0,
    ]);

    $order = ProductionOrder::query()->create([
        'document_number' => 'PO-REC-001',
        'status' => ProductionOrderStatus::RELEASED,
        'item_id' => $finishedGood->id,
        'description' => 'Reconciliation order',
        'quantity' => 1,
        'quantity_base' => 288,
        'unit_of_measure_code' => 'CT',
        'starting_date_time' => now(),
        'general_business_posting_group_id' => $businessGroup->id,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
        'costing_method' => 'FIFO',
        'unit_cost' => 0,
        'cost_rollup' => 0,
        'flushing_method' => 'MANUAL',
        'location_code' => $location->code,
    ]);

    $component = $order->components()->create([
        'line_number' => 10000,
        'item_id' => $rawMaterial->id,
        'description' => 'Raw material',
        'unit_of_measure_code' => 'PCS',
        'quantity_per' => 288,
        'expected_quantity' => 288,
        'expected_quantity_base' => 288,
        'remaining_quantity' => 288,
        'flushing_method' => 'MANUAL',
        'location_code' => $location->code,
    ]);
    $order->lines()->create([
        'line_number' => 10000,
        'item_id' => $finishedGood->id,
        'description' => 'Finished output',
        'quantity' => 1,
        'quantity_base' => 288,
        'unit_of_measure_code' => 'CT',
        'location_code' => $location->code,
    ]);
    $routingLine = $order->routingLines()->create([
        'line_number' => 10000,
        'operation_no' => '10',
        'description' => 'Assembly',
        'work_center_id' => $workCenter->id,
        'setup_time' => 0,
        'run_time' => 10,
        'setup_time_unit' => 'MIN',
        'run_time_unit' => 'MIN',
        'status' => 'PLANNED',
    ]);

    app(ProductionOrderService::class)->postConsumption($order->fresh(), [[
        'component_id' => $component->id,
        'quantity' => 288,
    ]], $user->id);

    $component->refresh();
    $consumptionEntry = ItemLedgerEntry::query()
        ->where('entry_type', ItemLedgerEntryType::CONSUMPTION)
        ->where('document_number', 'PO-REC-001')
        ->firstOrFail();
    $consumptionValueEntry = ValueEntry::query()
        ->where('item_ledger_entry_no', $consumptionEntry->entry_number)
        ->firstOrFail();

    expect((float) $consumptionEntry->quantity)->toBe(-288.0)
        ->and((float) $consumptionEntry->cost_amount_actual)->toBe(1296.0)
        ->and((float) $consumptionValueEntry->quantity)->toBe(-288.0)
        ->and((float) $consumptionValueEntry->cost_amount_actual)->toBe(1296.0)
        ->and((float) $consumptionValueEntry->unit_cost)->toBe(-4.5)
        ->and($consumptionValueEntry->production_order_no)->toBe('PO-REC-001')
        ->and($consumptionValueEntry->production_order_component_line_no)->toBe('10000')
        ->and($consumptionValueEntry->item_no)->toBe('RM-REC')
        ->and($consumptionValueEntry->location_code)->toBe('MAIN')
        ->and((float) $component->actual_quantity_consumed)->toBe(288.0)
        ->and((float) $component->remaining_quantity)->toBe(0.0);

    expect(fn () => app(ProductionOrderService::class)->postConsumption($order->fresh(), [[
        'component_id' => $component->id,
        'quantity' => 1,
    ]], $user->id))->toThrow(Exception::class, 'Cannot consume more than the remaining component quantity');

    app(ProductionOrderService::class)->postCapacity($order->fresh(), $routingLine->id, 0, 10, 250, $user->id);

    $capacityEntry = CapacityLedgerEntry::query()
        ->where('production_order_id', $order->id)
        ->firstOrFail();
    $capacityValueEntry = ValueEntry::query()
        ->where('item_ledger_entry_type', 8)
        ->where('source_no', (string) $capacityEntry->id)
        ->firstOrFail();

    expect((float) $capacityEntry->direct_cost)->toBe(250.0)
        ->and((float) $capacityEntry->overhead_cost)->toBe(50.0)
        ->and((float) $capacityEntry->total_cost)->toBe(300.0)
        ->and((float) $capacityValueEntry->cost_amount_actual)->toBe(300.0)
        ->and((float) $capacityValueEntry->direct_cost_amount)->toBe(250.0)
        ->and((float) $capacityValueEntry->overhead_amount)->toBe(50.0)
        ->and($capacityValueEntry->production_order_no)->toBe('PO-REC-001')
        ->and($capacityValueEntry->production_order_line_no)->toBe('10000')
        ->and($capacityValueEntry->capacity_type)->toBe('WORK_CENTER')
        ->and($capacityValueEntry->capacity_no)->toBe('WC-REC');

    expect(fn () => app(ProductionOrderService::class)->postCapacity($order->fresh(), $routingLine->id, 0, 1, 25, $user->id))
        ->toThrow(Exception::class, 'Cannot post more capacity than the remaining operation time');

    app(ProductionOrderService::class)->finish($order->fresh(), $user->id);

    $order->refresh();
    $outputEntry = ItemLedgerEntry::query()
        ->where('entry_type', ItemLedgerEntryType::OUTPUT)
        ->where('document_number', 'PO-REC-001')
        ->firstOrFail();
    $outputValueEntry = ValueEntry::query()
        ->where('item_ledger_entry_no', $outputEntry->entry_number)
        ->firstOrFail();

    expect($order->status)->toBe(ProductionOrderStatus::FINISHED)
        ->and($order->posted)->toBeTrue()
        ->and((float) $outputEntry->quantity)->toBe(288.0)
        ->and((float) $outputEntry->cost_amount_actual)->toBe(1596.0)
        ->and((float) $outputValueEntry->cost_amount_actual)->toBe(1596.0)
        ->and((float) $outputValueEntry->unit_cost)->toBe(5.5417);

    $documentGlEntries = GlEntry::query()
        ->where('document_number', 'PO-REC-001')
        ->get();
    $wipNetAmount = (float) GlEntry::query()
        ->where('document_number', 'PO-REC-001')
        ->where('chart_of_account_id', $wipAccount->id)
        ->selectRaw('coalesce(sum(debit_amount) - sum(credit_amount), 0) as net_amount')
        ->value('net_amount');

    expect((float) $documentGlEntries->sum('debit_amount'))->toBe(3192.0)
        ->and((float) $documentGlEntries->sum('credit_amount'))->toBe(3192.0)
        ->and($wipNetAmount)->toBe(0.0)
        ->and((float) $documentGlEntries->where('chart_of_account_id', $wipAccount->id)->sum('debit_amount'))->toBe(1596.0)
        ->and((float) $documentGlEntries->where('chart_of_account_id', $wipAccount->id)->sum('credit_amount'))->toBe(1596.0)
        ->and((float) $documentGlEntries->where('chart_of_account_id', $inventoryAccount->id)->sum('debit_amount'))->toBe(1596.0)
        ->and((float) $documentGlEntries->where('chart_of_account_id', $inventoryAccount->id)->sum('credit_amount'))->toBe(1296.0)
        ->and((float) $documentGlEntries->where('chart_of_account_id', $directAppliedAccount->id)->sum('credit_amount'))->toBe(250.0)
        ->and((float) $documentGlEntries->where('chart_of_account_id', $overheadAppliedAccount->id)->sum('credit_amount'))->toBe(50.0);

    expect(fn () => app(ProductionOrderService::class)->finish($order->fresh(), $user->id))
        ->toThrow(Exception::class, 'Production order is already finished');
});

function grantProductionPostingPermissions(User $user): void
{
    foreach (['factory.production_order.post_output', 'factory.production_order.finish'] as $permission) {
        Permission::query()->firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo([
        'factory.production_order.post_output',
        'factory.production_order.finish',
    ]);
}
