<?php

declare(strict_types=1);

use App\Enums\AccountCategory;
use App\Enums\AccountStructuralType;
use App\Enums\CostingMethod;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemLedgerEntryType;
use App\Enums\ItemType;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\CostAdjustmentBatch;
use App\Models\CostingPeriod;
use App\Models\Customer;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralLedgerSetup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\GlEntry;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemApplicationEntry;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\PostedSalesInvoice;
use App\Models\PostedSalesInvoiceLine;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseInvoiceLine;
use App\Models\User;
use App\Models\ValueEntry;
use App\Models\Vendor;
use App\Services\Inventory\CostAdjustmentService;
use App\Services\Inventory\ExpectedCostClearingService;
use App\Services\Inventory\ItemApplicationService;
use App\Services\Inventory\ReturnCostApplicationService;
use App\Services\Inventory\StockMovementService;
use App\Services\Inventory\ValueEntryAccountingOrchestrator;
use App\Support\DecimalMath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

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

it('applies outbound inventory fifo across multiple inbound layers and is idempotent', function (): void {
    $fixture = phase1cFixture(CostingMethod::FIFO);
    $firstInbound = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::PURCHASE, 5, 50, 'IN-001', '2026-01-01');
    $secondInbound = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::PURCHASE, 5, 100, 'IN-002', '2026-01-02');
    $outbound = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::SALE, -7, 0, 'OUT-001', '2026-01-03');

    $applications = app(ItemApplicationService::class)->applyOutbound($outbound, 'test_fifo');
    $retryApplications = app(ItemApplicationService::class)->applyOutbound($outbound->fresh(), 'test_fifo_retry');

    expect($applications)->toHaveCount(2)
        ->and($retryApplications)->toHaveCount(2)
        ->and(ItemApplicationEntry::query()->count())->toBe(2)
        ->and((float) $firstInbound->fresh()->remaining_quantity)->toBe(0.0)
        ->and((float) $secondInbound->fresh()->remaining_quantity)->toBe(3.0)
        ->and((float) $outbound->fresh()->cost_amount_actual)->toBe(90.0)
        ->and(ItemApplicationEntry::query()->orderBy('id')->pluck('inbound_item_ledger_entry_id')->all())
        ->toBe([$firstInbound->id, $secondInbound->id]);
});

it('applies average cost across open layers', function (): void {
    $fixture = phase1cFixture(CostingMethod::AVERAGE);
    phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::PURCHASE, 10, 100, 'AVG-IN-001', '2026-01-01');
    phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::PURCHASE, 10, 300, 'AVG-IN-002', '2026-01-02');
    $outbound = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::SALE, -5, 0, 'AVG-OUT-001', '2026-01-03');

    app(ItemApplicationService::class)->applyOutbound($outbound, 'test_average');

    expect((float) $outbound->fresh()->cost_amount_actual)->toBe(100.0)
        ->and((float) ItemApplicationEntry::query()->sum('cost_amount'))->toBe(100.0);
});

it('uses exact original cost for linked sales returns', function (): void {
    $fixture = phase1cFixture(CostingMethod::FIFO);
    $inbound = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::PURCHASE, 10, 250, 'RET-IN-001', '2026-01-01');
    $outbound = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::SALE, -4, 0, 'RET-OUT-001', '2026-01-02');
    app(ItemApplicationService::class)->applyOutbound($outbound, 'test_return_original');

    $postedInvoice = PostedSalesInvoice::query()->create([
        'document_number' => 'PSI-RET-001',
        'customer_id' => Customer::factory()->create()->id,
        'customer_name' => 'Test Customer',
        'posting_date' => '2026-01-02',
        'document_date' => '2026-01-02',
        'due_date' => '2026-01-31',
        'total_amount' => 100,
        'total_vat' => 0,
        'grand_total' => 100,
        'remaining_amount' => 100,
        'status' => 'posted',
        'posted_by' => User::factory()->create()->id,
        'posted_at' => now(),
    ]);

    PostedSalesInvoiceLine::query()->create([
        'posted_sales_invoice_id' => $postedInvoice->id,
        'line_number' => 10000,
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'item_description' => $fixture['item']->description,
        'quantity' => 4,
        'quantity_base' => 4,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'unit_price' => 25,
        'line_amount' => 100,
        'line_total' => 100,
        'item_ledger_entry_id' => $outbound->id,
        'posting_date' => '2026-01-02',
    ]);

    $returnEntry = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::SALE, 2, 0, 'SCM-RET-001', '2026-01-04');
    $valueEntry = app(ReturnCostApplicationService::class)->applyExactOrFallbackCost($returnEntry, $outbound);

    expect((float) $returnEntry->fresh()->cost_amount_actual)->toBe(50.0)
        ->and((float) $valueEntry?->cost_amount_actual)->toBe(50.0)
        ->and($valueEntry?->accounting_metadata['phase_1c_return_cost_source'])->toBe('exact_original_application')
        ->and((float) $inbound->fresh()->remaining_quantity)->toBe(6.0);
});

it('clears posted expected cost partially and does not clear when expected gl is disabled', function (): void {
    config(['accounts.post_expected_inventory_cost_to_gl' => true]);
    $fixture = phase1cFixture(CostingMethod::FIFO);
    $receiptEntry = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::PURCHASE, 10, 0, 'PR-EXP-001', '2026-01-01', expectedCost: 100);
    $expectedEntry = ValueEntry::query()->where('item_ledger_entry_no', $receiptEntry->entry_number)->firstOrFail();
    app(ValueEntryAccountingOrchestrator::class)->post($expectedEntry);

    [$invoice, $line] = phase1cPurchaseInvoiceAndLine($fixture, 'PI-EXP-001', 4, 60);
    $clearing = app(ExpectedCostClearingService::class)->clearForActualPurchaseInvoice($expectedEntry->fresh(), $invoice, $line, 4, 60);
    $retryClearing = app(ExpectedCostClearingService::class)->clearForActualPurchaseInvoice($expectedEntry->fresh(), $invoice, $line, 4, 60);

    expect($clearing)->not->toBeNull()
        ->and($retryClearing?->id)->toBe($clearing?->id)
        ->and((float) $clearing?->cost_amount_expected)->toBe(-40.0)
        ->and($clearing?->gl_posted)->toBeTrue()
        ->and(ValueEntry::query()->where('value_entry_state', 'clearing')->count())->toBe(1);

    config(['accounts.post_expected_inventory_cost_to_gl' => false]);
    [$secondInvoice, $secondLine] = phase1cPurchaseInvoiceAndLine($fixture, 'PI-EXP-002', 2, 30);

    expect(app(ExpectedCostClearingService::class)->clearForActualPurchaseInvoice($expectedEntry->fresh(), $secondInvoice, $secondLine, 2, 30))->toBeNull();
});

it('posts transfer shipment and receipt through explicit in-transit account and fails when setup is missing', function (): void {
    $fixture = phase1cFixture(CostingMethod::FIFO, createDestination: true);
    phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::PURCHASE, 10, 100, 'TR-IN-001', '2026-01-01');

    $entries = app(StockMovementService::class)->transfer(
        item: $fixture['item'],
        sourceLocation: $fixture['location'],
        destinationLocation: $fixture['destinationLocation'],
        quantityBase: 4,
        documentNumber: 'TR-1C-001',
        postingDate: '2026-01-05',
    );

    expect(ValueEntry::query()->where('document_no', 'TR-1C-001')->where('gl_posted', true)->count())->toBe(2)
        ->and(GlEntry::query()->where('document_number', 'TR-1C-001')->count())->toBe(4)
        ->and((float) GlEntry::query()->where('document_number', 'TR-1C-001')->sum('debit_amount'))->toBe(80.0)
        ->and((float) GlEntry::query()->where('document_number', 'TR-1C-001')->sum('credit_amount'))->toBe(80.0)
        ->and((float) $entries['source']->fresh()->cost_amount_actual)->toBe(40.0);

    $missingSetupFixture = phase1cFixture(CostingMethod::FIFO, createDestination: true, includeInTransit: false);
    phase1cItemLedgerEntry($missingSetupFixture, ItemLedgerEntryType::PURCHASE, 5, 50, 'TR-IN-002', '2026-01-01');

    expect(fn () => app(StockMovementService::class)->transfer(
        item: $missingSetupFixture['item'],
        sourceLocation: $missingSetupFixture['location'],
        destinationLocation: $missingSetupFixture['destinationLocation'],
        quantityBase: 1,
        documentNumber: 'TR-1C-MISSING',
        postingDate: '2026-01-05',
    ))->toThrow(RuntimeException::class, 'Inventory in-transit account missing');
});

it('creates cost adjustment value entries from inbound cost changes and protects closed costing periods', function (): void {
    $fixture = phase1cFixture(CostingMethod::FIFO);
    $inbound = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::PURCHASE, 10, 100, 'ADJ-IN-001', '2026-01-01');
    $outbound = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::SALE, -4, 0, 'ADJ-OUT-001', '2026-01-02');
    app(ItemApplicationService::class)->applyOutbound($outbound, 'test_adjustment');

    $dryRun = app(CostAdjustmentService::class)->adjustInboundCost($inbound, 150, 'Invoice cost increase', dryRun: true);
    $posted = app(CostAdjustmentService::class)->adjustInboundCost($inbound, 150, 'Invoice cost increase', dryRun: false);
    $retry = app(CostAdjustmentService::class)->adjustInboundCost($inbound->fresh(), 150, 'Invoice cost increase', dryRun: false);

    expect((float) $dryRun['adjustments'][0]['adjustment_amount'])->toBe(20.0)
        ->and((float) $dryRun['summary']['remaining_inventory_delta'])->toBe(30.0)
        ->and($posted['adjustments'][0])->toBeInstanceOf(ValueEntry::class)
        ->and($retry['adjustments'])->toHaveCount(0)
        ->and(ValueEntry::query()->where('value_entry_state', 'adjustment')->count())->toBe(2);

    CostingPeriod::query()->create([
        'start_date' => '2026-02-01',
        'end_date' => '2026-02-28',
        'name' => 'Closed February',
        'is_closed' => true,
        'closed_at' => now(),
    ]);

    $closedInbound = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::PURCHASE, 1, 10, 'ADJ-CLOSED-001', '2026-02-10');

    expect(fn () => app(CostAdjustmentService::class)->adjustInboundCost($closedInbound, 12, 'Closed period test', dryRun: false))
        ->toThrow(RuntimeException::class, 'Cost adjustment is not allowed');
});

it('allocates fifo layer revaluation between consumed applications and remaining inventory', function (): void {
    $fixture = phase1cFixture(CostingMethod::FIFO);
    $inbound = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::PURCHASE, 25000, 220000, 'OPEN-20260807170407', '2026-08-07');
    $originalValueEntry = ValueEntry::query()
        ->where('item_ledger_entry_no', $inbound->entry_number)
        ->where('value_entry_state', 'actual')
        ->firstOrFail();

    for ($index = 1; $index <= 3; $index++) {
        $outbound = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::CONSUMPTION, -6.563808, 0, "PROD-CONS-{$index}", '2026-08-08');
        app(ItemApplicationService::class)->applyOutbound($outbound, 'production_consumption');
        $outbound->refresh();
        ValueEntry::query()
            ->where('item_ledger_entry_no', $outbound->entry_number)
            ->where('value_entry_state', 'actual')
            ->update([
                'cost_amount_actual' => $outbound->cost_amount_actual,
                'cost_amount_actual_acy' => $outbound->cost_amount_actual,
                'unit_cost' => '8.80000000',
            ]);
    }

    $adjustmentCountBefore = ValueEntry::query()->where('value_entry_state', 'adjustment')->count();
    $dryRun = app(CostAdjustmentService::class)->adjustInboundCost($inbound->fresh(), 250000, 'Correct opening FIFO layer cost', dryRun: true);

    expect(ValueEntry::query()->where('value_entry_state', 'adjustment')->count())->toBe($adjustmentCountBefore)
        ->and((float) $dryRun['summary']['total_delta'])->toBe(30000.0)
        ->and((float) $dryRun['summary']['consumed_quantity'])->toBe(19.691424)
        ->and((float) $dryRun['summary']['remaining_quantity'])->toBe(24980.308576)
        ->and((float) $dryRun['summary']['consumed_delta'])->toBe(23.6298)
        ->and((float) $dryRun['summary']['remaining_inventory_delta'])->toBe(29976.3702)
        ->and((float) $dryRun['adjustments'][0]['adjustment_amount'])->toBe(7.8766)
        ->and((float) $dryRun['adjustments'][1]['adjustment_amount'])->toBe(7.8766)
        ->and((float) $dryRun['adjustments'][2]['adjustment_amount'])->toBe(7.8766);

    $posted = app(CostAdjustmentService::class)->adjustInboundCost($inbound->fresh(), 250000, 'Correct opening FIFO layer cost', dryRun: false);
    $retry = app(CostAdjustmentService::class)->adjustInboundCost($inbound->fresh(), 250000, 'Correct opening FIFO layer cost', dryRun: false);

    $applicationAdjustments = ValueEntry::query()
        ->where('document_no', $posted['batch']->batch_number)
        ->where('document_type', 'COST_ADJUSTMENT')
        ->where('value_entry_state', 'adjustment')
        ->get();
    $remainingRevaluation = ValueEntry::query()
        ->where('document_no', $posted['batch']->batch_number)
        ->where('document_type', 'INVENTORY_REVALUATION')
        ->where('value_entry_state', 'adjustment')
        ->firstOrFail();

    expect($posted['adjustments'])->toHaveCount(4)
        ->and($retry['adjustments'])->toHaveCount(0)
        ->and((float) $inbound->fresh()->cost_amount_actual)->toBe(250000.0)
        ->and((float) $originalValueEntry->fresh()->cost_amount_actual)->toBe(220000.0)
        ->and($applicationAdjustments)->toHaveCount(3)
        ->and((float) $applicationAdjustments->sum('cost_amount_actual'))->toBe(23.6298)
        ->and((float) $remainingRevaluation->cost_amount_actual)->toBe(29976.3702)
        ->and((float) DecimalMath::add($applicationAdjustments->sum('cost_amount_actual'), $remainingRevaluation->cost_amount_actual, 4))->toBe(30000.0)
        ->and($applicationAdjustments->every(fn (ValueEntry $entry): bool => $entry->gl_posted === true))->toBeTrue()
        ->and($remainingRevaluation->gl_posted)->toBeTrue()
        ->and((float) GlEntry::query()->where('document_number', $posted['batch']->batch_number)->sum('debit_amount'))
        ->toBe((float) GlEntry::query()->where('document_number', $posted['batch']->batch_number)->sum('credit_amount'))
        ->and(ValueEntry::query()->where('document_no', $posted['batch']->batch_number)->where('value_entry_state', 'adjustment')->count())->toBe(4);

    Artisan::call('biwms:costing-reconcile', ['--json' => true]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($report['revaluation_batch_missing_inventory_adjustment'])->toBeEmpty()
        ->and($report['cost_adjustment_allocation_mismatches'])->toBeEmpty()
        ->and($report['duplicate_adjustment_value_entries'])->toBeEmpty();

    Artisan::call('biwms:inventory-reconcile', ['--json' => true]);
    $inventoryReport = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect($inventoryReport['value_entry_mismatches'])->toBeEmpty();
});

it('supports negative and fully unconsumed inventory layer revaluation without consumed adjustments', function (): void {
    $fixture = phase1cFixture(CostingMethod::FIFO);
    $inbound = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::PURCHASE, 100, 1000, 'NEG-REVAL-001', '2026-01-03');

    $posted = app(CostAdjustmentService::class)->adjustInboundCost($inbound, 900, 'Decrease layer cost', dryRun: false);

    $revaluation = ValueEntry::query()
        ->where('document_no', $posted['batch']->batch_number)
        ->where('document_type', 'INVENTORY_REVALUATION')
        ->firstOrFail();

    expect($posted['adjustments'])->toHaveCount(1)
        ->and((float) $posted['summary']['consumed_delta'])->toBe(0.0)
        ->and((float) $posted['summary']['remaining_inventory_delta'])->toBe(-100.0)
        ->and((float) $revaluation->cost_amount_actual)->toBe(-100.0)
        ->and($revaluation->gl_posted)->toBeTrue()
        ->and((float) $inbound->fresh()->cost_amount_actual)->toBe(900.0);
});

it('does not duplicate adjustment when outbound economic value is already corrected', function (): void {
    $fixture = phase1cFixture(CostingMethod::FIFO);
    $inbound = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::PURCHASE, 25000, 220000, 'PROD-B-IN', '2026-08-07');
    $outbound = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::CONSUMPTION, -6.563808, 0, 'PROD-00005', '2026-08-08');
    $applications = app(ItemApplicationService::class)->applyOutbound($outbound, 'production_consumption');
    ValueEntry::query()
        ->where('item_ledger_entry_no', $outbound->entry_number)
        ->where('value_entry_state', 'actual')
        ->update(['cost_amount_actual' => '65.6381', 'cost_amount_actual_acy' => '65.6381']);

    $batchCountBeforeDryRun = CostAdjustmentBatch::query()->count();
    $valueEntryCountBeforeDryRun = ValueEntry::query()->count();
    $dryRun = app(CostAdjustmentService::class)->adjustInboundCost($inbound->fresh(), 250000, 'Correct layer cost', dryRun: true);
    $dryRunBatch = $dryRun['batch'];
    expect($dryRunBatch->exists)->toBeFalse()
        ->and(CostAdjustmentBatch::query()->count())->toBe($batchCountBeforeDryRun)
        ->and(ValueEntry::query()->count())->toBe($valueEntryCountBeforeDryRun);

    $posted = app(CostAdjustmentService::class)->adjustInboundCost($inbound->fresh(), 250000, 'Correct layer cost', dryRun: false);
    $remainingRevaluation = ValueEntry::query()
        ->where('document_no', $posted['batch']->batch_number)
        ->where('document_type', 'INVENTORY_REVALUATION')
        ->firstOrFail();

    expect((float) $applications[0]->cost_amount)->toBe(57.7615)
        ->and((float) $dryRun['adjustments'][0]['current_economic_cost'])->toBe(65.6381)
        ->and((float) $dryRun['adjustments'][0]['target_economic_cost'])->toBe(65.6381)
        ->and((float) $dryRun['adjustments'][0]['outstanding_adjustment_required'])->toBe(0.0)
        ->and((float) $dryRun['summary']['consumed_layer_delta'])->toBe(7.8766)
        ->and((float) $dryRun['summary']['pre_existing_economic_delta'])->toBe(7.8766)
        ->and((float) $dryRun['summary']['required_new_adjustment_delta'])->toBe(29992.1234)
        ->and((float) $dryRun['summary']['posted_new_adjustment_delta'])->toBe(29992.1234)
        ->and((float) $posted['summary']['consumed_delta'])->toBe(0.0)
        ->and((float) $posted['summary']['remaining_inventory_delta'])->toBe(29992.1234)
        ->and((float) $remainingRevaluation->cost_amount_actual)->toBe(29992.1234)
        ->and(ValueEntry::query()->where('document_no', $posted['batch']->batch_number)->where('document_type', 'COST_ADJUSTMENT')->count())->toBe(0)
        ->and(ValueEntry::query()->where('document_no', $posted['batch']->batch_number)->where('document_type', 'INVENTORY_REVALUATION')->count())->toBe(1);
});

it('allocates sodium saccharine revaluation around pre-existing outbound economic uplift', function (): void {
    $fixture = phase1cFixture(CostingMethod::FIFO);
    $inbound = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::PURCHASE, 25000, 220000, 'PROD-SODIUM-IN', '2026-08-07');

    foreach (['PROD-00001', 'PROD-00003', 'PROD-00005'] as $documentNumber) {
        $outbound = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::CONSUMPTION, -6.563808, 0, $documentNumber, '2026-08-08');
        app(ItemApplicationService::class)->applyOutbound($outbound, 'production_consumption');
        $outbound->refresh();
        ValueEntry::query()
            ->where('item_ledger_entry_no', $outbound->entry_number)
            ->where('value_entry_state', 'actual')
            ->update(['cost_amount_actual' => $outbound->cost_amount_actual, 'cost_amount_actual_acy' => $outbound->cost_amount_actual]);

        if ($documentNumber === 'PROD-00005') {
            ValueEntry::query()
                ->where('item_ledger_entry_no', $outbound->entry_number)
                ->where('value_entry_state', 'actual')
                ->update(['cost_amount_actual' => '65.6381', 'cost_amount_actual_acy' => '65.6381']);
        }
    }

    $posted = app(CostAdjustmentService::class)->adjustInboundCost($inbound->fresh(), 250000, 'Correct opening FIFO layer cost', dryRun: false);
    $applicationAdjustments = ValueEntry::query()
        ->where('document_no', $posted['batch']->batch_number)
        ->where('document_type', 'COST_ADJUSTMENT')
        ->where('value_entry_state', 'adjustment')
        ->get();
    $remainingRevaluation = ValueEntry::query()
        ->where('document_no', $posted['batch']->batch_number)
        ->where('document_type', 'INVENTORY_REVALUATION')
        ->firstOrFail();

    Artisan::call('biwms:costing-reconcile', ['--json' => true]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    Artisan::call('biwms:inventory-reconcile', ['--json' => true]);
    $inventoryReport = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

    expect((float) $posted['summary']['raw_layer_delta'])->toBe(30000.0)
        ->and((float) $posted['summary']['consumed_layer_delta'])->toBe(23.6298)
        ->and((float) $posted['summary']['pre_existing_economic_delta'])->toBe(7.8766)
        ->and((float) $posted['summary']['consumed_delta'])->toBe(15.7532)
        ->and((float) $posted['summary']['remaining_inventory_delta'])->toBe(29976.3702)
        ->and((float) $posted['summary']['required_new_adjustment_delta'])->toBe(29992.1234)
        ->and((float) $posted['summary']['posted_new_adjustment_delta'])->toBe(29992.1234)
        ->and($applicationAdjustments)->toHaveCount(2)
        ->and((float) $applicationAdjustments->sum('cost_amount_actual'))->toBe(15.7532)
        ->and((float) $remainingRevaluation->cost_amount_actual)->toBe(29976.3702)
        ->and($report['cost_adjustment_allocation_mismatches'])->toBeEmpty()
        ->and($inventoryReport['value_entry_mismatches'])->toBeEmpty();
});

it('still reports genuinely missing cost adjustment allocation', function (): void {
    $fixture = phase1cFixture(CostingMethod::FIFO);
    $inbound = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::PURCHASE, 10, 100, 'MISSING-ADJ-IN', '2026-01-01');
    $outbound = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::SALE, -4, 0, 'MISSING-ADJ-OUT', '2026-01-02');
    app(ItemApplicationService::class)->applyOutbound($outbound, 'missing_adjustment_test');

    $batch = CostAdjustmentBatch::query()->create([
        'batch_number' => 'COSTADJ-MISSING-ALLOCATION',
        'source_type' => ItemLedgerEntry::class,
        'source_id' => $inbound->id,
        'reason' => 'Incomplete test batch',
        'dry_run' => false,
        'run_at' => now(),
        'metadata' => [
            'old_total_cost' => '100.0000',
            'new_total_cost' => '150.0000',
            'delta' => '50.0000',
            'raw_layer_delta' => '50.0000',
            'posting_date' => '2026-01-02',
        ],
    ]);

    Artisan::call('biwms:costing-reconcile', ['--json' => true]);
    $report = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);
    $finding = collect($report['cost_adjustment_allocation_mismatches'])
        ->firstWhere('batch_number', $batch->batch_number);

    expect($finding)->not->toBeNull()
        ->and((float) $finding['target_delta'])->toBe(50.0)
        ->and((float) $finding['pre_existing_economic_delta'])->toBe(0.0)
        ->and((float) $finding['required_new_adjustment_delta'])->toBe(50.0)
        ->and((float) $finding['posted_new_adjustment_delta'])->toBe(0.0)
        ->and((float) $finding['difference'])->toBe(50.0);
});

it('nets adjustment and reversal chains before deciding whether another adjustment is required', function (): void {
    $fixture = phase1cFixture(CostingMethod::FIFO);
    $inbound = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::PURCHASE, 25000, 220000, 'PROD-C-IN', '2026-08-07');
    $outbound = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::CONSUMPTION, -6.563808, 0, 'PROD-00005-R', '2026-08-08');
    $application = app(ItemApplicationService::class)->applyOutbound($outbound, 'production_consumption')[0];
    ValueEntry::query()
        ->where('item_ledger_entry_no', $outbound->entry_number)
        ->where('value_entry_state', 'actual')
        ->update(['cost_amount_actual' => '65.6381', 'cost_amount_actual_acy' => '65.6381']);

    $correctionBatch = CostAdjustmentBatch::query()->create([
        'batch_number' => 'COSTADJ-CORRECTION-TEST',
        'source_type' => ItemLedgerEntry::class,
        'source_id' => $inbound->id,
        'reason' => 'Test correction chain',
        'dry_run' => false,
        'run_at' => now(),
        'metadata' => ['delta' => 0],
    ]);
    $adjustment = ValueEntry::query()->create([
        'entry_no' => (ValueEntry::max('entry_no') ?? 0) + 1,
        'item_ledger_entry_no' => $outbound->entry_number,
        'item_ledger_entry_type' => 6,
        'item_no' => $fixture['item']->item_code,
        'location_code' => $fixture['location']->code,
        'posting_date' => '2026-08-08',
        'document_type' => 'COST_ADJUSTMENT',
        'document_no' => $correctionBatch->batch_number,
        'document_line_no' => $application->id,
        'quantity' => 0,
        'valued_quantity' => 0,
        'value_entry_state' => 'adjustment',
        'cost_amount_actual' => '7.8766',
        'cost_amount_actual_acy' => '7.8766',
        'source_type' => CostAdjustmentBatch::class,
        'source_id' => $correctionBatch->id,
        'source_line_no' => $application->id,
        'source_module' => 'inventory',
        'cost_component' => 'cost_adjustment',
        'expected_cost' => false,
    ]);
    ValueEntry::query()->create([
        ...$adjustment->replicate()->toArray(),
        'entry_no' => (ValueEntry::max('entry_no') ?? 0) + 1,
        'value_entry_state' => 'reversal',
        'entry_type' => 'REVERSAL',
        'cost_amount_actual' => '-7.8766',
        'cost_amount_actual_acy' => '-7.8766',
        'reversal_of_value_entry_id' => $adjustment->id,
        'original_entry_no' => $adjustment->id,
    ]);

    $dryRun = app(CostAdjustmentService::class)->adjustInboundCost($inbound->fresh(), 250000, 'Correct layer cost', dryRun: true);
    $posted = app(CostAdjustmentService::class)->adjustInboundCost($inbound->fresh(), 250000, 'Correct layer cost', dryRun: false);

    expect((float) $dryRun['adjustments'][0]['current_economic_cost'])->toBe(65.6381)
        ->and((float) $dryRun['adjustments'][0]['outstanding_adjustment_required'])->toBe(0.0)
        ->and(ValueEntry::query()->where('document_no', $posted['batch']->batch_number)->where('document_type', 'COST_ADJUSTMENT')->count())->toBe(0);
});

it('posts only incremental positive and negative revaluation deltas after an earlier correction', function (): void {
    $fixture = phase1cFixture(CostingMethod::FIFO);
    $inbound = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::PURCHASE, 25000, 220000, 'INCR-IN', '2026-08-07');
    $outbound = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::CONSUMPTION, -6.563808, 0, 'INCR-OUT', '2026-08-08');
    app(ItemApplicationService::class)->applyOutbound($outbound, 'production_consumption');

    $first = app(CostAdjustmentService::class)->adjustInboundCost($inbound->fresh(), 250000, 'Correct to 10', dryRun: false);
    $second = app(CostAdjustmentService::class)->adjustInboundCost($inbound->fresh(), 262500, 'Increase to 10.50', dryRun: false);
    $third = app(CostAdjustmentService::class)->adjustInboundCost($inbound->fresh(), 237500, 'Decrease to 9.50', dryRun: false);

    expect((float) $first['summary']['consumed_delta'])->toBe(7.8766)
        ->and((float) $second['summary']['consumed_delta'])->toBe(3.2819)
        ->and((float) $third['summary']['consumed_delta'])->toBe(-6.5638)
        ->and((float) $second['summary']['remaining_inventory_delta'])->toBe(12496.7181)
        ->and((float) $third['summary']['remaining_inventory_delta'])->toBe(-24993.4362);
});

it('refreshes existing unposted value entry from current item application state before gl posting', function (): void {
    $fixture = phase1cFixture(CostingMethod::FIFO);
    phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::PURCHASE, 10, 100, 'STALE-IN-001', '2026-01-01');
    $outbound = phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::SALE, -4, 0, 'STALE-OUT-001', '2026-01-02');

    $preExistingValueEntry = ValueEntry::query()
        ->where('item_ledger_entry_no', $outbound->entry_number)
        ->firstOrFail();

    expect((float) $preExistingValueEntry->cost_amount_actual)->toBe(0.0)
        ->and($preExistingValueEntry->gl_posted)->toBeFalse();

    app(ItemApplicationService::class)->applyOutbound($outbound, 'stale_refresh_test');
    app(ValueEntryAccountingOrchestrator::class)->postForItemLedgerEntry($outbound);

    $refreshedValueEntry = $preExistingValueEntry->fresh();

    expect((float) $outbound->fresh()->cost_amount_actual)->toBe(40.0)
        ->and((float) $refreshedValueEntry->cost_amount_actual)->toBe(40.0)
        ->and((float) $refreshedValueEntry->unit_cost)->toBe(10.0)
        ->and($refreshedValueEntry->gl_posted)->toBeTrue()
        ->and((float) GlEntry::query()->where('document_number', 'STALE-OUT-001')->sum('debit_amount'))->toBe(40.0)
        ->and((float) GlEntry::query()->where('document_number', 'STALE-OUT-001')->sum('credit_amount'))->toBe(40.0);
});

it('costing reconcile reports findings and exports json', function (): void {
    $fixture = phase1cFixture(CostingMethod::FIFO);
    phase1cItemLedgerEntry($fixture, ItemLedgerEntryType::SALE, -1, 10, 'UNAPPLIED-001', '2026-01-03');
    $exportPath = 'storage/app/reports/costing-reconcile-test.json';

    expect(Artisan::call('biwms:costing-reconcile', ['--json' => true, '--export' => $exportPath]))->toBe(0);

    $report = json_decode(trim(Artisan::output()), true);

    expect($report['outbound_without_applications'])->not->toBeEmpty()
        ->and(file_exists(base_path($exportPath)))->toBeTrue();
});

/**
 * @return array<string, mixed>
 */
function phase1cFixture(CostingMethod $costingMethod, bool $createDestination = false, bool $includeInTransit = true): array
{
    $inventoryAccount = phase1cAccount('13'.fake()->unique()->numerify('###'), 'Inventory', AccountCategory::INVENTORY);
    $inTransitAccount = phase1cAccount('14'.fake()->unique()->numerify('###'), 'Inventory In Transit', AccountCategory::INVENTORY);
    $cogsAccount = phase1cAccount('50'.fake()->unique()->numerify('###'), 'COGS', AccountCategory::COGS);
    $purchaseAccount = phase1cAccount('21'.fake()->unique()->numerify('###'), 'Purchase Clearing', AccountCategory::LIABILITY);
    $adjustmentAccount = phase1cAccount('51'.fake()->unique()->numerify('###'), 'Inventory Adjustment', AccountCategory::DIRECT_EXPENSE);
    $wipAccount = phase1cAccount('15'.fake()->unique()->numerify('###'), 'WIP', AccountCategory::INVENTORY);

    $businessGroup = GeneralBusinessPostingGroup::factory()->create(['code' => 'P1C'.fake()->unique()->numerify('###')]);
    $productGroup = GeneralProductPostingGroup::query()->create([
        'code' => 'P1C-PROD'.fake()->unique()->numerify('###'),
        'description' => 'Phase 1C Product',
        'blocked' => false,
        'auto_create_vat_prod_posting_group' => false,
    ]);
    $inventoryGroup = InventoryPostingGroup::query()->create([
        'code' => 'P1C-INV'.fake()->unique()->numerify('###'),
        'description' => 'Phase 1C Inventory',
        'blocked' => false,
    ]);
    $location = Location::factory()->create(['code' => 'P1CS'.fake()->unique()->numerify('##')]);
    $destinationLocation = $createDestination ? Location::factory()->create(['code' => 'P1CD'.fake()->unique()->numerify('##')]) : null;

    foreach (array_filter([$location, $destinationLocation]) as $setupLocation) {
        InventoryPostingSetup::query()->create([
            'location_id' => $setupLocation->id,
            'inventory_posting_group_id' => $inventoryGroup->id,
            'inventory_account_id' => $inventoryAccount->id,
            'inventory_account_interim_id' => $inventoryAccount->id,
            'inventory_in_transit_account_id' => $includeInTransit ? $inTransitAccount->id : null,
            'wip_account_id' => $wipAccount->id,
        ]);
    }

    GeneralPostingSetup::query()->create([
        'general_business_posting_group_id' => $businessGroup->id,
        'general_product_posting_group_id' => $productGroup->id,
        'sales_account_id' => phase1cAccount('40'.fake()->unique()->numerify('###'), 'Sales', AccountCategory::REVENUE)->id,
        'cogs_account_id' => $cogsAccount->id,
        'inventory_adj_account_id' => $adjustmentAccount->id,
        'inventory_account_id' => $inventoryAccount->id,
        'purchase_account_id' => $purchaseAccount->id,
        'direct_cost_applied_account_id' => phase1cAccount('52'.fake()->unique()->numerify('###'), 'Direct Cost Applied', AccountCategory::DIRECT_EXPENSE)->id,
        'overhead_applied_account_id' => phase1cAccount('53'.fake()->unique()->numerify('###'), 'Overhead Applied', AccountCategory::DIRECT_EXPENSE)->id,
        'blocked' => false,
    ]);

    $item = Item::factory()->create([
        'item_code' => 'P1C-ITEM'.fake()->unique()->numerify('###'),
        'description' => 'Phase 1C Item',
        'item_type' => ItemType::RAW_MATERIAL,
        'costing_method' => $costingMethod,
        'unit_cost' => 10,
        'standard_cost' => 12,
        'inventory' => 0,
        'location_id' => $location->id,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
    ]);
    $vendor = Vendor::factory()->create();

    return compact(
        'inventoryAccount',
        'inTransitAccount',
        'cogsAccount',
        'purchaseAccount',
        'businessGroup',
        'productGroup',
        'inventoryGroup',
        'location',
        'destinationLocation',
        'item',
        'vendor',
    );
}

function phase1cAccount(string $number, string $name, AccountCategory $category): ChartOfAccount
{
    return ChartOfAccount::query()->create([
        'account_number' => $number,
        'name' => $name,
        'structural_type' => AccountStructuralType::POSTING,
        'account_category' => $category,
        'balance' => 0,
        'direct_posting' => true,
        'blocked' => false,
        'income_balance' => $category->isBalanceSheet()
            ? IncomeBalanceType::BALANCE_SHEET
            : IncomeBalanceType::INCOME_STATEMENT,
    ]);
}

/**
 * @param  array<string, mixed>  $fixture
 */
function phase1cItemLedgerEntry(
    array $fixture,
    ItemLedgerEntryType $type,
    float $quantity,
    float $actualCost,
    string $documentNumber,
    string $postingDate,
    float $expectedCost = 0
): ItemLedgerEntry {
    $entry = ItemLedgerEntry::query()->create([
        'entry_type' => $type,
        'document_type' => str_starts_with($documentNumber, 'PR') ? 'PURCHASE_RECEIPT' : 'PHASE_1C',
        'document_number' => $documentNumber,
        'document_line_number' => 10000,
        'item_id' => $fixture['item']->id,
        'location_id' => $fixture['location']->id,
        'quantity' => $quantity,
        'remaining_quantity' => max(0, $quantity),
        'cost_amount_actual' => $actualCost,
        'cost_amount_expected' => $expectedCost,
        'purchase_amount_actual' => $actualCost,
        'general_business_posting_group_id' => $fixture['businessGroup']->id,
        'general_product_posting_group_id' => $fixture['productGroup']->id,
        'inventory_posting_group_id' => $fixture['inventoryGroup']->id,
        'posting_date' => $postingDate,
        'entry_date' => now(),
        'open' => $quantity > 0,
    ]);

    if ($quantity > 0) {
        $fixture['item']->increment('inventory', $quantity);
    }

    return $entry;
}

/**
 * @param  array<string, mixed>  $fixture
 * @return array{0: PurchaseInvoice, 1: PurchaseInvoiceLine}
 */
function phase1cPurchaseInvoiceAndLine(array $fixture, string $documentNumber, float $quantityBase, float $lineTotal): array
{
    $invoice = PurchaseInvoice::query()->create([
        'document_number' => $documentNumber,
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'posting_date' => '2026-01-05',
        'document_date' => '2026-01-05',
        'due_date' => '2026-02-05',
        'status' => 'approved',
        'total_amount' => $lineTotal,
        'total_vat' => 0,
        'grand_total' => $lineTotal,
        'remaining_amount' => $lineTotal,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
    ]);

    $line = PurchaseInvoiceLine::query()->create([
        'purchase_invoice_id' => $invoice->id,
        'line_number' => 10000,
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'item_description' => $fixture['item']->description,
        'general_product_posting_group_id' => $fixture['productGroup']->id,
        'inventory_posting_group_id' => $fixture['inventoryGroup']->id,
        'quantity' => $quantityBase,
        'quantity_base' => $quantityBase,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'unit_cost' => $lineTotal / $quantityBase,
        'unit_cost_lcy' => $lineTotal / $quantityBase,
        'line_total' => $lineTotal,
        'vat_percentage' => 0,
        'vat_amount' => 0,
        'vat_amount_lcy' => 0,
        'amount_including_vat' => $lineTotal,
        'amount_including_vat_lcy' => $lineTotal,
        'posting_date' => $invoice->posting_date,
    ]);

    return [$invoice, $line];
}
