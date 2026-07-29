<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemLedgerEntryType;
use App\Enums\JournalBatchStatus;
use App\Enums\ProductionOperationExecutionStatus;
use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionQualityDisposition;
use App\Enums\ProductionQualityInspectionStage;
use App\Enums\ProductionQualityResult;
use App\Enums\ProductionScrapPostingTreatment;
use App\Enums\ProductionScrapStage;
use App\Models\AccountingPeriod;
use App\Models\CapacityLedgerEntry;
use App\Models\ChartOfAccount;
use App\Models\Employee;
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
use App\Models\Manufacturing\ProductionDowntimeReason;
use App\Models\Manufacturing\ProductionOperationExecution;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionQualityCheckAttachment;
use App\Models\Manufacturing\ProductionScrapReason;
use App\Models\Manufacturing\WorkCenter;
use App\Models\NumberSeries;
use App\Models\Permission;
use App\Models\ProductionJournalBatch;
use App\Models\ProductionJournalLine;
use App\Models\ProductionJournalTemplate;
use App\Models\User;
use App\Models\ValueEntry;
use App\Services\Manufacturing\ProductionCompletionReadinessService;
use App\Services\Manufacturing\ProductionOperationExecutionService;
use App\Services\Manufacturing\ProductionProgressService;
use App\Services\Manufacturing\ProductionQualityAttachmentService;
use App\Services\Manufacturing\ProductionWorkQueueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Carbon::setTestNow('2026-07-29 08:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

test('released operations appear in work queue and non-released orders do not', function (): void {
    [$releasedOrder] = shopFloorProductionOrder(status: ProductionOrderStatus::RELEASED, documentNumber: 'PO-SFC-001');
    [$draftOrder] = shopFloorProductionOrder(status: ProductionOrderStatus::PLANNED, documentNumber: 'PO-SFC-002');

    $queue = app(ProductionWorkQueueService::class)->availableOperations();

    expect($queue->pluck('production_order_id')->all())
        ->toContain($releasedOrder->id)
        ->not->toContain($draftOrder->id);
});

test('operation execution transitions capture setup and run time', function (): void {
    [$order, $routingLine] = shopFloorProductionOrder();
    $operator = Employee::factory()->create();
    $service = app(ProductionOperationExecutionService::class);

    $execution = $service->getOrCreateExecution($order, $routingLine, $operator);
    $sameExecution = $service->getOrCreateExecution($order, $routingLine, $operator);

    expect($sameExecution->id)->toBe($execution->id);

    $execution = $service->startSetup($execution);
    Carbon::setTestNow('2026-07-29 08:30:00');
    $execution = $service->completeSetup($execution);
    $execution = $service->startRun($execution);
    Carbon::setTestNow('2026-07-29 10:30:00');
    $execution = $service->completeRun($execution, ['good_quantity' => 100, 'scrap_quantity' => 5]);

    expect($execution->status)->toBe(ProductionOperationExecutionStatus::Completed)
        ->and($execution->setup_seconds)->toBe(1800)
        ->and($execution->run_seconds)->toBe(7200)
        ->and($execution->labour_seconds)->toBe(9000)
        ->and($execution->machine_seconds)->toBe(0)
        ->and((float) $execution->good_quantity)->toBe(100.0)
        ->and($execution->events()->count())->toBeGreaterThanOrEqual(5);
});

test('operator cannot act on another operators restricted execution', function (): void {
    [$order, $routingLine] = shopFloorProductionOrder();
    $assignedEmployee = Employee::factory()->create();
    $assignedUser = User::factory()->create(['employee_id' => $assignedEmployee->id]);
    $otherEmployee = Employee::factory()->create();
    $otherUser = User::factory()->create(['employee_id' => $otherEmployee->id]);
    $service = app(ProductionOperationExecutionService::class);
    $execution = $service->getOrCreateExecution($order, $routingLine, $assignedEmployee);

    $started = $service->startSetup($execution, $assignedUser->id);

    expect($started->status)->toBe(ProductionOperationExecutionStatus::SetupStarted);

    expect(fn () => $service->pauseSetup($started->fresh(), $otherUser->id))
        ->toThrow(RuntimeException::class, 'not authorized');
});

test('all core state transitions and invalid terminal transitions are enforced', function (): void {
    [$order, $routingLine] = shopFloorProductionOrder();
    $service = app(ProductionOperationExecutionService::class);
    $execution = $service->getOrCreateExecution($order, $routingLine);

    $execution = $service->startSetup($execution);
    $execution = $service->pauseSetup($execution);
    $execution = $service->resumeSetup($execution);
    Carbon::setTestNow('2026-07-29 08:20:00');
    $execution = $service->completeSetup($execution);
    $execution = $service->startRun($execution);
    $execution = $service->pauseRun($execution);
    $execution = $service->resumeRun($execution);
    Carbon::setTestNow('2026-07-29 09:20:00');
    $execution = $service->completeRun($execution, ['good_quantity' => 10]);
    $execution = $service->submit($execution);
    $execution->update(['status' => ProductionOperationExecutionStatus::Posted, 'posted_at' => now()]);
    $execution = $service->reverse($execution->fresh(), 'Correction required.');

    expect($execution->status)->toBe(ProductionOperationExecutionStatus::Reversed);

    expect(fn () => $service->startRun($execution->fresh()))
        ->toThrow(RuntimeException::class, 'Cannot transition');
});

test('completion without start and invalid restart are blocked', function (): void {
    [$order, $routingLine] = shopFloorProductionOrder();
    $service = app(ProductionOperationExecutionService::class);
    $execution = $service->getOrCreateExecution($order, $routingLine);

    expect(fn () => $service->completeRun($execution))
        ->toThrow(RuntimeException::class, 'Cannot transition');

    $execution->update(['status' => ProductionOperationExecutionStatus::Posted]);

    expect(fn () => $service->startRun($execution->fresh()))
        ->toThrow(RuntimeException::class, 'Cannot transition');
});

test('previous routing operation sequence is enforced', function (): void {
    [$order, $firstRoutingLine] = shopFloorProductionOrder();
    $secondRoutingLine = $order->routingLines()->create([
        'line_number' => 20000,
        'operation_no' => 20,
        'description' => 'Filling',
        'work_center_id' => $firstRoutingLine->work_center_id,
        'expected_output_quantity' => 100,
        'status' => 'PLANNED',
    ]);

    $service = app(ProductionOperationExecutionService::class);
    $secondExecution = $service->getOrCreateExecution($order, $secondRoutingLine);
    $secondExecution = $service->startRun($secondExecution);
    Carbon::setTestNow('2026-07-29 09:00:00');

    expect(fn () => $service->completeRun($secondExecution, ['good_quantity' => 100]))
        ->toThrow(RuntimeException::class, 'Previous routing operation');

    $firstExecution = $service->getOrCreateExecution($order, $firstRoutingLine);
    $firstExecution = $service->startRun($firstExecution);
    Carbon::setTestNow('2026-07-29 10:00:00');
    $service->completeRun($firstExecution, ['good_quantity' => 100]);

    Carbon::setTestNow('2026-07-29 10:30:00');
    $completedSecond = $service->completeRun($secondExecution->fresh(), ['good_quantity' => 100]);

    expect($completedSecond->status)->toBe(ProductionOperationExecutionStatus::Completed);
});

test('quality hold blocks completion until released', function (): void {
    [$order, $routingLine] = shopFloorProductionOrder();
    $service = app(ProductionOperationExecutionService::class);
    $execution = $service->getOrCreateExecution($order, $routingLine);

    $service->recordQualityCheck(
        execution: $execution,
        stage: ProductionQualityInspectionStage::DuringOperation,
        result: ProductionQualityResult::Failed,
        disposition: ProductionQualityDisposition::Hold,
        notes: 'Fill level outside tolerance.',
    );

    $execution = $service->startRun($execution->fresh());
    Carbon::setTestNow('2026-07-29 09:00:00');

    expect(fn () => $service->completeRun($execution, ['good_quantity' => 10]))
        ->toThrow(RuntimeException::class, 'Active quality holds');

    $hold = $execution->fresh()->qualityHolds()->firstOrFail();
    $service->releaseQualityHold($hold, 'Supervisor released after recheck.');

    $completed = $service->completeRun($execution->fresh(), ['good_quantity' => 10]);

    expect($completed->status)->toBe(ProductionOperationExecutionStatus::Completed);
});

test('scrap downtime progress and readiness are derived from execution records', function (): void {
    [$order, $routingLine] = shopFloorProductionOrder();
    $service = app(ProductionOperationExecutionService::class);
    $execution = $service->getOrCreateExecution($order, $routingLine);

    $scrapReason = ProductionScrapReason::query()->create([
        'code' => 'QC-FAIL',
        'name' => 'QC Failure',
        'stage' => ProductionScrapStage::Quality,
        'default_posting_treatment' => ProductionScrapPostingTreatment::ReducedOutput,
        'requires_approval' => true,
    ]);
    $downtimeReason = ProductionDowntimeReason::query()->create([
        'code' => 'MCH',
        'name' => 'Machine Fault',
        'category' => 'maintenance',
    ]);

    $service->recordScrap($execution, $scrapReason, 3);
    $service->recordDowntime($execution, $downtimeReason, Carbon::parse('2026-07-29 09:00:00'), Carbon::parse('2026-07-29 09:15:00'));
    $service->addManualTime($execution, 'run', 3600);
    $execution->refresh()->update([
        'status' => ProductionOperationExecutionStatus::Completed,
        'good_quantity' => 97,
    ]);

    $progress = app(ProductionProgressService::class)->forOrder($order->fresh());
    $readiness = app(ProductionCompletionReadinessService::class)->findingsForOrder($order->fresh());

    expect($progress['good_quantity'])->toBe(97.0)
        ->and($progress['scrap_quantity'])->toBe(3.0)
        ->and($progress['downtime_seconds'])->toBe(900)
        ->and(collect($readiness)->pluck('classification')->all())->not->toContain('operation_not_completed');
});

test('execution submission can create traceable production journal lines', function (): void {
    [$order, $routingLine] = shopFloorProductionOrder();
    shopFloorProductionJournalTemplate();
    $service = app(ProductionOperationExecutionService::class);
    $execution = $service->getOrCreateExecution($order, $routingLine);
    $execution->update([
        'status' => ProductionOperationExecutionStatus::Completed,
        'good_quantity' => 25,
        'run_seconds' => 3600,
    ]);

    $submitted = $service->submit($execution->fresh(), userId: null, createJournal: true);

    expect($submitted->status)->toBe(ProductionOperationExecutionStatus::Submitted)
        ->and($submitted->production_journal_batch_id)->not->toBeNull()
        ->and(ProductionJournalBatch::query()->whereKey($submitted->production_journal_batch_id)->exists())->toBeTrue()
        ->and(ProductionJournalLine::query()->where('production_operation_execution_id', $submitted->id)->count())->toBe(3);
});

test('shop floor generated journal posts material capacity output value entries and general ledger idempotently', function (): void {
    $user = User::factory()->create();
    shopFloorGrantExecutionPermissions($user, ['factory.production_operation_execution.submit', 'factory.production_operation_execution.post']);
    $this->actingAs($user);

    [$order, $routingLine] = shopFloorProductionOrder();
    $location = $order->location;
    shopFloorConfigurePostingAccounts($order, $location);
    shopFloorProductionJournalTemplate();

    $routingLine->workCenter->update([
        'direct_unit_cost' => 12,
        'overhead_rate' => 3,
    ]);

    $service = app(ProductionOperationExecutionService::class);
    $execution = $service->getOrCreateExecution($order, $routingLine, attributes: [
        'location_id' => $location->id,
        'created_by' => $user->id,
    ]);
    $execution->update([
        'status' => ProductionOperationExecutionStatus::Completed,
        'good_quantity' => 10,
        'run_seconds' => 3600,
    ]);

    $submitted = $service->submit($execution->fresh(), $user->id, createJournal: true);

    expect($submitted->journalBatch->lines()->count())->toBe(3);

    $posted = $service->postJournal($submitted->fresh(), $user->id);

    $ledgerCounts = [
        'item' => ItemLedgerEntry::query()->where('document_number', $order->document_number)->count(),
        'capacity' => CapacityLedgerEntry::query()->where('document_number', $order->document_number)->count(),
        'value' => ValueEntry::query()->where('document_no', $order->document_number)->count(),
        'gl' => GlEntry::query()->where('document_number', $order->document_number)->count(),
    ];

    expect($posted->status)->toBe(ProductionOperationExecutionStatus::Posted)
        ->and($posted->journalBatch->status)->toBe(JournalBatchStatus::POSTED)
        ->and($ledgerCounts['item'])->toBe(2)
        ->and($ledgerCounts['capacity'])->toBe(1)
        ->and($ledgerCounts['value'])->toBeGreaterThanOrEqual(4)
        ->and($ledgerCounts['gl'])->toBeGreaterThanOrEqual(8);

    $consumptionEntry = ItemLedgerEntry::query()
        ->where('document_number', $order->document_number)
        ->where('entry_type', ItemLedgerEntryType::CONSUMPTION)
        ->firstOrFail();
    $outputEntry = ItemLedgerEntry::query()
        ->where('document_number', $order->document_number)
        ->where('entry_type', ItemLedgerEntryType::OUTPUT)
        ->firstOrFail();

    expect((float) $consumptionEntry->quantity)->toBe(-10.0)
        ->and((float) $outputEntry->quantity)->toBe(10.0)
        ->and(ValueEntry::query()->where('document_no', $order->document_number)->where('gl_posted', false)->exists())->toBeFalse();

    $service->postJournal($posted->fresh(), $user->id);

    expect([
        'item' => ItemLedgerEntry::query()->where('document_number', $order->document_number)->count(),
        'capacity' => CapacityLedgerEntry::query()->where('document_number', $order->document_number)->count(),
        'value' => ValueEntry::query()->where('document_no', $order->document_number)->count(),
        'gl' => GlEntry::query()->where('document_number', $order->document_number)->count(),
    ])->toBe($ledgerCounts);
});

test('shop floor posting failure leaves execution and journal unposted atomically', function (): void {
    $user = User::factory()->create();
    shopFloorGrantExecutionPermissions($user, ['factory.production_operation_execution.submit', 'factory.production_operation_execution.post']);
    $this->actingAs($user);

    [$order, $routingLine] = shopFloorProductionOrder();
    shopFloorProductionJournalTemplate();

    $service = app(ProductionOperationExecutionService::class);
    $execution = $service->getOrCreateExecution($order, $routingLine, attributes: [
        'location_id' => $order->location->id,
        'created_by' => $user->id,
    ]);
    $execution->update([
        'status' => ProductionOperationExecutionStatus::Completed,
        'good_quantity' => 10,
        'run_seconds' => 3600,
    ]);
    $submitted = $service->submit($execution->fresh(), $user->id, createJournal: true);

    expect(fn () => $service->postJournal($submitted->fresh(), $user->id))
        ->toThrow(RuntimeException::class, 'WIP account missing');

    $submitted->refresh();

    expect($submitted->status)->toBe(ProductionOperationExecutionStatus::Submitted)
        ->and($submitted->journalBatch->status)->not->toBe(JournalBatchStatus::POSTED)
        ->and(ItemLedgerEntry::query()->where('document_number', $order->document_number)->exists())->toBeFalse()
        ->and(CapacityLedgerEntry::query()->where('document_number', $order->document_number)->exists())->toBeFalse()
        ->and(GlEntry::query()->where('document_number', $order->document_number)->exists())->toBeFalse();
});

test('quality check attachments are private validated and downloaded through authorization', function (): void {
    Storage::fake('local');

    $user = User::factory()->create();
    $unauthorized = User::factory()->create();
    shopFloorGrantExecutionPermissions($user, ['factory.production_quality.record']);
    $this->actingAs($user);

    [$order, $routingLine] = shopFloorProductionOrder();
    $service = app(ProductionOperationExecutionService::class);
    $execution = $service->getOrCreateExecution($order, $routingLine);
    $qualityCheck = $service->recordQualityCheck(
        execution: $execution,
        stage: ProductionQualityInspectionStage::DuringOperation,
        result: ProductionQualityResult::Passed,
        userId: $user->id,
        notes: 'Looks good.',
    );

    $attachment = app(ProductionQualityAttachmentService::class)->store(
        $qualityCheck,
        UploadedFile::fake()->image('fill-level.png', 64, 64),
        $user,
    );

    expect($attachment)->toBeInstanceOf(ProductionQualityCheckAttachment::class)
        ->and($attachment->disk)->toBe('local')
        ->and($attachment->path)->toStartWith('manufacturing/quality-checks/')
        ->and(Storage::disk('local')->exists($attachment->path))->toBeTrue()
        ->and(Storage::disk('public')->exists($attachment->path))->toBeFalse();

    $this->actingAs($unauthorized)
        ->get(route('production-quality-attachments.download', $attachment))
        ->assertNotFound();

    $this->actingAs($user)
        ->get(route('production-quality-attachments.download', $attachment))
        ->assertOk()
        ->assertHeader('content-disposition');
});

test('shop floor reconcile is report only and supports json export', function (): void {
    [$order, $routingLine] = shopFloorProductionOrder();
    app(ProductionOperationExecutionService::class)->getOrCreateExecution($order, $routingLine);

    $before = [
        'executions' => ProductionOperationExecution::query()->count(),
        'journal_lines' => ProductionJournalLine::query()->count(),
    ];
    $exportPath = storage_path('app/reports/shop-floor-reconcile-test.json');
    File::delete($exportPath);

    Artisan::call('biwms:shop-floor-reconcile', [
        '--details' => true,
        '--export' => $exportPath,
    ]);

    $after = [
        'executions' => ProductionOperationExecution::query()->count(),
        'journal_lines' => ProductionJournalLine::query()->count(),
    ];

    expect($after)->toBe($before)
        ->and(File::exists($exportPath))->toBeTrue()
        ->and(json_decode(File::get($exportPath), true)['mode'])->toBe('report-only');
});

test('shop floor services and filament resources do not directly create general ledger entries', function (): void {
    $paths = [
        app_path('Services/Manufacturing/ProductionOperationExecutionService.php'),
        app_path('Filament/Resources/ProductionOperationExecutions/ProductionOperationExecutionResource.php'),
    ];

    foreach ($paths as $path) {
        $contents = File::get($path);

        expect($contents)
            ->not->toContain('GlEntry::create')
            ->not->toContain('createGeneralLedgerEntry')
            ->not->toContain('PostingService::class');
    }
});

function shopFloorProductionOrder(ProductionOrderStatus $status = ProductionOrderStatus::RELEASED, string $documentNumber = 'PO-SFC-TEST'): array
{
    $suffix = Str::upper(Str::random(6));
    $documentNumber = $documentNumber.'-'.$suffix;
    $generalProductPostingGroup = GeneralProductPostingGroup::query()->create([
        'code' => 'SFC-GEN-'.$suffix,
        'description' => 'Shop Floor General Product Group',
    ]);
    $generalBusinessPostingGroup = GeneralBusinessPostingGroup::query()->create([
        'code' => 'SFC-BUS-'.$suffix,
        'description' => 'Shop Floor General Business Group',
    ]);
    $inventoryPostingGroup = InventoryPostingGroup::query()->create([
        'code' => 'SFC-INV-'.$suffix,
        'description' => 'Shop Floor Inventory Group',
    ]);
    $location = Location::factory()->create(['code' => 'SFC-'.$suffix]);
    $item = Item::query()->create([
        'item_code' => 'SFC-'.$suffix,
        'description' => 'Shop Floor Item',
        'unit_cost' => 10,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
    ]);
    $componentItem = Item::query()->create([
        'item_code' => 'SFC-RM-'.$suffix,
        'description' => 'Shop Floor Component',
        'unit_cost' => 2,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
    ]);
    $workCenter = WorkCenter::factory()->create();
    $creator = User::factory()->create();
    Auth::login($creator);
    $order = ProductionOrder::query()->create([
        'document_number' => $documentNumber,
        'status' => $status,
        'item_id' => $item->id,
        'description' => 'Shop Floor Test Order',
        'quantity' => 100,
        'quantity_base' => 100,
        'unit_of_measure_code' => 'PCS',
        'starting_date_time' => now(),
        'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
        'location_code' => $location->code,
        'flushing_method' => 'MANUAL',
        'created_by' => $creator->id,
    ]);
    $order->components()->create([
        'line_number' => 10000,
        'item_id' => $componentItem->id,
        'description' => 'Shop Floor Component',
        'unit_of_measure_code' => 'PCS',
        'quantity_per' => 1,
        'expected_quantity' => 10,
        'expected_quantity_base' => 10,
        'remaining_quantity' => 10,
        'unit_cost' => 2,
        'total_cost' => 20,
        'location_code' => $location->code,
    ]);
    $routingLine = $order->routingLines()->create([
        'line_number' => 10000,
        'operation_no' => 10,
        'description' => 'Mixing',
        'work_center_id' => $workCenter->id,
        'expected_output_quantity' => 100,
        'status' => 'PLANNED',
    ]);

    return [$order, $routingLine];
}

function shopFloorProductionJournalTemplate(): ProductionJournalTemplate
{
    $numberSeries = NumberSeries::query()->create([
        'code' => 'SFC-JNL',
        'description' => 'Shop Floor Journal',
        'prefix' => 'SFC',
        'starting_number' => 1,
        'current_number' => 0,
        'is_active' => true,
    ]);

    return ProductionJournalTemplate::query()->create([
        'name' => 'SFC-JNL',
        'description' => 'Shop Floor Journal Template',
        'number_series_id' => $numberSeries->id,
        'is_active' => true,
    ]);
}

function shopFloorConfigurePostingAccounts(ProductionOrder $order, Location $location): void
{
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

    $inventoryAccount = ChartOfAccount::query()->create([
        'account_number' => 'SFC-INV-'.$order->id,
        'name' => 'SFC Inventory',
        'account_category' => 'asset',
        'account_type' => AccountType::ASSET,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    $wipAccount = ChartOfAccount::query()->create([
        'account_number' => 'SFC-WIP-'.$order->id,
        'name' => 'SFC WIP',
        'account_category' => 'asset',
        'account_type' => AccountType::ASSET,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    $appliedAccount = ChartOfAccount::query()->create([
        'account_number' => 'SFC-APP-'.$order->id,
        'name' => 'SFC Applied Cost',
        'account_category' => 'direct_expense',
        'account_type' => AccountType::EXPENSE,
        'income_balance' => IncomeBalanceType::INCOME_STATEMENT,
    ]);

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
        'inventory_adj_account_id' => $appliedAccount->id,
        'direct_cost_applied_account_id' => $appliedAccount->id,
        'overhead_applied_account_id' => $appliedAccount->id,
    ]);
}

function shopFloorGrantExecutionPermissions(User $user, array $permissions): void
{
    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user->givePermissionTo($permissions);
}
