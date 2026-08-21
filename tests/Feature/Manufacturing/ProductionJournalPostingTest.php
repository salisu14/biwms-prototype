<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemLedgerEntryType;
use App\Enums\JournalBatchStatus;
use App\Enums\JournalLineStatus;
use App\Enums\ManufacturingCostComponent;
use App\Enums\ProductionCostSettlementClassification;
use App\Enums\ProductionCostSettlementStatus;
use App\Enums\ProductionJournalEntryType;
use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionOutputAllocationStatus;
use App\Enums\ProductionVarianceType;
use App\Filament\Resources\ProductionOrders\Actions\ProductionOrderActions;
use App\Models\AccountingPeriod;
use App\Models\CapacityLedgerEntry;
use App\Models\ChartOfAccount;
use App\Models\CostAdjustmentBatch;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralLedgerSetup;
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
use App\Models\PostingTransaction;
use App\Models\ProductionExpectedCostSnapshot;
use App\Models\ProductionJournalBatch;
use App\Models\ProductionJournalLine;
use App\Models\ProductionJournalTemplate;
use App\Models\ProductionOutputCostAllocation;
use App\Models\ProductionVarianceCalculation;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\ValueEntry;
use App\Services\Inventory\ExpectedCostClearingService;
use App\Services\Inventory\ValueEntryAccountingOrchestrator;
use App\Services\Manufacturing\ExpectedManufacturingCostService;
use App\Services\Manufacturing\ProductionCostSummaryService;
use App\Services\Manufacturing\ProductionOrderCostSettlementService;
use App\Services\Manufacturing\ProductionOrderService;
use App\Services\Manufacturing\ProductionOutputCostService;
use App\Services\Manufacturing\ProductionVarianceCalculationService;
use App\Services\Manufacturing\ProductionVarianceValueEntryService;
use App\Services\Posting\ProductionJournalPostingRoutine;
use App\Support\DecimalMath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    GeneralLedgerSetup::query()->updateOrCreate(
        ['company_name' => 'Default Company'],
        [
            'allow_posting_from' => '2026-01-01',
            'allow_posting_to' => '2026-12-31',
        ],
    );

    AccountingPeriod::query()->firstOrCreate([
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ], [
        'name' => 'FY2026',
        'is_closed' => false,
    ]);
});

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

    $location = Location::factory()->create(['code' => 'MAIN']);

    InventoryPostingSetup::create([
        'inventory_posting_group_id' => $invGroup->id,
        'location_id' => $location->id,
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
    $inventoryAccount = ChartOfAccount::create([
        'account_number' => '1200-UOM',
        'name' => 'Inventory UOM',
        'account_category' => 'asset',
        'account_type' => AccountType::ASSET,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    $wipAccount = ChartOfAccount::create([
        'account_number' => '1210-UOM',
        'name' => 'WIP UOM',
        'account_category' => 'asset',
        'account_type' => AccountType::ASSET,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    $businessGroup = GeneralBusinessPostingGroup::create([
        'code' => 'MAN-UOM',
        'description' => 'Manufacturing UOM',
    ]);
    $productGroup = GeneralProductPostingGroup::create([
        'code' => 'FG-UOM',
        'description' => 'Finished UOM',
    ]);
    $inventoryGroup = InventoryPostingGroup::create([
        'code' => 'FG-UOM',
        'description' => 'Finished UOM',
    ]);
    InventoryPostingSetup::create([
        'inventory_posting_group_id' => $inventoryGroup->id,
        'location_id' => $location->id,
        'inventory_account_id' => $inventoryAccount->id,
        'wip_account_id' => $wipAccount->id,
    ]);
    GeneralPostingSetup::create([
        'general_business_posting_group_id' => $businessGroup->id,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_adj_account_id' => $wipAccount->id,
        'direct_cost_applied_account_id' => $wipAccount->id,
        'overhead_applied_account_id' => $wipAccount->id,
    ]);

    $finishedGood = Item::factory()->create([
        'item_code' => 'FG-CT',
        'description' => 'Carton Finished Good',
        'unit_cost' => 3,
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

    $order = ProductionOrder::query()->create([
        'document_number' => 'PO-UOM-001',
        'status' => ProductionOrderStatus::RELEASED,
        'item_id' => $finishedGood->id,
        'description' => 'One carton output',
        'quantity' => 1,
        'quantity_base' => 288,
        'unit_of_measure_code' => 'CT',
        'starting_date_time' => now(),
        'general_business_posting_group_id' => $businessGroup->id,
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
    $businessGroup = GeneralBusinessPostingGroup::create([
        'code' => 'MAN-FINISH',
        'description' => 'Manufacturing Finish',
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
    GeneralPostingSetup::create([
        'general_business_posting_group_id' => $businessGroup->id,
        'general_product_posting_group_id' => $genProdGroup->id,
        'inventory_adj_account_id' => $wipAccount->id,
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
        'inventory' => 1000,
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
        'general_business_posting_group_id' => $businessGroup->id,
        'general_product_posting_group_id' => $genProdGroup->id,
        'inventory_posting_group_id' => $invGroup->id,
        'costing_method' => 'FIFO',
        'unit_cost' => 0,
        'cost_rollup' => 0,
        'flushing_method' => 'MANUAL',
        'location_code' => $location->code,
        'created_by' => $user->id,
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
        ->and($outputValueEntry->cost_component)->toBe(ManufacturingCostComponent::Output->value)
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

    $allocation = ProductionOutputCostAllocation::query()
        ->where('production_order_id', $order->id)
        ->whereNull('reversed_at')
        ->firstOrFail();

    expect((float) $allocation->allocated_total_cost)->toBe(1296.0)
        ->and((float) $allocation->allocated_material_cost)->toBe(1296.0)
        ->and((float) $allocation->allocated_capacity_cost)->toBe(0.0)
        ->and((float) $allocation->allocated_overhead_cost)->toBe(0.0)
        ->and($allocation->allocation_status)->toBe(ProductionOutputAllocationStatus::Final)
        ->and($allocation->finalized_at)->not->toBeNull()
        ->and($order->cost_settled_at)->not->toBeNull();

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
        'inventory' => 1000,
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
        ->and((float) $consumptionValueEntry->unit_cost)->toBe(4.5)
        ->and($consumptionValueEntry->production_order_no)->toBe('PO-REC-001')
        ->and($consumptionValueEntry->production_order_component_line_no)->toBe('10000')
        ->and($consumptionValueEntry->item_no)->toBe('RM-REC')
        ->and($consumptionValueEntry->location_code)->toBe('MAIN')
        ->and((float) $component->actual_quantity_consumed)->toBe(288.0)
        ->and((float) $component->remaining_quantity)->toBe(0.0)
        ->and((float) $rawMaterial->fresh()->inventory)->toBe(712.0);

    expect(fn () => app(ProductionOrderService::class)->postConsumption($order->fresh(), [[
        'component_id' => $component->id,
        'quantity' => 1,
    ]], $user->id))->toThrow(Exception::class, 'Cannot consume more than the remaining component quantity');

    app(ProductionOrderService::class)->postCapacity($order->fresh(), $routingLine->id, 0, 10, 250, $user->id, 'manual-capacity-line-1');

    $capacityEntry = CapacityLedgerEntry::query()
        ->where('production_order_id', $order->id)
        ->firstOrFail();
    $capacityValueEntry = ValueEntry::query()
        ->where('item_ledger_entry_type', 8)
        ->where('source_no', (string) $capacityEntry->id)
        ->firstOrFail();
    $capacityOverheadValueEntry = ValueEntry::query()
        ->where('item_ledger_entry_type', 10)
        ->where('source_no', (string) $capacityEntry->id)
        ->firstOrFail();

    expect((float) $capacityEntry->direct_cost)->toBe(250.0)
        ->and((float) $capacityEntry->overhead_cost)->toBe(50.0)
        ->and((float) $capacityEntry->total_cost)->toBe(300.0)
        ->and((float) $capacityValueEntry->cost_amount_actual)->toBe(250.0)
        ->and($capacityValueEntry->cost_component)->toBe(ManufacturingCostComponent::DirectCapacity->value)
        ->and((float) $capacityValueEntry->direct_cost_amount)->toBe(250.0)
        ->and((float) $capacityValueEntry->overhead_amount)->toBe(0.0)
        ->and((float) $capacityOverheadValueEntry->cost_amount_actual)->toBe(50.0)
        ->and($capacityOverheadValueEntry->cost_component)->toBe(ManufacturingCostComponent::CapacityOverhead->value)
        ->and((float) $capacityOverheadValueEntry->direct_cost_amount)->toBe(0.0)
        ->and((float) $capacityOverheadValueEntry->overhead_amount)->toBe(50.0)
        ->and($capacityValueEntry->production_order_no)->toBe('PO-REC-001')
        ->and($capacityValueEntry->production_order_line_no)->toBe('10000')
        ->and($capacityValueEntry->capacity_type)->toBe('WORK_CENTER')
        ->and($capacityValueEntry->capacity_no)->toBe('WC-REC');

    app(ProductionOrderService::class)->postCapacity($order->fresh(), $routingLine->id, 0, 10, 250, $user->id, 'manual-capacity-line-1');

    expect(CapacityLedgerEntry::query()
        ->where('production_order_id', $order->id)
        ->count())->toBe(1)
        ->and(ValueEntry::query()
            ->whereIn('item_ledger_entry_type', [8, 10])
            ->where('source_no', (string) $capacityEntry->id)
            ->count())->toBe(2);

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
        ->and((float) $outputValueEntry->unit_cost)->toBe(5.54166667)
        ->and($outputValueEntry->cost_component)->toBe(ManufacturingCostComponent::Output->value)
        ->and((float) $finishedGood->fresh()->inventory)->toBe(288.0);

    $allocation = ProductionOutputCostAllocation::query()
        ->where('production_order_id', $order->id)
        ->firstOrFail();

    expect((float) $allocation->allocated_material_cost)->toBe(1296.0)
        ->and((float) $allocation->allocated_capacity_cost)->toBe(250.0)
        ->and((float) $allocation->allocated_overhead_cost)->toBe(50.0)
        ->and((float) $allocation->allocated_total_cost)->toBe(1596.0)
        ->and($allocation->allocation_status)->toBe(ProductionOutputAllocationStatus::Final)
        ->and($allocation->source_identity_key)->not->toBeNull();

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

    expect(Artisan::call('biwms:manufacturing-cost-reconcile', [
        '--production-order' => $order->id,
        '--json' => true,
    ]))->toBe(0);

    $report = json_decode(trim(Artisan::output()), true);

    foreach ($report['findings'] as $findings) {
        expect($findings)->toBeEmpty();
    }
});

it('blocks production component consumption when component stock is insufficient', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $productGroup = GeneralProductPostingGroup::create([
        'code' => 'FG-NOSTOCK',
        'description' => 'Finished No Stock',
    ]);
    $inventoryGroup = InventoryPostingGroup::create([
        'code' => 'INV-NOSTOCK',
        'description' => 'Inventory No Stock',
    ]);
    $location = Location::factory()->create(['code' => 'MAIN']);

    $finishedGood = Item::factory()->create([
        'item_code' => 'FG-NOSTOCK',
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
    ]);
    $rawMaterial = Item::factory()->create([
        'item_code' => 'RM-NOSTOCK',
        'inventory' => 0,
        'unit_cost' => 4.5,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
    ]);

    $order = ProductionOrder::query()->create([
        'document_number' => 'PO-NOSTOCK-001',
        'status' => ProductionOrderStatus::RELEASED,
        'item_id' => $finishedGood->id,
        'description' => 'No stock order',
        'quantity' => 1,
        'quantity_base' => 1,
        'starting_date_time' => now(),
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
        'description' => 'No stock component',
        'unit_of_measure_code' => 'PCS',
        'quantity_per' => 1,
        'expected_quantity' => 1,
        'expected_quantity_base' => 1,
        'remaining_quantity' => 1,
        'flushing_method' => 'MANUAL',
        'location_code' => $location->code,
    ]);

    expect(fn () => app(ProductionOrderService::class)->postConsumption($order->fresh(), [[
        'component_id' => $component->id,
        'quantity' => 1,
    ]], $user->id))->toThrow(Exception::class, 'Insufficient component inventory');

    expect(ItemLedgerEntry::query()
        ->where('document_number', 'PO-NOSTOCK-001')
        ->where('entry_type', ItemLedgerEntryType::CONSUMPTION)
        ->exists()
    )->toBeFalse();
});

it('exports manufacturing cost reconciliation findings with severity and remediation', function (): void {
    $exportPath = 'storage/app/testing/manufacturing-cost-reconcile-export.json';
    File::delete(base_path($exportPath));

    $user = User::factory()->create();
    $this->actingAs($user);

    $location = Location::factory()->create(['code' => 'MAIN']);
    $productGroup = GeneralProductPostingGroup::create([
        'code' => 'MFG-REC-EXP',
        'description' => 'Manufacturing Reconcile Export',
    ]);
    $inventoryGroup = InventoryPostingGroup::create([
        'code' => 'MFG-REC-EXP',
        'description' => 'Manufacturing Reconcile Export',
    ]);
    $item = Item::factory()->create([
        'item_code' => 'FG-MFG-REC-EXP',
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
    ]);
    $order = ProductionOrder::query()->create([
        'document_number' => 'PO-MFG-REC-EXP',
        'status' => ProductionOrderStatus::FINISHED,
        'item_id' => $item->id,
        'description' => 'Manufacturing reconcile export',
        'quantity' => 1,
        'quantity_base' => 1,
        'starting_date_time' => now(),
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
        'costing_method' => 'FIFO',
        'unit_cost' => 0,
        'cost_rollup' => 0,
        'flushing_method' => 'MANUAL',
        'location_code' => $location->code,
        'posted' => true,
        'posted_at' => now(),
        'posted_by' => $user->id,
        'created_by' => $user->id,
    ]);

    $entry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $order->document_number,
        'document_line_number' => 10000,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'quantity' => 1,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 100,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
        'source_id' => $order->id,
        'source_type' => ProductionOrder::class,
    ]);

    ValueEntry::query()
        ->where('item_ledger_entry_no', $entry->entry_number)
        ->delete();

    expect(Artisan::call('biwms:manufacturing-cost-reconcile', [
        '--production-order' => $order->document_number,
        '--details' => true,
        '--export' => $exportPath,
    ]))->toBe(0);

    expect(File::exists(base_path($exportPath)))->toBeTrue();

    $report = json_decode(File::get(base_path($exportPath)), true);

    expect($report['findings']['output_without_value_entries'])->toHaveCount(1)
        ->and($report['findings']['output_without_value_entries'][0]['classification'])->toBe('output_value_missing')
        ->and($report['findings']['output_without_value_entries'][0]['severity'])->toBe('critical')
        ->and($report['findings']['output_without_value_entries'][0]['suggested_remediation'])->toContain('controlled repost/reversal path')
        ->and($report['findings']['finished_orders_without_cost_settlement'])->toHaveCount(1)
        ->and($report['findings']['finished_orders_without_cost_settlement'][0]['classification'])->toBe('finished_order_not_cost_settled');
});

it('keeps production journal capacity posting idempotent by journal line source identity', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $inventoryAccount = ChartOfAccount::create([
        'account_number' => '1200-JID',
        'name' => 'Inventory Journal Idempotency',
        'account_category' => 'asset',
        'account_type' => AccountType::ASSET,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    $wipAccount = ChartOfAccount::create([
        'account_number' => '1210-JID',
        'name' => 'WIP Journal Idempotency',
        'account_category' => 'asset',
        'account_type' => AccountType::ASSET,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    $directAppliedAccount = ChartOfAccount::create([
        'account_number' => '5100-JID',
        'name' => 'Direct Applied Journal Idempotency',
        'account_category' => 'direct_expense',
        'account_type' => AccountType::EXPENSE,
        'income_balance' => IncomeBalanceType::INCOME_STATEMENT,
    ]);
    $businessGroup = GeneralBusinessPostingGroup::create([
        'code' => 'JID',
        'description' => 'Journal Idempotency',
    ]);
    $productGroup = GeneralProductPostingGroup::create([
        'code' => 'JID',
        'description' => 'Journal Idempotency',
    ]);
    $inventoryGroup = InventoryPostingGroup::create([
        'code' => 'JID',
        'description' => 'Journal Idempotency',
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
        'direct_cost_applied_account_id' => $directAppliedAccount->id,
        'overhead_applied_account_id' => $directAppliedAccount->id,
        'inventory_adj_account_id' => $directAppliedAccount->id,
    ]);

    $item = Item::factory()->create([
        'item_code' => 'FG-JID',
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
    ]);
    $workCenter = WorkCenter::factory()->create([
        'direct_unit_cost' => 10,
        'overhead_rate' => 0,
        'indirect_cost_percent' => 0,
    ]);
    $order = ProductionOrder::query()->create([
        'document_number' => 'PO-JID-001',
        'status' => ProductionOrderStatus::RELEASED,
        'item_id' => $item->id,
        'quantity' => 1,
        'quantity_base' => 1,
        'starting_date_time' => now(),
        'general_business_posting_group_id' => $businessGroup->id,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
        'flushing_method' => 'MANUAL',
        'location_code' => $location->code,
    ]);
    $routingLine = $order->routingLines()->create([
        'line_number' => 10000,
        'operation_no' => '10',
        'description' => 'Journal capacity',
        'work_center_id' => $workCenter->id,
        'setup_time' => 0,
        'run_time' => 20,
        'setup_time_unit' => 'MIN',
        'run_time_unit' => 'MIN',
        'status' => 'PLANNED',
    ]);
    $numberSeries = NumberSeries::create([
        'code' => 'JID',
        'description' => 'Journal Idempotency',
        'prefix' => 'JID',
        'starting_number' => 1,
        'current_number' => 0,
        'is_active' => true,
    ]);
    $template = ProductionJournalTemplate::create([
        'name' => 'JID',
        'description' => 'Journal Idempotency',
        'number_series_id' => $numberSeries->id,
        'absorb_overhead' => false,
    ]);
    $batch = ProductionJournalBatch::create([
        'template_id' => $template->id,
        'name' => 'JID',
        'description' => 'Journal Idempotency',
        'status' => JournalBatchStatus::RELEASED,
        'production_order_id' => $order->id,
    ]);
    $line = ProductionJournalLine::create([
        'batch_id' => $batch->id,
        'line_no' => 10000,
        'posting_date' => now(),
        'entry_type' => ProductionJournalEntryType::Capacity,
        'production_order_id' => $order->id,
        'production_order_no' => $order->document_number,
        'routing_line_id' => $routingLine->id,
        'work_center_id' => $workCenter->id,
        'setup_time' => 0,
        'run_time' => 10,
        'quantity' => 0,
        'quantity_base' => 0,
        'created_by' => $user->id,
    ]);

    $routine = app(ProductionJournalPostingRoutine::class);
    expect($routine->post($batch)->success)->toBeTrue();

    $batch->update(['status' => JournalBatchStatus::RELEASED]);
    $line->update(['line_status' => 'open']);

    expect($routine->post($batch->fresh('lines'))->success)->toBeTrue();

    $capacityEntry = CapacityLedgerEntry::query()
        ->where('production_order_id', $order->id)
        ->firstOrFail();

    expect(CapacityLedgerEntry::query()->where('production_order_id', $order->id)->count())->toBe(1)
        ->and(ValueEntry::query()->where('source_no', (string) $capacityEntry->id)->count())->toBe(1)
        ->and(GlEntry::query()->where('document_number', $order->document_number)->count())->toBe(2)
        ->and((float) $routingLine->fresh()->actual_run_time)->toBe(10.0);
});

it('settlement readiness is scoped to the current order and blocks only current incomplete routing', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    [$order, $otherOrder, $workCenter, $location] = createMinimalSettlementOrders($user);

    $otherOrder->routingLines()->create([
        'line_number' => 10000,
        'operation_no' => '10',
        'description' => 'Other incomplete line',
        'work_center_id' => $workCenter->id,
        'setup_time' => 0,
        'run_time' => 10,
        'setup_time_unit' => 'MIN',
        'run_time_unit' => 'MIN',
        'status' => 'PLANNED',
    ]);

    ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $order->document_number,
        'document_line_number' => 10000,
        'item_id' => $order->item_id,
        'location_id' => $location->id,
        'quantity' => 1,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 0,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
        'source_id' => $order->id,
        'source_type' => ProductionOrder::class,
    ]);

    $result = app(ProductionOrderCostSettlementService::class)->settle($order->fresh(), $user->id);

    expect($result['settled'])->toBeTrue()
        ->and($result['status'])->toBe(ProductionCostSettlementStatus::Settled->value)
        ->and($order->fresh()->cost_settlement_status)->toBe(ProductionCostSettlementStatus::Settled);

    [$blockedOrder,, $blockedWorkCenter, $blockedLocation] = createMinimalSettlementOrders($user, 'BLOCK');
    $blockedOrder->routingLines()->create([
        'line_number' => 10000,
        'operation_no' => '10',
        'description' => 'Current incomplete line',
        'work_center_id' => $blockedWorkCenter->id,
        'setup_time' => 0,
        'run_time' => 10,
        'setup_time_unit' => 'MIN',
        'run_time_unit' => 'MIN',
        'status' => 'PLANNED',
    ]);
    ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $blockedOrder->document_number,
        'document_line_number' => 10000,
        'item_id' => $blockedOrder->item_id,
        'location_id' => $blockedLocation->id,
        'quantity' => 1,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 0,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $blockedOrder->general_product_posting_group_id,
        'inventory_posting_group_id' => $blockedOrder->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
        'source_id' => $blockedOrder->id,
        'source_type' => ProductionOrder::class,
    ]);

    $blockedResult = app(ProductionOrderCostSettlementService::class)->settle($blockedOrder->fresh(), $user->id);

    expect($blockedResult['settled'])->toBeFalse()
        ->and($blockedResult['status'])->toBe(ProductionCostSettlementStatus::NotReady->value)
        ->and($blockedResult['classification'])->toBe(ProductionCostSettlementClassification::RequiredCapacityNotPosted->value);
});

it('settlement readiness accepts posted journal lines zero requirement components and blocks open journal lines', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    [$order,, $workCenter, $location] = createMinimalSettlementOrders($user, 'JREADY');
    $order->components()->create([
        'line_number' => 10000,
        'item_id' => $order->item_id,
        'description' => 'Zero required component',
        'unit_of_measure_code' => 'PCS',
        'quantity_per' => 0,
        'expected_quantity' => 0,
        'expected_quantity_base' => 0,
        'remaining_quantity' => 0,
        'flushing_method' => 'MANUAL',
        'location_code' => $location->code,
    ]);
    ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $order->document_number,
        'document_line_number' => 10000,
        'item_id' => $order->item_id,
        'location_id' => $location->id,
        'quantity' => 1,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 0,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
        'source_id' => $order->id,
        'source_type' => ProductionOrder::class,
    ]);

    $numberSeries = NumberSeries::create([
        'code' => 'JREADY',
        'description' => 'Journal readiness',
        'prefix' => 'JRD',
        'starting_number' => 1,
        'current_number' => 0,
        'is_active' => true,
    ]);
    $template = ProductionJournalTemplate::create([
        'name' => 'JREADY',
        'description' => 'Journal readiness',
        'number_series_id' => $numberSeries->id,
        'absorb_overhead' => false,
    ]);
    $batch = ProductionJournalBatch::create([
        'template_id' => $template->id,
        'name' => 'JREADY',
        'description' => 'Journal readiness',
        'status' => JournalBatchStatus::RELEASED,
        'production_order_id' => $order->id,
    ]);
    ProductionJournalLine::create([
        'batch_id' => $batch->id,
        'line_no' => 10000,
        'posting_date' => now(),
        'entry_type' => ProductionJournalEntryType::Capacity,
        'production_order_id' => $order->id,
        'production_order_no' => $order->document_number,
        'work_center_id' => $workCenter->id,
        'setup_time' => 0,
        'run_time' => 0,
        'quantity' => 0,
        'quantity_base' => 0,
        'line_status' => JournalLineStatus::POSTED,
        'created_by' => $user->id,
    ]);

    expect(app(ProductionOrderCostSettlementService::class)->settle($order->fresh(), $user->id)['settled'])->toBeTrue();

    [$openOrder,, $openWorkCenter, $openLocation] = createMinimalSettlementOrders($user, 'JOPEN');
    ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $openOrder->document_number,
        'document_line_number' => 10000,
        'item_id' => $openOrder->item_id,
        'location_id' => $openLocation->id,
        'quantity' => 1,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 0,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $openOrder->general_product_posting_group_id,
        'inventory_posting_group_id' => $openOrder->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
        'source_id' => $openOrder->id,
        'source_type' => ProductionOrder::class,
    ]);
    $openBatch = ProductionJournalBatch::create([
        'template_id' => $template->id,
        'name' => 'JOPEN',
        'description' => 'Journal open',
        'status' => JournalBatchStatus::RELEASED,
        'production_order_id' => $openOrder->id,
    ]);
    ProductionJournalLine::create([
        'batch_id' => $openBatch->id,
        'line_no' => 10000,
        'posting_date' => now(),
        'entry_type' => ProductionJournalEntryType::Capacity,
        'production_order_id' => $openOrder->id,
        'production_order_no' => $openOrder->document_number,
        'work_center_id' => $openWorkCenter->id,
        'setup_time' => 0,
        'run_time' => 0,
        'quantity' => 0,
        'quantity_base' => 0,
        'line_status' => JournalLineStatus::OPEN,
        'created_by' => $user->id,
    ]);

    $result = app(ProductionOrderCostSettlementService::class)->settle($openOrder->fresh(), $user->id);

    expect($result['settled'])->toBeFalse()
        ->and($result['classification'])->toBe(ProductionCostSettlementClassification::UnresolvedProductionJournalLines->value);
});

it('excludes output and variance value entries from eligible accumulated production cost', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $productGroup = GeneralProductPostingGroup::create([
        'code' => 'MFG-SUM',
        'description' => 'Manufacturing Summary',
    ]);
    $inventoryGroup = InventoryPostingGroup::create([
        'code' => 'MFG-SUM',
        'description' => 'Manufacturing Summary',
    ]);
    $item = Item::factory()->create([
        'item_code' => 'FG-MFG-SUM',
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
    ]);

    $order = ProductionOrder::query()->create([
        'document_number' => 'PO-MFG-SUM',
        'status' => ProductionOrderStatus::RELEASED,
        'item_id' => $item->id,
        'description' => 'Manufacturing summary',
        'quantity' => 1,
        'quantity_base' => 1,
        'starting_date_time' => now(),
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
        'costing_method' => 'FIFO',
        'unit_cost' => 0,
        'cost_rollup' => 0,
        'flushing_method' => 'MANUAL',
        'created_by' => $user->id,
    ]);

    foreach ([
        [ManufacturingCostComponent::DirectMaterial->value, 40],
        [ManufacturingCostComponent::Output->value, 1000],
        [ManufacturingCostComponent::StandardCostVariance->value, 25],
    ] as $index => [$component, $amount]) {
        ValueEntry::query()->create([
            'entry_no' => 900000 + $index,
            'item_ledger_entry_type' => 7,
            'item_no' => $item->item_code,
            'location_code' => 'MAIN',
            'posting_date' => now(),
            'document_type' => 'PRODUCTION_ORDER',
            'document_no' => $order->document_number,
            'document_line_no' => 10000 + $index,
            'quantity' => 1,
            'valued_quantity' => 1,
            'cost_component' => $component,
            'value_entry_state' => $component === ManufacturingCostComponent::StandardCostVariance->value ? 'variance' : 'actual',
            'cost_amount_actual' => $amount,
            'cost_amount_actual_acy' => $amount,
            'source_module' => 'manufacturing',
            'source_id' => $order->id,
            'source_number' => $order->document_number,
            'production_order_no' => $order->document_number,
            'expected_cost' => false,
        ]);
    }

    $summary = app(ProductionCostSummaryService::class)->summarize($order);

    expect($summary['actual_material_cost'])->toBe(40.0)
        ->and($summary['actual_output_cost'])->toBe(1000.0)
        ->and($summary['variance'])->toBe(25.0)
        ->and($summary['total_accumulated_cost'])->toBe(40.0);
});

it('rejects unsupported manufacturing cost components before gl posting', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    [$order,,, $location] = createMinimalSettlementOrders($user, 'BADCOMP');
    $entry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $order->document_number,
        'document_line_number' => 10000,
        'item_id' => $order->item_id,
        'location_id' => $location->id,
        'quantity' => 1,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 1,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
        'source_id' => $order->id,
        'source_type' => ProductionOrder::class,
    ]);

    $valueEntry = ValueEntry::query()
        ->where('item_ledger_entry_no', $entry->entry_number)
        ->firstOrFail();

    $valueEntry->forceFill([
        'cost_component' => 'mystery_factory_cost',
        'source_module' => 'manufacturing',
        'cost_amount_actual' => 1,
    ])->save();

    expect(fn () => app(ValueEntryAccountingOrchestrator::class)->post($valueEntry->fresh()))
        ->toThrow(RuntimeException::class, 'Unsupported manufacturing cost component');

    expect(Artisan::call('biwms:manufacturing-cost-reconcile', [
        '--production-order' => $order->document_number,
        '--json' => true,
    ]))->toBe(0);

    $report = json_decode(trim(Artisan::output()), true);

    expect($report['findings']['unsupported_manufacturing_cost_components'])->toHaveCount(1)
        ->and($report['findings']['unsupported_manufacturing_cost_components'][0]['classification'])->toBe('unsupported_manufacturing_cost_component');
});

it('creates idempotent expected manufacturing cost snapshots and expected value entries', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    [$order,, $workCenter] = createMinimalSettlementOrders($user, 'EXPECTED');
    $componentItem = Item::factory()->create([
        'item_code' => 'RM-EXPECTED',
        'unit_cost' => 7,
        'standard_cost' => 8,
        'costing_method' => 'STANDARD',
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
    ]);

    $order->components()->create([
        'line_number' => 10000,
        'item_id' => $componentItem->id,
        'description' => 'Expected raw material',
        'unit_of_measure_code' => 'PCS',
        'quantity_per' => 2,
        'expected_quantity' => 20,
        'expected_quantity_base' => 20,
        'remaining_quantity' => 20,
        'scrap_percent' => 10,
        'unit_cost' => 7,
    ]);
    $order->routingLines()->create([
        'line_number' => 10000,
        'operation_no' => '10',
        'work_center_id' => $workCenter->id,
        'setup_time' => 1,
        'run_time' => 2,
        'setup_time_unit' => 'MINUTES',
        'run_time_unit' => 'MINUTES',
        'expected_output_quantity' => 1,
        'direct_cost' => 5,
        'overhead_cost' => 1,
        'status' => 'PLANNED',
    ]);

    $result = app(ExpectedManufacturingCostService::class)->calculate($order, userId: $user->id);
    $retry = app(ExpectedManufacturingCostService::class)->calculate($order->fresh(), userId: $user->id);

    expect($retry['snapshot']->id)->toBe($result['snapshot']->id)
        ->and((float) $result['snapshot']->expected_material_cost)->toBe(160.0)
        ->and((float) $result['snapshot']->expected_capacity_cost)->toBe(15.0)
        ->and((float) $result['snapshot']->expected_overhead_cost)->toBe(3.0)
        ->and((float) $result['snapshot']->expected_total_cost)->toBe(178.0)
        ->and(ValueEntry::query()->where('source_type', 'PRODUCTION_EXPECTED_COST')->where('production_order_no', $order->document_number)->count())->toBe(4)
        ->and(ValueEntry::query()->where('cost_component', ManufacturingCostComponent::ExpectedDirectMaterial->value)->first()?->expected_cost)->toBeTrue();

    Artisan::call('biwms:manufacturing-cost-reconcile', ['--json' => true, '--production-order' => $order->document_number]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($report['findings']['manufacturing_value_entries_not_gl_posted'])->toBeEmpty();
});

it('clears manufacturing expected cost append only and idempotently', function (): void {
    config(['accounts.post_expected_inventory_cost_to_gl' => false]);
    $user = User::factory()->create();
    $this->actingAs($user);
    [$order,,, $location] = createMinimalSettlementOrders($user, 'CLEARING');
    createPostingAccountsForOrder($order, $location);
    $componentItem = Item::factory()->create([
        'item_code' => 'RM-CLEARING',
        'unit_cost' => 10,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
    ]);
    $order->components()->create([
        'line_number' => 10000,
        'item_id' => $componentItem->id,
        'description' => 'Clearing component',
        'unit_of_measure_code' => 'PCS',
        'quantity_per' => 1,
        'expected_quantity' => 1,
        'expected_quantity_base' => 1,
        'remaining_quantity' => 0,
        'unit_cost' => 10,
    ]);

    app(ExpectedManufacturingCostService::class)->calculate($order, userId: $user->id);
    $expected = ValueEntry::query()
        ->where('production_order_no', $order->document_number)
        ->where('cost_component', ManufacturingCostComponent::ExpectedDirectMaterial->value)
        ->firstOrFail();
    $expected->forceFill(['gl_posted' => true])->save();

    $actual = ValueEntry::query()->create([
        'entry_no' => (ValueEntry::max('entry_no') ?? 0) + 1,
        'item_ledger_entry_type' => 6,
        'item_no' => 'RM-CLEARING',
        'location_code' => $location->code,
        'posting_date' => now()->toDateString(),
        'document_type' => 'PRODUCTION_ORDER',
        'document_no' => $order->document_number,
        'document_line_no' => 10000,
        'quantity' => -1,
        'valued_quantity' => -1,
        'cost_component' => ManufacturingCostComponent::DirectMaterial->value,
        'value_entry_state' => 'actual',
        'cost_amount_actual' => 12,
        'source_module' => 'manufacturing',
        'source_id' => $order->id,
        'source_no' => (string) $order->id,
        'production_order_no' => $order->document_number,
        'expected_cost' => false,
    ]);

    config(['accounts.post_expected_inventory_cost_to_gl' => true]);
    $clearing = app(ExpectedCostClearingService::class)->clearForActualManufacturingCost($expected, $actual, 1, 10, $user->id);
    $retry = app(ExpectedCostClearingService::class)->clearForActualManufacturingCost($expected->fresh(), $actual, 1, 10, $user->id);

    expect($clearing)->not->toBeNull()
        ->and($retry?->id)->toBe($clearing?->id)
        ->and((float) $clearing?->cost_amount_expected)->toBe(-10.0)
        ->and(ValueEntry::query()->where('value_entry_state', 'clearing')->count())->toBe(1)
        ->and((float) $expected->fresh()->cost_amount_expected)->toBe(10.0);
});

it('resettles late production cost into output adjustment and expected clearing idempotently', function (): void {
    config(['accounts.post_expected_inventory_cost_to_gl' => true]);

    $user = User::factory()->create();
    $this->actingAs($user);

    [$order,,, $location] = createMinimalSettlementOrders($user, 'LATECOST');
    createPostingAccountsForOrder($order, $location);
    $order->forceFill([
        'quantity' => 288,
        'quantity_base' => 288,
    ])->save();

    $componentItem = Item::factory()->create([
        'item_code' => 'RM-LATECOST',
        'unit_cost' => 1,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
    ]);

    ProductionExpectedCostSnapshot::query()->create([
        'production_order_id' => $order->id,
        'finished_item_id' => $order->item_id,
        'production_quantity_base' => 288,
        'costing_date' => now()->toDateString(),
        'expected_material_cost' => 2092.896,
        'expected_capacity_cost' => 0,
        'expected_overhead_cost' => 0,
        'expected_output_cost' => 0,
        'expected_total_cost' => 2092.896,
        'calculation_identity' => 'late-cost-test',
        'status' => 'calculated',
        'calculated_by' => $user->id,
        'calculated_at' => now(),
    ]);

    $outputEntry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $order->document_number,
        'document_line_number' => 10000,
        'item_id' => $order->item_id,
        'location_id' => $location->id,
        'quantity' => 288,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 34525.4933,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
        'source_id' => $order->id,
        'source_type' => ProductionOrder::class,
    ]);

    $outputValueEntry = ValueEntry::query()
        ->where('item_ledger_entry_no', $outputEntry->entry_number)
        ->firstOrFail();
    $outputValueEntry->forceFill([
        'cost_component' => ManufacturingCostComponent::Output->value,
        'source_module' => 'manufacturing',
        'source_id' => $order->id,
        'source_no' => (string) $order->id,
        'production_order_no' => $order->document_number,
        'cost_amount_actual' => 34525.4933,
        'cost_amount_actual_acy' => 34525.4933,
        'unit_cost' => 119.88018507,
        'unit_cost_acy' => 119.88018507,
    ])->save();
    app(ValueEntryAccountingOrchestrator::class)->post($outputValueEntry->fresh());

    $baseMaterial = manufacturingValueEntryForVariance(
        order: $order,
        item: $componentItem,
        location: $location,
        component: ManufacturingCostComponent::DirectMaterial,
        quantity: -288,
        amount: 34525.4933,
        lineNumber: 10000,
    );
    app(ValueEntryAccountingOrchestrator::class)->post($baseMaterial);

    ProductionOutputCostAllocation::query()->create([
        'production_order_id' => $order->id,
        'output_item_ledger_entry_id' => $outputEntry->id,
        'output_value_entry_id' => $outputValueEntry->id,
        'output_quantity' => 288,
        'eligible_cost_before_allocation' => 34525.4933,
        'allocated_material_cost' => 34525.4933,
        'allocated_capacity_cost' => 0,
        'allocated_overhead_cost' => 0,
        'allocated_total_cost' => 34525.4933,
        'allocation_status' => ProductionOutputAllocationStatus::Final->value,
        'is_final_allocation' => true,
        'finalized_at' => now(),
        'idempotency_key' => hash('sha256', implode('|', [
            'production-output-allocation',
            $order->id,
            $outputEntry->id,
            DecimalMath::quantity($outputEntry->quantity),
        ])),
        'source_identity_key' => hash('sha256', implode('|', [
            'production-output-source',
            $order->id,
            $outputEntry->id,
        ])),
    ]);

    $expectedLateMaterial = ValueEntry::query()->create([
        'entry_no' => (ValueEntry::max('entry_no') ?? 0) + 1,
        'item_ledger_entry_type' => 6,
        'item_no' => $componentItem->item_code,
        'location_code' => $location->code,
        'posting_date' => now()->toDateString(),
        'document_type' => 'PRODUCTION_EXPECTED_COST',
        'document_no' => $order->document_number,
        'document_line_no' => 20000,
        'quantity' => -17.065728,
        'valued_quantity' => -17.065728,
        'cost_component' => ManufacturingCostComponent::ExpectedDirectMaterial->value,
        'value_entry_state' => 'expected',
        'cost_amount_expected' => 2092.896,
        'cost_amount_expected_acy' => 2092.896,
        'source_type' => 'PRODUCTION_EXPECTED_COST',
        'source_module' => 'manufacturing',
        'source_id' => $order->id,
        'source_no' => (string) $order->id,
        'source_line_no' => 20000,
        'production_order_no' => $order->document_number,
        'production_order_component_line_no' => 20000,
        'expected_cost' => true,
        'gl_posted' => true,
    ]);

    $lateMaterial = manufacturingValueEntryForVariance(
        order: $order,
        item: $componentItem,
        location: $location,
        component: ManufacturingCostComponent::CostAdjustment,
        quantity: 0,
        amount: 2092.896,
        lineNumber: 20000,
    );
    app(ValueEntryAccountingOrchestrator::class)->post($lateMaterial);

    $order->forceFill([
        'status' => ProductionOrderStatus::FINISHED,
        'cost_settled_at' => now()->subDay(),
        'cost_settled_by' => $user->id,
        'cost_settlement_status' => ProductionCostSettlementStatus::AdjustmentRequired->value,
        'cost_settlement_classification' => ProductionCostSettlementClassification::LateCostAdjustmentRequired->value,
    ])->save();

    $result = app(ProductionOrderCostSettlementService::class)->settle($order->fresh(), $user->id);
    $retry = app(ProductionOrderCostSettlementService::class)->settle($order->fresh(), $user->id);

    $allocation = ProductionOutputCostAllocation::query()
        ->where('production_order_id', $order->id)
        ->firstOrFail();
    $outputAdjustment = ValueEntry::query()
        ->where('production_order_no', $order->document_number)
        ->where('document_type', 'PROD_OUTPUT_COST_ADJ')
        ->firstOrFail();
    $clearing = ValueEntry::query()
        ->where('reversal_of_value_entry_id', $expectedLateMaterial->id)
        ->where('value_entry_state', 'clearing')
        ->firstOrFail();
    $summary = app(ProductionCostSummaryService::class)->summarize($order->fresh());

    expect($result['settled'])->toBeTrue()
        ->and($retry['idempotent'])->toBeTrue()
        ->and((float) $allocation->allocated_total_cost)->toBe(36618.3893)
        ->and((float) $outputEntry->fresh()->cost_amount_actual)->toBe(36618.3893)
        ->and((float) $outputAdjustment->cost_amount_actual)->toBe(2092.896)
        ->and($outputAdjustment->gl_posted)->toBeTrue()
        ->and((float) $clearing->cost_amount_expected)->toBe(-2092.896)
        ->and($clearing->gl_posted)->toBeTrue()
        ->and($summary['unallocated_cost'])->toBe(0.0)
        ->and($summary['uncleared_expected_cost'])->toBe(0.0)
        ->and(ValueEntry::query()->where('document_type', 'PROD_OUTPUT_COST_ADJ')->count())->toBe(1)
        ->and(ValueEntry::query()->where('reversal_of_value_entry_id', $expectedLateMaterial->id)->count())->toBe(1);

    Artisan::call('biwms:manufacturing-cost-reconcile', [
        '--json' => true,
        '--production-order' => $order->document_number,
    ]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($report['findings']['expected_material_cost_uncleared'])->toBeEmpty()
        ->and($report['findings']['finished_orders_with_unallocated_cost'])->toBeEmpty()
        ->and($report['findings']['settled_orders_with_open_wip'])->toBeEmpty();
});

it('isolates production value entries from colliding manufacturing source ids', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    [$orderA, $orderB,, $location] = createMinimalSettlementOrders($user, 'OWNERSHIP');
    $componentItem = Item::factory()->create([
        'item_code' => 'RM-OWNERSHIP',
        'general_product_posting_group_id' => $orderA->general_product_posting_group_id,
        'inventory_posting_group_id' => $orderA->inventory_posting_group_id,
    ]);

    $collidingBatch = null;
    while (! $collidingBatch || $collidingBatch->id < $orderB->id) {
        $collidingBatch = CostAdjustmentBatch::query()->create([
            'batch_number' => 'COSTADJ-COLLISION-'.$orderB->id.'-'.CostAdjustmentBatch::query()->count(),
            'source_type' => ItemLedgerEntry::class,
            'source_id' => 1000,
            'reason' => 'Collision',
            'dry_run' => false,
            'run_at' => now(),
        ]);
    }

    expect($collidingBatch->id)->toBe($orderB->id);

    ValueEntry::query()->create([
        'entry_no' => (ValueEntry::max('entry_no') ?? 0) + 1,
        'item_ledger_entry_type' => 6,
        'item_no' => $componentItem->item_code,
        'location_code' => $location->code,
        'posting_date' => now()->toDateString(),
        'document_type' => 'PRODUCTION_COST_ADJUSTMENT',
        'document_no' => $collidingBatch->batch_number,
        'document_line_no' => 10000,
        'quantity' => 0,
        'valued_quantity' => 0,
        'cost_component' => ManufacturingCostComponent::CostAdjustment->value,
        'value_entry_state' => 'adjustment',
        'cost_amount_actual' => -5791389.6419,
        'source_type' => CostAdjustmentBatch::class,
        'source_module' => 'manufacturing',
        'source_id' => $collidingBatch->id,
        'source_no' => (string) $orderA->id,
        'source_line_no' => 10000,
        'production_order_no' => $orderA->document_number,
        'expected_cost' => false,
        'gl_posted' => true,
    ]);
    ValueEntry::query()->create([
        'entry_no' => (ValueEntry::max('entry_no') ?? 0) + 1,
        'item_ledger_entry_type' => 6,
        'item_no' => $componentItem->item_code,
        'location_code' => $location->code,
        'posting_date' => now()->toDateString(),
        'document_type' => 'LEGACY_PRODUCTION',
        'document_no' => $orderB->document_number,
        'document_line_no' => 20000,
        'quantity' => -1,
        'valued_quantity' => -1,
        'cost_component' => ManufacturingCostComponent::DirectMaterial->value,
        'value_entry_state' => 'actual',
        'cost_amount_actual' => 123.4567,
        'source_type' => ProductionOrder::class,
        'source_module' => 'manufacturing',
        'source_id' => $orderB->id,
        'source_line_no' => 20000,
        'expected_cost' => false,
        'gl_posted' => true,
    ]);

    $summaryA = app(ProductionCostSummaryService::class)->summarize($orderA->fresh());
    $summaryB = app(ProductionCostSummaryService::class)->summarize($orderB->fresh());

    expect($summaryA['actual_material_cost'])->toBe(-5791389.6419)
        ->and($summaryB['actual_material_cost'])->toBe(123.4567)
        ->and($summaryB['posted_adjustments'])->toBe(0.0);

    Artisan::call('biwms:manufacturing-cost-reconcile', ['--json' => true]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($report['findings']['ambiguous_production_value_entry_ownership'])->toBeEmpty()
        ->and($report['findings']['production_source_id_collision_potential'])->toHaveCount(1)
        ->and($report['findings']['production_source_id_collision_potential'][0]['classification'])->toBe('production_source_id_collision_potential')
        ->and($report['findings']['production_source_id_collision_potential'][0]['severity'])->toBe('info')
        ->and($report['findings']['production_source_id_collision_potential'][0]['production_order_no'])->toBe($orderA->document_number)
        ->and($report['findings']['production_source_id_collision_potential'][0]['colliding_production_order_no'])->toBe($orderB->document_number);
});

it('repairs stale output allocation after upstream material adjustment append only and idempotently', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    [$order,,, $location] = createMinimalSettlementOrders($user, 'OUTPUTPROP');
    createPostingAccountsForOrder($order, $location);
    $order->forceFill([
        'quantity' => 1,
        'quantity_base' => 1,
        'status' => ProductionOrderStatus::RELEASED,
    ])->save();

    $componentItem = Item::factory()->create([
        'item_code' => 'RM-OUTPUTPROP',
        'unit_cost' => 1,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
    ]);
    $outputEntry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $order->document_number,
        'document_line_number' => 10000,
        'item_id' => $order->item_id,
        'location_id' => $location->id,
        'quantity' => 1,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 1000,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
        'source_id' => $order->id,
        'source_type' => ProductionOrder::class,
    ]);
    $outputValueEntry = ValueEntry::query()
        ->where('item_ledger_entry_no', $outputEntry->entry_number)
        ->firstOrFail();
    $outputValueEntry->forceFill([
        'cost_component' => ManufacturingCostComponent::Output->value,
        'source_module' => 'manufacturing',
        'source_id' => $order->id,
        'source_type' => ProductionOrder::class,
        'source_no' => (string) $order->id,
        'production_order_no' => $order->document_number,
        'cost_amount_actual' => 1000,
        'cost_amount_actual_acy' => 1000,
        'unit_cost' => 1000,
        'unit_cost_acy' => 1000,
    ])->save();
    app(ValueEntryAccountingOrchestrator::class)->post($outputValueEntry->fresh());

    $material = manufacturingValueEntryForVariance(
        order: $order,
        item: $componentItem,
        location: $location,
        component: ManufacturingCostComponent::DirectMaterial,
        quantity: -1,
        amount: 1000,
        lineNumber: 10000,
    );
    app(ValueEntryAccountingOrchestrator::class)->post($material);

    $originalAllocation = ProductionOutputCostAllocation::query()->create([
        'production_order_id' => $order->id,
        'output_item_ledger_entry_id' => $outputEntry->id,
        'output_value_entry_id' => $outputValueEntry->id,
        'output_quantity' => 1,
        'eligible_cost_before_allocation' => 1000,
        'allocated_material_cost' => 1000,
        'allocated_capacity_cost' => 0,
        'allocated_overhead_cost' => 0,
        'allocated_total_cost' => 1000,
        'allocation_status' => ProductionOutputAllocationStatus::Final->value,
        'is_final_allocation' => true,
        'finalized_at' => now(),
        'idempotency_key' => hash('sha256', implode('|', [
            'production-output-allocation',
            $order->id,
            $outputEntry->id,
            DecimalMath::quantity($outputEntry->quantity),
        ])),
        'source_identity_key' => hash('sha256', implode('|', [
            'production-output-source',
            $order->id,
            $outputEntry->id,
        ])),
    ]);
    $lateAdjustment = manufacturingValueEntryForVariance(
        order: $order,
        item: $componentItem,
        location: $location,
        component: ManufacturingCostComponent::CostAdjustment,
        quantity: 0,
        amount: -900,
        lineNumber: 10000,
    );
    $lateAdjustment->forceFill([
        'document_type' => 'PRODUCTION_COST_ADJUSTMENT',
        'value_entry_state' => 'adjustment',
        'accounting_metadata' => ['gl_covered_by_generic_cost_adjustment' => true],
        'gl_posted' => true,
    ])->save();
    $beforeCounts = [
        'allocations' => ProductionOutputCostAllocation::query()->count(),
        'value_entries' => ValueEntry::query()->count(),
        'gl_entries' => GlEntry::query()->count(),
    ];

    Artisan::call('biwms:manufacturing-cost-reconcile', ['--json' => true, '--production-order' => $order->document_number]);
    $beforeReport = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($beforeReport['findings']['stale_output_cost_propagation_pending'])->toHaveCount(1)
        ->and($beforeReport['findings']['output_cost_overallocated'])->toHaveCount(1);

    Artisan::call('biwms:manufacturing-output-cost-propagation-repair', ['--dry-run' => true, '--production-order' => $order->document_number]);
    expect(ProductionOutputCostAllocation::query()->count())->toBe($beforeCounts['allocations'])
        ->and(ValueEntry::query()->count())->toBe($beforeCounts['value_entries'])
        ->and(GlEntry::query()->count())->toBe($beforeCounts['gl_entries']);

    Artisan::call('biwms:manufacturing-output-cost-propagation-repair', ['--apply' => true, '--production-order' => $order->document_number]);
    $firstApplyOutput = Artisan::output();
    Artisan::call('biwms:manufacturing-output-cost-propagation-repair', ['--apply' => true, '--production-order' => $order->document_number]);
    $secondApplyOutput = Artisan::output();

    $activeAllocation = ProductionOutputCostAllocation::query()
        ->where('production_order_id', $order->id)
        ->whereNull('reversed_at')
        ->firstOrFail();
    $outputAdjustment = ValueEntry::query()
        ->where('production_order_no', $order->document_number)
        ->where('document_type', 'PROD_OUTPUT_COST_ADJ')
        ->firstOrFail();
    $summary = app(ProductionCostSummaryService::class)->summarize($order->fresh());

    expect($firstApplyOutput)->toContain('Repaired: 1')
        ->and($secondApplyOutput)->toContain('Repaired: 0')
        ->and($originalAllocation->fresh()->allocation_status)->toBe(ProductionOutputAllocationStatus::Reversed)
        ->and($originalAllocation->fresh()->reversed_at)->not->toBeNull()
        ->and((float) $activeAllocation->allocated_total_cost)->toBe(100.0)
        ->and($activeAllocation->reversed_allocation_id)->toBe($originalAllocation->id)
        ->and((float) $outputAdjustment->cost_amount_actual)->toBe(-900.0)
        ->and($outputAdjustment->gl_posted)->toBeTrue()
        ->and(ProductionOutputCostAllocation::query()->where('production_order_id', $order->id)->count())->toBe(2)
        ->and(ValueEntry::query()->where('production_order_no', $order->document_number)->where('document_type', 'PROD_OUTPUT_COST_ADJ')->count())->toBe(1)
        ->and($summary['allocated_output_cost'])->toBe(100.0)
        ->and($summary['unallocated_cost'])->toBe(0.0);

    Artisan::call('biwms:manufacturing-cost-reconcile', ['--json' => true, '--production-order' => $order->document_number]);
    $afterReport = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($afterReport['findings']['stale_output_cost_propagation_pending'])->toBeEmpty()
        ->and($afterReport['findings']['output_cost_overallocated'])->toBeEmpty();
});

it('initializes a pending zero output allocation in place without creating an output adjustment value entry', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    [$order,,, $location] = createMinimalSettlementOrders($user, 'PENDOUT');
    createPostingAccountsForOrder($order, $location);

    $componentItem = Item::factory()->create([
        'item_code' => 'RM-PENDOUT',
        'unit_cost' => 100,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
    ]);
    $outputEntry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $order->document_number,
        'document_line_number' => 10000,
        'item_id' => $order->item_id,
        'location_id' => $location->id,
        'quantity' => 1,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 0,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
        'source_id' => $order->id,
        'source_type' => ProductionOrder::class,
    ]);
    $outputValueEntry = ValueEntry::query()
        ->where('item_ledger_entry_no', $outputEntry->entry_number)
        ->firstOrFail();
    $outputValueEntry->forceFill([
        'cost_component' => ManufacturingCostComponent::Output->value,
        'source_module' => 'manufacturing',
        'source_id' => $order->id,
        'source_type' => ProductionOrder::class,
        'source_no' => (string) $order->id,
        'production_order_no' => $order->document_number,
        'cost_amount_actual' => 0,
        'cost_amount_actual_acy' => 0,
        'gl_posted' => false,
    ])->save();
    $material = manufacturingValueEntryForVariance(
        order: $order,
        item: $componentItem,
        location: $location,
        component: ManufacturingCostComponent::DirectMaterial,
        quantity: -1,
        amount: 100,
        lineNumber: 10000,
    );
    $material->forceFill(['gl_posted' => true])->save();
    $consumptionEntryIds = collect(range(1, 10))
        ->map(fn (int $line): int => ItemLedgerEntry::query()->create([
            'entry_type' => ItemLedgerEntryType::CONSUMPTION,
            'document_type' => 'PRODUCTION_ORDER',
            'document_number' => $order->document_number,
            'document_line_number' => 20000 + $line,
            'item_id' => $componentItem->id,
            'location_id' => $location->id,
            'quantity' => -1,
            'remaining_quantity' => 0,
            'cost_amount_actual' => 0,
            'cost_amount_expected' => 0,
            'general_product_posting_group_id' => $order->general_product_posting_group_id,
            'inventory_posting_group_id' => $order->inventory_posting_group_id,
            'posting_date' => now(),
            'entry_date' => now(),
            'open' => false,
            'source_id' => $order->id,
            'source_type' => ProductionOrder::class,
        ])->id);

    $pendingAllocation = ProductionOutputCostAllocation::query()->create([
        'production_order_id' => $order->id,
        'output_item_ledger_entry_id' => $outputEntry->id,
        'output_value_entry_id' => $outputValueEntry->id,
        'output_quantity' => 1,
        'eligible_cost_before_allocation' => 0,
        'allocated_material_cost' => 0,
        'allocated_capacity_cost' => 0,
        'allocated_overhead_cost' => 0,
        'allocated_total_cost' => 0,
        'allocation_status' => ProductionOutputAllocationStatus::Pending->value,
        'is_final_allocation' => false,
        'idempotency_key' => hash('sha256', implode('|', [
            'production-output-allocation',
            $order->id,
            $outputEntry->id,
            DecimalMath::quantity($outputEntry->quantity),
        ])),
        'source_identity_key' => hash('sha256', implode('|', [
            'production-output-source',
            $order->id,
            $outputEntry->id,
        ])),
    ]);

    $beforeCounts = [
        'allocations' => ProductionOutputCostAllocation::query()->count(),
        'adjustments' => ValueEntry::query()->where('document_type', 'PROD_OUTPUT_COST_ADJ')->count(),
        'gl_entries' => GlEntry::query()->count(),
        'posting_transactions' => PostingTransaction::query()->count(),
    ];

    Artisan::call('biwms:manufacturing-output-cost-propagation-repair', ['--apply' => true, '--production-order' => $order->document_number]);
    $output = Artisan::output();
    Artisan::call('biwms:manufacturing-output-cost-propagation-repair', ['--apply' => true, '--production-order' => $order->document_number]);

    expect($output)->toContain('status=initialized_pending_output')
        ->and($output)->toContain('output_entry_count=1')
        ->and(ProductionOutputCostAllocation::query()->count())->toBe($beforeCounts['allocations'])
        ->and(ProductionOutputCostAllocation::query()->whereIn('output_item_ledger_entry_id', $consumptionEntryIds)->doesntExist())->toBeTrue()
        ->and($pendingAllocation->fresh()->allocation_status)->toBe(ProductionOutputAllocationStatus::Final)
        ->and((float) $pendingAllocation->fresh()->allocated_total_cost)->toBe(100.0)
        ->and((float) $outputValueEntry->fresh()->cost_amount_actual)->toBe(100.0)
        ->and($outputValueEntry->fresh()->gl_posted)->toBeTrue()
        ->and($outputValueEntry->fresh()->posting_transaction_id)->not->toBeNull()
        ->and(ValueEntry::query()->where('document_type', 'PROD_OUTPUT_COST_ADJ')->count())->toBe($beforeCounts['adjustments'])
        ->and(PostingTransaction::query()->count())->toBe($beforeCounts['posting_transactions'] + 1)
        ->and(GlEntry::query()->count())->toBe($beforeCounts['gl_entries'] + 2)
        ->and($outputValueEntry->fresh()->postingTransaction->glEntries()->count())->toBe(2)
        ->and((float) $outputValueEntry->fresh()->postingTransaction->glEntries()->sum('debit_amount'))->toBe(100.0)
        ->and((float) $outputValueEntry->fresh()->postingTransaction->glEntries()->sum('credit_amount'))->toBe(100.0);
});

it('rejects output cost allocation for non-output cross-order and nonpositive output entries', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    [$order, $otherOrder,, $location] = createMinimalSettlementOrders($user, 'OUTGUARD');
    createPostingAccountsForOrder($order, $location);
    $componentItem = Item::factory()->create([
        'item_code' => 'RM-OUTGUARD',
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
    ]);
    $consumptionEntry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::CONSUMPTION,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $order->document_number,
        'document_line_number' => 10000,
        'item_id' => $componentItem->id,
        'location_id' => $location->id,
        'quantity' => -1,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 10,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
        'source_id' => $order->id,
        'source_type' => ProductionOrder::class,
    ]);
    $otherOutputEntry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $otherOrder->document_number,
        'document_line_number' => 10000,
        'item_id' => $otherOrder->item_id,
        'location_id' => $location->id,
        'quantity' => 1,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 10,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $otherOrder->general_product_posting_group_id,
        'inventory_posting_group_id' => $otherOrder->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
        'source_id' => $otherOrder->id,
        'source_type' => ProductionOrder::class,
    ]);
    $zeroOutputEntry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $order->document_number,
        'document_line_number' => 20000,
        'item_id' => $order->item_id,
        'location_id' => $location->id,
        'quantity' => 0,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 0,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
        'source_id' => $order->id,
        'source_type' => ProductionOrder::class,
    ]);
    $validOutputEntry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $order->document_number,
        'document_line_number' => 30000,
        'item_id' => $order->item_id,
        'location_id' => $location->id,
        'quantity' => 1,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 0,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
        'source_id' => $order->id,
        'source_type' => ProductionOrder::class,
    ]);
    manufacturingValueEntryForVariance(
        order: $order,
        item: $componentItem,
        location: $location,
        component: ManufacturingCostComponent::DirectMaterial,
        quantity: -1,
        amount: 10,
        lineNumber: 40000,
    )->forceFill(['gl_posted' => true])->save();

    $service = app(ProductionOutputCostService::class);

    expect(fn () => $service->allocateToOutput($order, $consumptionEntry, true))
        ->toThrow(RuntimeException::class, 'requires an Output Item Ledger Entry')
        ->and(fn () => $service->allocateToOutput($order, $otherOutputEntry, true))
        ->toThrow(RuntimeException::class, 'does not belong to production order')
        ->and(fn () => $service->allocateToOutput($order, $zeroOutputEntry, true))
        ->toThrow(RuntimeException::class, 'requires positive output quantity')
        ->and(fn () => $service->allocateToOutput($order, $validOutputEntry, true))
        ->not->toThrow(RuntimeException::class)
        ->and(ProductionOutputCostAllocation::query()->whereIn('output_item_ledger_entry_id', [
            $consumptionEntry->id,
            $otherOutputEntry->id,
            $zeroOutputEntry->id,
        ])->doesntExist())->toBeTrue()
        ->and(ProductionOutputCostAllocation::query()->where('output_item_ledger_entry_id', $validOutputEntry->id)->exists())->toBeTrue();
});

it('reports invalid output allocation item ledger entry type and order ownership mismatches', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    [$order, $otherOrder,, $location] = createMinimalSettlementOrders($user, 'OUTRECON');
    $componentItem = Item::factory()->create([
        'item_code' => 'RM-OUTRECON',
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
    ]);
    $consumptionEntry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::CONSUMPTION,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $order->document_number,
        'document_line_number' => 10000,
        'item_id' => $componentItem->id,
        'location_id' => $location->id,
        'quantity' => -1,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 10,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
        'source_id' => $order->id,
        'source_type' => ProductionOrder::class,
    ]);
    $otherOutputEntry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $otherOrder->document_number,
        'document_line_number' => 10000,
        'item_id' => $otherOrder->item_id,
        'location_id' => $location->id,
        'quantity' => 1,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 10,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $otherOrder->general_product_posting_group_id,
        'inventory_posting_group_id' => $otherOrder->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
        'source_id' => $otherOrder->id,
        'source_type' => ProductionOrder::class,
    ]);

    foreach ([[$consumptionEntry, 'bad-type'], [$otherOutputEntry, 'bad-order']] as [$entry, $suffix]) {
        ProductionOutputCostAllocation::query()->create([
            'production_order_id' => $order->id,
            'output_item_ledger_entry_id' => $entry->id,
            'output_value_entry_id' => ValueEntry::query()->where('item_ledger_entry_no', $entry->entry_number)->value('id'),
            'output_quantity' => abs((float) $entry->quantity),
            'eligible_cost_before_allocation' => 10,
            'allocated_material_cost' => 10,
            'allocated_capacity_cost' => 0,
            'allocated_overhead_cost' => 0,
            'allocated_total_cost' => 10,
            'allocation_status' => ProductionOutputAllocationStatus::Final->value,
            'is_final_allocation' => true,
            'finalized_at' => now(),
            'idempotency_key' => 'invalid-output-allocation-'.$suffix,
            'source_identity_key' => 'invalid-output-source-'.$suffix,
        ]);
    }

    Artisan::call('biwms:manufacturing-cost-reconcile', ['--json' => true, '--production-order' => $order->document_number]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($report['findings']['invalid_output_allocation_ile_type'])->toHaveCount(1)
        ->and($report['findings']['invalid_output_allocation_ile_type'][0]['classification'])->toBe('invalid_output_allocation_ile_type')
        ->and($report['findings']['invalid_output_allocation_ile_type'][0]['severity'])->toBe('critical')
        ->and($report['findings']['output_allocation_order_mismatch'])->toHaveCount(1)
        ->and($report['findings']['output_allocation_order_mismatch'][0]['classification'])->toBe('output_allocation_order_mismatch')
        ->and($report['findings']['output_allocation_order_mismatch'][0]['severity'])->toBe('critical');
});

it('replaces a nonzero provisional stale output allocation append only', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    [$order,,, $location] = createMinimalSettlementOrders($user, 'PROVOUTPUT');
    createPostingAccountsForOrder($order, $location);

    $componentItem = Item::factory()->create([
        'item_code' => 'RM-PROVOUTPUT',
        'unit_cost' => 1,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
    ]);
    $outputEntry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $order->document_number,
        'document_line_number' => 10000,
        'item_id' => $order->item_id,
        'location_id' => $location->id,
        'quantity' => 1,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 1000,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
        'source_id' => $order->id,
        'source_type' => ProductionOrder::class,
    ]);
    $outputValueEntry = ValueEntry::query()
        ->where('item_ledger_entry_no', $outputEntry->entry_number)
        ->firstOrFail();
    $outputValueEntry->forceFill([
        'cost_component' => ManufacturingCostComponent::Output->value,
        'source_module' => 'manufacturing',
        'source_id' => $order->id,
        'source_type' => ProductionOrder::class,
        'source_no' => (string) $order->id,
        'production_order_no' => $order->document_number,
        'cost_amount_actual' => 1000,
        'cost_amount_actual_acy' => 1000,
        'unit_cost' => 1000,
        'unit_cost_acy' => 1000,
    ])->save();
    app(ValueEntryAccountingOrchestrator::class)->post($outputValueEntry->fresh());

    $material = manufacturingValueEntryForVariance(
        order: $order,
        item: $componentItem,
        location: $location,
        component: ManufacturingCostComponent::DirectMaterial,
        quantity: -1,
        amount: 1000,
        lineNumber: 10000,
    );
    app(ValueEntryAccountingOrchestrator::class)->post($material);

    $originalAllocation = ProductionOutputCostAllocation::query()->create([
        'production_order_id' => $order->id,
        'output_item_ledger_entry_id' => $outputEntry->id,
        'output_value_entry_id' => $outputValueEntry->id,
        'output_quantity' => 1,
        'eligible_cost_before_allocation' => 1000,
        'allocated_material_cost' => 1000,
        'allocated_capacity_cost' => 0,
        'allocated_overhead_cost' => 0,
        'allocated_total_cost' => 1000,
        'allocation_status' => ProductionOutputAllocationStatus::Provisional->value,
        'is_final_allocation' => false,
        'idempotency_key' => hash('sha256', implode('|', [
            'production-output-allocation',
            $order->id,
            $outputEntry->id,
            DecimalMath::quantity($outputEntry->quantity),
        ])),
        'source_identity_key' => hash('sha256', implode('|', [
            'production-output-source',
            $order->id,
            $outputEntry->id,
        ])),
    ]);

    $lateAdjustment = manufacturingValueEntryForVariance(
        order: $order,
        item: $componentItem,
        location: $location,
        component: ManufacturingCostComponent::CostAdjustment,
        quantity: 0,
        amount: -900,
        lineNumber: 10000,
    );
    $lateAdjustment->forceFill([
        'document_type' => 'PRODUCTION_COST_ADJUSTMENT',
        'value_entry_state' => 'adjustment',
        'accounting_metadata' => ['gl_covered_by_generic_cost_adjustment' => true],
        'gl_posted' => true,
    ])->save();

    Artisan::call('biwms:manufacturing-output-cost-propagation-repair', ['--apply' => true, '--production-order' => $order->document_number]);
    Artisan::call('biwms:manufacturing-output-cost-propagation-repair', ['--apply' => true, '--production-order' => $order->document_number]);

    $replacement = ProductionOutputCostAllocation::query()
        ->where('production_order_id', $order->id)
        ->whereNull('reversed_at')
        ->firstOrFail();

    expect((float) $originalAllocation->fresh()->allocated_total_cost)->toBe(1000.0)
        ->and($originalAllocation->fresh()->allocation_status)->toBe(ProductionOutputAllocationStatus::Reversed)
        ->and($originalAllocation->fresh()->reversed_at)->not->toBeNull()
        ->and((float) $replacement->allocated_total_cost)->toBe(100.0)
        ->and($replacement->allocation_status)->toBe(ProductionOutputAllocationStatus::Final)
        ->and($replacement->reversed_allocation_id)->toBe($originalAllocation->id)
        ->and(ProductionOutputCostAllocation::query()->where('production_order_id', $order->id)->whereNull('reversed_at')->count())->toBe(1)
        ->and(ProductionOutputCostAllocation::query()->where('production_order_id', $order->id)->count())->toBe(2)
        ->and(ValueEntry::query()->where('production_order_no', $order->document_number)->where('document_type', 'PROD_OUTPUT_COST_ADJ')->count())->toBe(1);
});

it('does not repair finished adjustment required orders through the generic output propagation command', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    [$order,,, $location] = createMinimalSettlementOrders($user, 'ADJOUT');
    createPostingAccountsForOrder($order, $location);
    $order->forceFill([
        'status' => ProductionOrderStatus::FINISHED,
        'cost_settlement_status' => ProductionCostSettlementStatus::AdjustmentRequired->value,
        'cost_settlement_classification' => ProductionCostSettlementClassification::LateCostAdjustmentRequired->value,
        'cost_settled_at' => now()->subDay(),
        'cost_settled_by' => $user->id,
    ])->save();

    $componentItem = Item::factory()->create([
        'item_code' => 'RM-ADJOUT',
        'unit_cost' => 20,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
    ]);
    $outputEntry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $order->document_number,
        'document_line_number' => 10000,
        'item_id' => $order->item_id,
        'location_id' => $location->id,
        'quantity' => 1,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 10,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
        'source_id' => $order->id,
        'source_type' => ProductionOrder::class,
    ]);
    $outputValueEntry = ValueEntry::query()
        ->where('item_ledger_entry_no', $outputEntry->entry_number)
        ->firstOrFail();
    $outputValueEntry->forceFill([
        'cost_component' => ManufacturingCostComponent::Output->value,
        'source_module' => 'manufacturing',
        'source_id' => $order->id,
        'source_type' => ProductionOrder::class,
        'source_no' => (string) $order->id,
        'production_order_no' => $order->document_number,
        'cost_amount_actual' => 10,
        'cost_amount_actual_acy' => 10,
    ])->save();
    manufacturingValueEntryForVariance(
        order: $order,
        item: $componentItem,
        location: $location,
        component: ManufacturingCostComponent::DirectMaterial,
        quantity: -1,
        amount: 20,
        lineNumber: 10000,
    )->forceFill(['gl_posted' => true])->save();

    $allocation = ProductionOutputCostAllocation::query()->create([
        'production_order_id' => $order->id,
        'output_item_ledger_entry_id' => $outputEntry->id,
        'output_value_entry_id' => $outputValueEntry->id,
        'output_quantity' => 1,
        'eligible_cost_before_allocation' => 10,
        'allocated_material_cost' => 10,
        'allocated_capacity_cost' => 0,
        'allocated_overhead_cost' => 0,
        'allocated_total_cost' => 10,
        'allocation_status' => ProductionOutputAllocationStatus::Final->value,
        'is_final_allocation' => true,
        'finalized_at' => now(),
        'idempotency_key' => hash('sha256', implode('|', [
            'production-output-allocation',
            $order->id,
            $outputEntry->id,
            DecimalMath::quantity($outputEntry->quantity),
        ])),
        'source_identity_key' => hash('sha256', implode('|', [
            'production-output-source',
            $order->id,
            $outputEntry->id,
        ])),
    ]);

    Artisan::call('biwms:manufacturing-output-cost-propagation-repair', ['--apply' => true, '--production-order' => $order->document_number]);
    $output = Artisan::output();

    expect($output)->toContain('status=requires_resettlement')
        ->and($output)->toContain('Repaired: 0')
        ->and($allocation->fresh()->allocation_status)->toBe(ProductionOutputAllocationStatus::Final)
        ->and($allocation->fresh()->reversed_at)->toBeNull()
        ->and((float) $allocation->fresh()->allocated_total_cost)->toBe(10.0)
        ->and($order->fresh()->cost_settlement_status)->toBe(ProductionCostSettlementStatus::AdjustmentRequired)
        ->and(ValueEntry::query()->where('production_order_no', $order->document_number)->where('document_type', 'PROD_OUTPUT_COST_ADJ')->doesntExist())->toBeTrue();
});

it('allows completed routing with valid zero cost capacity even when planned setup exceeds actual setup', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    [$order,, $workCenter, $location] = createMinimalSettlementOrders($user, 'READYSETUP');
    createPostingAccountsForOrder($order, $location);
    ProductionExpectedCostSnapshot::query()->create([
        'production_order_id' => $order->id,
        'finished_item_id' => $order->item_id,
        'production_quantity_base' => 1,
        'costing_date' => now()->toDateString(),
        'expected_material_cost' => 0,
        'expected_capacity_cost' => 0,
        'expected_overhead_cost' => 0,
        'expected_output_cost' => 0,
        'expected_total_cost' => 0,
        'calculation_identity' => 'ready-setup',
        'status' => 'calculated',
        'calculated_by' => $user->id,
        'calculated_at' => now(),
    ]);

    $routingLine = $order->routingLines()->create([
        'line_number' => 10000,
        'operation_no' => '10',
        'work_center_id' => $workCenter->id,
        'setup_time' => 5,
        'run_time' => 8,
        'setup_time_unit' => 'MINUTES',
        'run_time_unit' => 'MINUTES',
        'actual_setup_time' => 0,
        'actual_run_time' => 8,
        'status' => 'COMPLETED',
    ]);

    App\Models\Manufacturing\CapacityLedgerEntry::query()->create([
        'production_order_id' => $order->id,
        'routing_line_id' => $routingLine->id,
        'work_center_id' => $workCenter->id,
        'posting_date' => now()->toDateString(),
        'document_number' => $order->document_number,
        'setup_time' => 0,
        'run_time' => 8,
        'setup_time_unit' => 'MINUTES',
        'run_time_unit' => 'MINUTES',
        'direct_cost' => 0,
        'overhead_cost' => 0,
        'total_cost' => 0,
        'cost_state' => 'actual',
    ]);

    ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $order->document_number,
        'document_line_number' => 10000,
        'item_id' => $order->item_id,
        'location_id' => $location->id,
        'quantity' => 1,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 0,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
        'source_id' => $order->id,
        'source_type' => ProductionOrder::class,
    ]);

    $result = app(ProductionOrderCostSettlementService::class)->settle($order->fresh(), $user->id);

    expect($result['settled'])->toBeTrue()
        ->and($result['status'])->toBe(ProductionCostSettlementStatus::Settled->value)
        ->and(App\Models\Manufacturing\CapacityLedgerEntry::query()->where('production_order_id', $order->id)->first()?->total_cost)->toEqual('0.0000');
});

it('keeps genuinely incomplete routing not ready for settlement', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    [$order,, $workCenter, $location] = createMinimalSettlementOrders($user, 'NRROUTE');
    createPostingAccountsForOrder($order, $location);
    ProductionExpectedCostSnapshot::query()->create([
        'production_order_id' => $order->id,
        'finished_item_id' => $order->item_id,
        'production_quantity_base' => 1,
        'costing_date' => now()->toDateString(),
        'expected_material_cost' => 0,
        'expected_capacity_cost' => 0,
        'expected_overhead_cost' => 0,
        'expected_output_cost' => 0,
        'expected_total_cost' => 0,
        'calculation_identity' => 'not-ready-route',
        'status' => 'calculated',
        'calculated_by' => $user->id,
        'calculated_at' => now(),
    ]);
    $order->routingLines()->create([
        'line_number' => 10000,
        'operation_no' => '10',
        'work_center_id' => $workCenter->id,
        'setup_time' => 5,
        'run_time' => 8,
        'setup_time_unit' => 'MINUTES',
        'run_time_unit' => 'MINUTES',
        'actual_setup_time' => 0,
        'actual_run_time' => 0,
        'status' => 'PLANNED',
    ]);
    ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $order->document_number,
        'document_line_number' => 10000,
        'item_id' => $order->item_id,
        'location_id' => $location->id,
        'quantity' => 1,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 0,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
        'source_id' => $order->id,
        'source_type' => ProductionOrder::class,
    ]);

    $result = app(ProductionOrderCostSettlementService::class)->settle($order->fresh(), $user->id);

    expect($result['settled'])->toBeFalse()
        ->and($result['status'])->toBe(ProductionCostSettlementStatus::NotReady->value)
        ->and($result['classification'])->toBe(ProductionCostSettlementClassification::RequiredCapacityNotPosted->value)
        ->and(App\Models\Manufacturing\CapacityLedgerEntry::query()->where('production_order_id', $order->id)->exists())->toBeFalse();
});

it('economically clears manufacturing expected cost without gl clearing when expected gl is disabled', function (): void {
    config(['accounts.post_expected_inventory_cost_to_gl' => false]);

    $user = User::factory()->create();
    $this->actingAs($user);

    [$order,,, $location] = createMinimalSettlementOrders($user, 'ECOCLEAR');
    createPostingAccountsForOrder($order, $location);
    $componentItem = Item::factory()->create([
        'item_code' => 'RM-ECOCLEAR',
        'unit_cost' => 10,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
    ]);
    ProductionExpectedCostSnapshot::query()->create([
        'production_order_id' => $order->id,
        'finished_item_id' => $order->item_id,
        'production_quantity_base' => 1,
        'costing_date' => now()->toDateString(),
        'expected_material_cost' => 60,
        'expected_capacity_cost' => 0,
        'expected_overhead_cost' => 0,
        'expected_output_cost' => 60,
        'expected_total_cost' => 60,
        'calculation_identity' => 'eco-clear',
        'status' => 'calculated',
        'calculated_by' => $user->id,
        'calculated_at' => now(),
    ]);

    $expectedMaterial = ValueEntry::query()->create([
        'entry_no' => (ValueEntry::max('entry_no') ?? 0) + 1,
        'item_ledger_entry_type' => 6,
        'item_no' => $componentItem->item_code,
        'location_code' => $location->code,
        'posting_date' => now()->toDateString(),
        'document_type' => 'PRODUCTION_EXPECTED_COST',
        'document_no' => $order->document_number,
        'document_line_no' => 10000,
        'quantity' => -6,
        'valued_quantity' => -6,
        'cost_component' => ManufacturingCostComponent::ExpectedDirectMaterial->value,
        'value_entry_state' => 'expected',
        'cost_amount_expected' => 60,
        'cost_amount_expected_acy' => 60,
        'source_type' => 'PRODUCTION_EXPECTED_COST',
        'source_module' => 'manufacturing',
        'source_id' => $order->id,
        'source_no' => (string) $order->id,
        'source_line_no' => 10000,
        'production_order_no' => $order->document_number,
        'production_order_component_line_no' => 10000,
        'expected_cost' => true,
    ]);
    $expectedOutput = ValueEntry::query()->create([
        'entry_no' => (ValueEntry::max('entry_no') ?? 0) + 1,
        'item_ledger_entry_type' => 7,
        'item_no' => $order->item->item_code,
        'location_code' => $location->code,
        'posting_date' => now()->toDateString(),
        'document_type' => 'PRODUCTION_EXPECTED_COST',
        'document_no' => $order->document_number,
        'document_line_no' => 20000,
        'quantity' => 1,
        'valued_quantity' => 1,
        'cost_component' => ManufacturingCostComponent::ExpectedOutput->value,
        'value_entry_state' => 'expected',
        'cost_amount_expected' => 60,
        'cost_amount_expected_acy' => 60,
        'source_type' => 'PRODUCTION_EXPECTED_COST',
        'source_module' => 'manufacturing',
        'source_id' => $order->id,
        'source_no' => (string) $order->id,
        'source_line_no' => 20000,
        'production_order_no' => $order->document_number,
        'production_order_line_no' => 20000,
        'expected_cost' => true,
    ]);
    $actualMaterial = manufacturingValueEntryForVariance(
        order: $order,
        item: $componentItem,
        location: $location,
        component: ManufacturingCostComponent::DirectMaterial,
        quantity: -6,
        amount: 60,
        lineNumber: 10000,
    );
    app(ValueEntryAccountingOrchestrator::class)->post($actualMaterial);
    $outputEntry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $order->document_number,
        'document_line_number' => 10000,
        'item_id' => $order->item_id,
        'location_id' => $location->id,
        'quantity' => 1,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 60,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
        'source_id' => $order->id,
        'source_type' => ProductionOrder::class,
    ]);
    $outputValueEntry = ValueEntry::query()
        ->where('item_ledger_entry_no', $outputEntry->entry_number)
        ->firstOrFail();
    $outputValueEntry->forceFill([
        'cost_component' => ManufacturingCostComponent::Output->value,
        'source_module' => 'manufacturing',
        'source_id' => $order->id,
        'source_no' => (string) $order->id,
        'production_order_no' => $order->document_number,
        'cost_amount_actual' => 60,
        'cost_amount_actual_acy' => 60,
    ])->save();
    app(ValueEntryAccountingOrchestrator::class)->post($outputValueEntry->fresh());

    $result = app(ProductionOrderCostSettlementService::class)->settle($order->fresh(), $user->id);
    $retry = app(ProductionOrderCostSettlementService::class)->settle($order->fresh(), $user->id);
    $summary = app(ProductionCostSummaryService::class)->summarize($order->fresh());

    expect($result['settled'])->toBeTrue()
        ->and($retry['idempotent'])->toBeTrue()
        ->and($summary['uncleared_expected_cost'])->toBe(0.0)
        ->and(ValueEntry::query()->where('reversal_of_value_entry_id', $expectedMaterial->id)->count())->toBe(1)
        ->and(ValueEntry::query()->where('reversal_of_value_entry_id', $expectedOutput->id)->count())->toBe(1)
        ->and(ValueEntry::query()->whereIn('reversal_of_value_entry_id', [$expectedMaterial->id, $expectedOutput->id])->where('gl_posted', true)->exists())->toBeFalse()
        ->and(ValueEntry::query()->where('value_entry_state', 'clearing')->count())->toBe(2);
});

it('rolls back expected clearing and output allocation when settlement fails after clearing', function (): void {
    config(['accounts.post_expected_inventory_cost_to_gl' => false]);

    $user = User::factory()->create();
    $this->actingAs($user);

    [$order,,, $location] = createMinimalSettlementOrders($user, 'ROLLBACK');
    createPostingAccountsForOrder($order, $location);
    $componentItem = Item::factory()->create([
        'item_code' => 'RM-ROLLBACK',
        'unit_cost' => 10,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
    ]);
    ProductionExpectedCostSnapshot::query()->create([
        'production_order_id' => $order->id,
        'finished_item_id' => $order->item_id,
        'production_quantity_base' => 1,
        'costing_date' => now()->toDateString(),
        'expected_material_cost' => 60,
        'expected_capacity_cost' => 0,
        'expected_overhead_cost' => 0,
        'expected_output_cost' => 0,
        'expected_total_cost' => 60,
        'calculation_identity' => 'rollback-clear',
        'status' => 'calculated',
        'calculated_by' => $user->id,
        'calculated_at' => now(),
    ]);

    $expectedMaterial = ValueEntry::query()->create([
        'entry_no' => (ValueEntry::max('entry_no') ?? 0) + 1,
        'item_ledger_entry_type' => 6,
        'item_no' => $componentItem->item_code,
        'location_code' => $location->code,
        'posting_date' => now()->toDateString(),
        'document_type' => 'PRODUCTION_EXPECTED_COST',
        'document_no' => $order->document_number,
        'document_line_no' => 10000,
        'quantity' => -6,
        'valued_quantity' => -6,
        'cost_component' => ManufacturingCostComponent::ExpectedDirectMaterial->value,
        'value_entry_state' => 'expected',
        'cost_amount_expected' => 60,
        'source_type' => 'PRODUCTION_EXPECTED_COST',
        'source_module' => 'manufacturing',
        'source_id' => $order->id,
        'source_no' => (string) $order->id,
        'source_line_no' => 10000,
        'production_order_no' => $order->document_number,
        'production_order_component_line_no' => 10000,
        'expected_cost' => true,
    ]);
    $actualMaterial = manufacturingValueEntryForVariance(
        order: $order,
        item: $componentItem,
        location: $location,
        component: ManufacturingCostComponent::DirectMaterial,
        quantity: -6,
        amount: 60,
        lineNumber: 10000,
    );
    app(ValueEntryAccountingOrchestrator::class)->post($actualMaterial);
    $outputEntry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $order->document_number,
        'document_line_number' => 10000,
        'item_id' => $order->item_id,
        'location_id' => $location->id,
        'quantity' => 1,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 60,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
        'source_id' => $order->id,
        'source_type' => ProductionOrder::class,
    ]);
    $outputValueEntry = ValueEntry::query()
        ->where('item_ledger_entry_no', $outputEntry->entry_number)
        ->firstOrFail();
    $outputValueEntry->forceFill([
        'cost_component' => ManufacturingCostComponent::Output->value,
        'source_module' => 'manufacturing',
        'source_id' => $order->id,
        'source_no' => (string) $order->id,
        'production_order_no' => $order->document_number,
        'cost_amount_actual' => 60,
        'cost_amount_actual_acy' => 60,
    ])->save();
    app(ValueEntryAccountingOrchestrator::class)->post($outputValueEntry->fresh());

    $order->forceFill([
        'cost_settlement_status' => ProductionCostSettlementStatus::Reversed->value,
    ])->save();

    expect(fn () => app(ProductionOrderCostSettlementService::class)->settle($order->fresh(), $user->id))
        ->toThrow(RuntimeException::class, 'Invalid production cost settlement transition');

    expect(ValueEntry::query()->where('reversal_of_value_entry_id', $expectedMaterial->id)->exists())->toBeFalse()
        ->and(ProductionOutputCostAllocation::query()->where('production_order_id', $order->id)->exists())->toBeFalse()
        ->and($order->fresh()->cost_settlement_status)->toBe(ProductionCostSettlementStatus::Reversed);
});

it('calculates detailed production variances and posts eligible variance through value entries', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    [$order,, $workCenter, $location] = createMinimalSettlementOrders($user, 'VARIANCE');
    createPostingAccountsForOrder($order, $location);
    $componentItem = Item::factory()->create([
        'item_code' => 'RM-VARIANCE',
        'unit_cost' => 10,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
    ]);
    $order->components()->create([
        'line_number' => 10000,
        'item_id' => $componentItem->id,
        'description' => 'Variance component',
        'unit_of_measure_code' => 'PCS',
        'quantity_per' => 10,
        'expected_quantity' => 10,
        'expected_quantity_base' => 10,
        'remaining_quantity' => 0,
        'unit_cost' => 10,
    ]);
    $order->routingLines()->create([
        'line_number' => 10000,
        'operation_no' => '10',
        'work_center_id' => $workCenter->id,
        'setup_time' => 0,
        'run_time' => 10,
        'expected_output_quantity' => 1,
        'direct_cost' => 5,
        'overhead_cost' => 1,
        'actual_run_time' => 12,
        'status' => 'COMPLETED',
    ]);
    app(ExpectedManufacturingCostService::class)->calculate($order, userId: $user->id);
    manufacturingValueEntryForVariance($order, $componentItem, $location, ManufacturingCostComponent::DirectMaterial, -12, 132, 10000);
    manufacturingValueEntryForVariance($order, $order->item, $location, ManufacturingCostComponent::DirectCapacity, 12, 72, 10000);
    manufacturingValueEntryForVariance($order, $order->item, $location, ManufacturingCostComponent::CapacityOverhead, 12, 18, 10000);
    ItemLedgerEntry::query()->create([
        'entry_number' => 999001,
        'item_id' => $order->item_id,
        'location_id' => $location->id,
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'quantity' => 1,
        'remaining_quantity' => 1,
        'posting_date' => now(),
        'entry_date' => now(),
        'document_type' => 'PRODUCTION_ORDER',
        'document_number' => $order->document_number,
        'document_line_number' => 10000,
        'cost_amount_actual' => 100,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
        'source_id' => $order->id,
        'source_type' => ProductionOrder::class,
    ]);

    $calculations = app(ProductionVarianceCalculationService::class)->calculate($order, userId: $user->id);
    $priceVariance = $calculations->firstWhere('variance_type', ProductionVarianceType::MaterialPrice);
    $quantityVariance = $calculations->firstWhere('variance_type', ProductionVarianceType::MaterialQuantity);
    $posted = app(ProductionVarianceValueEntryService::class)->postCalculation($priceVariance, $user->id);

    expect($priceVariance)->not->toBeNull()
        ->and((float) $priceVariance->variance_amount)->toBe(12.0)
        ->and($quantityVariance)->not->toBeNull()
        ->and((float) $quantityVariance->variance_amount)->toBe(20.0)
        ->and($posted)->not->toBeNull()
        ->and($posted?->cost_component)->toBe(ManufacturingCostComponent::MaterialPriceVariance->value)
        ->and($priceVariance->fresh()->posted_value_entry_id)->toBe($posted?->id);
});

it('gates production costing actions with granular permissions', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    [$order] = createMinimalSettlementOrders($user, 'PERMS');

    expect($user->can('calculateExpectedCost', $order))->toBeFalse()
        ->and($user->can('settleProductionCost', $order))->toBeFalse()
        ->and($user->can('adjustProductionCost', $order))->toBeFalse()
        ->and($user->can('reverseProductionCost', $order))->toBeFalse();

    foreach ([
        'manufacturing.production_cost.calculate_expected',
        'manufacturing.production_cost.settle',
        'manufacturing.production_cost.adjust',
        'manufacturing.production_cost.reverse',
    ] as $permission) {
        Permission::query()->firstOrCreate(['name' => $permission, 'guard_name' => 'web']);
    }

    $user->givePermissionTo([
        'manufacturing.production_cost.calculate_expected',
        'manufacturing.production_cost.settle',
        'manufacturing.production_cost.adjust',
        'manufacturing.production_cost.reverse',
    ]);

    expect($user->can('calculateExpectedCost', $order))->toBeTrue()
        ->and($user->can('settleProductionCost', $order))->toBeTrue()
        ->and($user->can('adjustProductionCost', $order))->toBeTrue()
        ->and($user->can('reverseProductionCost', $order))->toBeTrue();
});

it('manufacturing cost reconcile exposes enhanced classifications without writing data', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);
    [$order] = createMinimalSettlementOrders($user, 'RECONCILE2');
    $before = [
        'value_entries' => ValueEntry::query()->count(),
        'variance_calculations' => ProductionVarianceCalculation::query()->count(),
    ];

    expect(Artisan::call('biwms:manufacturing-cost-reconcile', [
        '--production-order' => $order->document_number,
        '--json' => true,
    ]))->toBe(0);

    $report = json_decode(trim(Artisan::output()), true);

    expect($report['findings'])->toHaveKeys([
        'expected_manufacturing_cost_missing',
        'expected_material_cost_uncleared',
        'material_price_variance_mismatch',
        'missing_manufacturing_posting_account',
        'manufacturing_gl_without_value_entry',
    ])
        ->and(ValueEntry::query()->count())->toBe($before['value_entries'])
        ->and(ProductionVarianceCalculation::query()->count())->toBe($before['variance_calculations']);
});

function createMinimalSettlementOrders(User $user, string $suffix = 'READY'): array
{
    $userId = $user->getKey() ?: User::query()->value('id') ?: User::factory()->create()->getKey();
    $businessGroup = GeneralBusinessPostingGroup::create([
        'code' => 'MFG-'.$suffix,
        'description' => 'Manufacturing '.$suffix,
    ]);
    $productGroup = GeneralProductPostingGroup::create([
        'code' => 'MFG-'.$suffix,
        'description' => 'Manufacturing '.$suffix,
    ]);
    $inventoryGroup = InventoryPostingGroup::create([
        'code' => 'MFG-'.$suffix,
        'description' => 'Manufacturing '.$suffix,
    ]);
    $location = Location::factory()->create(['code' => 'MAIN-'.$suffix]);
    $item = Item::factory()->create([
        'item_code' => 'FG-'.$suffix,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
    ]);
    $otherItem = Item::factory()->create([
        'item_code' => 'FG-'.$suffix.'-OTHER',
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
    ]);
    $workCenter = WorkCenter::factory()->create([
        'direct_unit_cost' => 0,
        'overhead_rate' => 0,
        'indirect_cost_percent' => 0,
    ]);

    GeneralPostingSetup::create([
        'general_business_posting_group_id' => $businessGroup->id,
        'general_product_posting_group_id' => $productGroup->id,
    ]);

    $order = ProductionOrder::query()->create([
        'document_number' => 'PO-'.$suffix,
        'status' => ProductionOrderStatus::RELEASED,
        'item_id' => $item->id,
        'description' => 'Settlement '.$suffix,
        'quantity' => 1,
        'quantity_base' => 1,
        'starting_date_time' => now(),
        'general_business_posting_group_id' => $businessGroup->id,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
        'costing_method' => 'FIFO',
        'unit_cost' => 0,
        'cost_rollup' => 0,
        'flushing_method' => 'MANUAL',
        'location_code' => $location->code,
        'created_by' => $userId,
    ]);

    $otherOrder = ProductionOrder::query()->create([
        'document_number' => 'PO-'.$suffix.'-OTHER',
        'status' => ProductionOrderStatus::RELEASED,
        'item_id' => $otherItem->id,
        'description' => 'Other settlement '.$suffix,
        'quantity' => 1,
        'quantity_base' => 1,
        'starting_date_time' => now(),
        'general_business_posting_group_id' => $businessGroup->id,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
        'costing_method' => 'FIFO',
        'unit_cost' => 0,
        'cost_rollup' => 0,
        'flushing_method' => 'MANUAL',
        'location_code' => $location->code,
        'created_by' => $userId,
    ]);

    return [$order, $otherOrder, $workCenter, $location];
}

function createPostingAccountsForOrder(ProductionOrder $order, Location $location): void
{
    $inventoryAccount = ChartOfAccount::factory()->create(['account_number' => 'INV-'.$order->id]);
    $wipAccount = ChartOfAccount::factory()->create(['account_number' => 'WIP-'.$order->id]);
    $appliedAccount = ChartOfAccount::factory()->create(['account_number' => 'APP-'.$order->id]);
    $varianceAccount = ChartOfAccount::factory()->create(['account_number' => 'VAR-'.$order->id]);

    InventoryPostingSetup::query()->updateOrCreate([
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
        'location_id' => $location->id,
    ], [
        'inventory_account_id' => $inventoryAccount->id,
        'wip_account_id' => $wipAccount->id,
    ]);

    GeneralPostingSetup::query()->updateOrCreate([
        'general_business_posting_group_id' => $order->general_business_posting_group_id,
        'general_product_posting_group_id' => $order->general_product_posting_group_id,
    ], [
        'direct_cost_applied_account_id' => $appliedAccount->id,
        'overhead_applied_account_id' => $appliedAccount->id,
        'inventory_adj_account_id' => $appliedAccount->id,
        'material_variance_account_id' => $varianceAccount->id,
        'capacity_variance_account_id' => $varianceAccount->id,
        'capacity_overhead_variance_account_id' => $varianceAccount->id,
        'manufacturing_overhead_variance_account_id' => $varianceAccount->id,
    ]);
}

function manufacturingValueEntryForVariance(
    ProductionOrder $order,
    Item $item,
    Location $location,
    ManufacturingCostComponent $component,
    float $quantity,
    float $amount,
    int $lineNumber,
    int $itemLedgerEntryType = 6
): ValueEntry {
    return ValueEntry::query()->create([
        'entry_no' => (ValueEntry::max('entry_no') ?? 0) + 1,
        'item_ledger_entry_type' => $itemLedgerEntryType,
        'item_no' => $item->item_code,
        'location_code' => $location->code,
        'posting_date' => now()->toDateString(),
        'document_type' => 'PRODUCTION_ORDER',
        'document_no' => $order->document_number,
        'document_line_no' => $lineNumber,
        'quantity' => $quantity,
        'valued_quantity' => $quantity,
        'cost_component' => $component->value,
        'value_entry_state' => 'actual',
        'cost_amount_actual' => $amount,
        'source_module' => 'manufacturing',
        'source_id' => $order->id,
        'source_no' => (string) $order->id,
        'source_line_no' => $lineNumber,
        'production_order_no' => $order->document_number,
        'expected_cost' => false,
    ]);
}

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
