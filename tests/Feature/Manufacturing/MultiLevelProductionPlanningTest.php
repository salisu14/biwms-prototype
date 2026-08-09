<?php

declare(strict_types=1);

use App\Enums\CostingMethod;
use App\Enums\ItemLedgerEntryType;
use App\Enums\ItemType;
use App\Enums\ProductionHierarchyStatus;
use App\Enums\ProductionOrderOrigin;
use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionReservationStatus;
use App\Enums\ProductionReservationType;
use App\Enums\ProductionSupplyLinkStatus;
use App\Enums\ProductionSupplyType;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\GeneralLedgerSetup;
use App\Models\GeneralPostingSetup;
use App\Models\GlEntry;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\Manufacturing\CapacityLedgerEntry;
use App\Models\Manufacturing\ProductionBom;
use App\Models\Manufacturing\ProductionBomLine;
use App\Models\Manufacturing\ProductionHierarchy;
use App\Models\Manufacturing\ProductionMaterialReservation;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionOrderComponent;
use App\Models\Manufacturing\ProductionOrderSupplyLink;
use App\Models\Permission;
use App\Models\User;
use App\Models\ValueEntry;
use App\Services\Manufacturing\MultiLevelBomExplosionService;
use App\Services\Manufacturing\MultiLevelProductionPlanningService;
use App\Services\Manufacturing\ProductionHierarchyProgressService;
use App\Services\Manufacturing\ProductionOrderNumberSeriesSetupService;
use App\Services\Manufacturing\ProductionOrderService;
use App\Services\Manufacturing\ProductionReservationConsumptionService;
use App\Services\Manufacturing\ProductionSupplyFulfilmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

it('explodes multi-level BOMs and creates child orders, supply links, and reservations without ledger side effects', function (): void {
    $context = manufacturingPlanningFixture();
    $rootOrder = $context['root_order'];

    $result = app(MultiLevelProductionPlanningService::class)->plan($rootOrder, $context['user']->id);

    expect($result['node_count'])->toBe(5)
        ->and($result['manufactured_component_count'])->toBe(2)
        ->and($result['child_order_count'])->toBe(2)
        ->and(ProductionHierarchy::query()->count())->toBe(1)
        ->and(ProductionOrder::query()->where('order_origin', ProductionOrderOrigin::GeneratedChild)->count())->toBe(2)
        ->and(ProductionOrderComponent::query()->where('is_manufactured_requirement', true)->count())->toBe(2)
        ->and(ProductionOrderSupplyLink::query()->where('supply_type', ProductionSupplyType::GeneratedChildOrder)->count())->toBe(2)
        ->and(ProductionMaterialReservation::query()->where('reservation_type', ProductionReservationType::ChildOutput)->count())->toBe(2)
        ->and(ItemLedgerEntry::query()->count())->toBe(0)
        ->and(ValueEntry::query()->count())->toBe(0)
        ->and(CapacityLedgerEntry::query()->count())->toBe(0);

    $extractOrder = ProductionOrder::query()
        ->whereHas('item', fn ($query) => $query->where('item_code', 'SFG-EXTRACT'))
        ->firstOrFail();

    expect($extractOrder->parent_production_order_id)->toBe($rootOrder->id)
        ->and((float) $extractOrder->quantity_base)->toBe(2.0)
        ->and($extractOrder->hierarchy_path)->toBe('1.1');
});

it('is idempotent when planning is retried for the same root order', function (): void {
    $context = manufacturingPlanningFixture();
    $service = app(MultiLevelProductionPlanningService::class);

    $service->plan($context['root_order'], $context['user']->id);
    $service->plan($context['root_order']->fresh(), $context['user']->id);

    expect(ProductionHierarchy::query()->count())->toBe(1)
        ->and(ProductionOrder::query()->where('order_origin', ProductionOrderOrigin::GeneratedChild)->count())->toBe(2)
        ->and(ProductionOrderComponent::query()->whereNotNull('hierarchy_node_id')->count())->toBe(4)
        ->and(ProductionOrderSupplyLink::query()->count())->toBe(2)
        ->and(ProductionMaterialReservation::query()->count())->toBe(2);
});

it('detects circular production BOM references before creating planning records', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $first = manufacturingPlanningItem('SFG-A', ItemType::SEMI_FINISHED);
    $second = manufacturingPlanningItem('SFG-B', ItemType::SEMI_FINISHED);

    $firstBom = manufacturingPlanningBom('BOM-A', $first);
    $secondBom = manufacturingPlanningBom('BOM-B', $second);

    $first->update(['production_bom_id' => $firstBom->id]);
    $second->update(['production_bom_id' => $secondBom->id]);

    $firstBom->lines()->create([
        'line_number' => 10000,
        'type' => ProductionBomLine::TYPE_PRODUCTION_BOM,
        'production_bom_id_related' => $secondBom->id,
        'quantity_per' => 1,
    ]);
    $secondBom->lines()->create([
        'line_number' => 10000,
        'type' => ProductionBomLine::TYPE_PRODUCTION_BOM,
        'production_bom_id_related' => $firstBom->id,
        'quantity_per' => 1,
    ]);

    $order = manufacturingPlanningOrder('PROD-CYCLE', $first, $firstBom, 1);

    expect(fn () => app(MultiLevelBomExplosionService::class)->explode($order))
        ->toThrow(RuntimeException::class, 'Circular production BOM reference detected');

    expect(ProductionHierarchy::query()->count())->toBe(0);
});

it('blocks manufactured child items that do not have a certified BOM', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $finished = manufacturingPlanningItem('FG-MISSING-BOM', ItemType::FINISHED_GOOD);
    $semiFinished = manufacturingPlanningItem('SFG-MISSING-BOM', ItemType::SEMI_FINISHED);
    $rootBom = manufacturingPlanningBom('BOM-MISSING-CHILD', $finished);
    $finished->update(['production_bom_id' => $rootBom->id]);

    $rootBom->lines()->create([
        'line_number' => 10000,
        'type' => ProductionBomLine::TYPE_ITEM,
        'item_id' => $semiFinished->id,
        'quantity_per' => 1,
        'unit_of_measure_code' => 'PCS',
    ]);

    $order = manufacturingPlanningOrder('PROD-MISSING-BOM', $finished, $rootBom, 1);

    $preview = app(MultiLevelBomExplosionService::class)->explode($order);

    expect($preview['manufactured_count'])->toBe(0)
        ->and($preview['node_count'])->toBe(2);
});

it('blocks replanning after production ledger activity exists', function (): void {
    $context = manufacturingPlanningFixture();
    $location = Location::factory()->create();

    ItemLedgerEntry::query()->create([
        'entry_number' => 999001,
        'item_id' => $context['finished_item']->id,
        'posting_date' => now()->toDateString(),
        'entry_type' => 'Output',
        'document_type' => 'Production Order',
        'document_number' => $context['root_order']->document_number,
        'document_line_number' => 10000,
        'location_id' => $location->id,
        'quantity' => 1,
        'remaining_quantity' => 1,
        'cost_amount_actual' => 0,
        'general_product_posting_group_id' => $context['finished_item']->general_product_posting_group_id,
        'inventory_posting_group_id' => $context['finished_item']->inventory_posting_group_id,
        'entry_date' => now(),
        'source_type' => ProductionOrder::class,
        'source_id' => $context['root_order']->id,
    ]);

    expect(fn () => app(MultiLevelProductionPlanningService::class)->plan($context['root_order'], $context['user']->id))
        ->toThrow(RuntimeException::class, 'ledger activity');
});

it('reports phase 2a2 planning diagnostics through manufacturing cost reconcile', function (): void {
    $context = manufacturingPlanningFixture();
    app(MultiLevelProductionPlanningService::class)->plan($context['root_order'], $context['user']->id);

    ProductionOrderSupplyLink::allowServiceMutation(
        fn () => ProductionOrderSupplyLink::query()->update(['status' => 'cancelled']),
    );

    $this->artisan('biwms:manufacturing-cost-reconcile', [
        '--production-order' => $context['root_order']->document_number,
        '--details' => true,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Phase 2a2 generated child without supply link');
});

it('updates child supply and reservation availability when generated child output is posted', function (): void {
    $context = manufacturingPlanningFixture();
    grantManufacturingPlanningPostingPermissions($context['user']);
    $result = app(MultiLevelProductionPlanningService::class)->plan($context['root_order'], $context['user']->id);
    $extractOrder = manufacturingPlanningChildOrder('SFG-EXTRACT');
    createManufacturingPlanningPostingAccounts($extractOrder);
    $extractOrder->update(['status' => ProductionOrderStatus::RELEASED]);

    app(ProductionOrderService::class)->postOutput($extractOrder->fresh(), 1, $context['user']->id);
    app(ProductionOrderService::class)->postOutput($extractOrder->fresh(), 1, $context['user']->id);

    $link = $extractOrder->supplyLinksAsChild()->firstOrFail();
    $reservation = $link->materialReservations()->firstOrFail();

    expect((float) $link->fresh()->produced_quantity_base)->toBe(2.0)
        ->and((float) $link->fresh()->supplied_quantity_base)->toBe(2.0)
        ->and((float) $reservation->fresh()->quantity_base)->toBe(2.0)
        ->and((float) data_get($reservation->fresh()->metadata, 'available_quantity_base'))->toBe(2.0)
        ->and($result['hierarchy']->fresh()->supplyLinks()->count())->toBe(2);
});

it('supports partial child output and blocks parent overconsumption beyond available child supply', function (): void {
    $context = manufacturingPlanningFixture();
    grantManufacturingPlanningPostingPermissions($context['user']);
    app(MultiLevelProductionPlanningService::class)->plan($context['root_order'], $context['user']->id);
    $rootOrder = $context['root_order']->fresh();
    $extractOrder = manufacturingPlanningChildOrder('SFG-EXTRACT');
    createManufacturingPlanningPostingAccounts($extractOrder);
    createManufacturingPlanningPostingAccounts($rootOrder);
    $extractOrder->update(['status' => ProductionOrderStatus::RELEASED]);
    $rootOrder->update(['status' => ProductionOrderStatus::RELEASED]);

    app(ProductionOrderService::class)->postOutput($extractOrder->fresh(), 1, $context['user']->id);

    $extractComponent = $rootOrder->components()
        ->whereHas('item', fn ($query) => $query->where('item_code', 'SFG-EXTRACT'))
        ->firstOrFail();

    expect(fn () => app(ProductionOrderService::class)->postConsumption($rootOrder->fresh(), [[
        'component_id' => $extractComponent->id,
        'quantity' => 2,
    ]], $context['user']->id))->toThrow(RuntimeException::class, 'Cannot consume more hierarchy child supply than is available');

    app(ProductionOrderService::class)->postConsumption($rootOrder->fresh(), [[
        'component_id' => $extractComponent->id,
        'quantity' => 1,
    ]], $context['user']->id);

    $reservation = $extractComponent->fresh()->materialReservations()->firstOrFail();

    expect((float) $reservation->remaining_quantity_base)->toBe(1.0)
        ->and((float) data_get($reservation->metadata, 'consumed_quantity_base'))->toBe(1.0);
});

it('blocks parent finish while manufactured child demand remains unresolved', function (): void {
    $context = manufacturingPlanningFixture();
    grantManufacturingPlanningPostingPermissions($context['user']);
    app(MultiLevelProductionPlanningService::class)->plan($context['root_order'], $context['user']->id);
    $rootOrder = $context['root_order']->fresh();
    createManufacturingPlanningPostingAccounts($rootOrder);
    $rootOrder->update(['status' => ProductionOrderStatus::RELEASED]);

    expect(fn () => app(ProductionOrderService::class)->finish($rootOrder->fresh(), $context['user']->id))
        ->toThrow(RuntimeException::class, 'Child supply is not fully available');
});

it('caps child overproduction supply to parent demand and leaves excess as ordinary inventory', function (): void {
    $context = manufacturingPlanningFixture();
    app(MultiLevelProductionPlanningService::class)->plan($context['root_order'], $context['user']->id);
    $extractOrder = manufacturingPlanningChildOrder('SFG-EXTRACT');
    $link = $extractOrder->supplyLinksAsChild()->firstOrFail();
    $location = Location::query()->where('code', 'MAIN-PHASE2A')->firstOrFail();

    ItemLedgerEntry::query()->create([
        'entry_number' => 880001,
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_type' => 'Production Order',
        'document_number' => $extractOrder->document_number,
        'document_line_number' => 10000,
        'item_id' => $extractOrder->item_id,
        'location_id' => $location->id,
        'quantity' => 3,
        'remaining_quantity' => 3,
        'cost_amount_actual' => 30,
        'general_product_posting_group_id' => $extractOrder->general_product_posting_group_id,
        'inventory_posting_group_id' => $extractOrder->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'source_type' => ProductionOrder::class,
        'source_id' => $extractOrder->id,
    ]);

    app(ProductionSupplyFulfilmentService::class)->syncChildOutputSupply($extractOrder->fresh());

    expect((float) $link->fresh()->produced_quantity_base)->toBe(3.0)
        ->and((float) $link->fresh()->supplied_quantity_base)->toBe(2.0)
        ->and((float) data_get($link->fresh()->metadata, 'excess_output_quantity_base'))->toBe(1.0);
});

it('keeps single-level production order output behaviour unchanged', function (): void {
    $context = manufacturingPlanningFixture();
    grantManufacturingPlanningPostingPermissions($context['user']);
    $singleLevelOrder = manufacturingPlanningOrder('PROD-SINGLE-001', $context['finished_item'], $context['finished_bom'], 1);
    createManufacturingPlanningPostingAccounts($singleLevelOrder);
    $singleLevelOrder->update(['status' => ProductionOrderStatus::RELEASED]);

    app(ProductionOrderService::class)->postOutput($singleLevelOrder->fresh(), 1, $context['user']->id);

    expect($singleLevelOrder->fresh()->supplyLinksAsChild()->count())->toBe(0)
        ->and($singleLevelOrder->fresh()->supplyLinksAsParent()->count())->toBe(0)
        ->and((float) $singleLevelOrder->fresh()->itemLedgerEntries()->where('entry_type', ItemLedgerEntryType::OUTPUT)->sum('quantity'))->toBe(1.0);
});

it('executes a three-level herbal hierarchy end to end through inventory and separated WIP', function (): void {
    $context = manufacturingCompletionHerbalFixture();
    $user = $context['user'];
    $service = app(ProductionOrderService::class);

    $result = app(MultiLevelProductionPlanningService::class)->plan($context['root_order'], $user->id);

    expect($result['node_count'])->toBeGreaterThanOrEqual(14)
        ->and($result['child_order_count'])->toBe(2)
        ->and(ProductionOrder::query()->where('order_origin', ProductionOrderOrigin::GeneratedChild)->count())->toBe(2);

    $bulkOrder = manufacturingCompletionChildOrder('SFG-BULK-HERBAL');
    $bottledOrder = manufacturingCompletionChildOrder('SFG-BOTTLED-HERBAL');
    $rootOrder = $context['root_order']->fresh();

    foreach ([$bulkOrder, $bottledOrder, $rootOrder] as $order) {
        createManufacturingPlanningPostingAccounts($order);
    }
    manufacturingCompletionCreateInventoryPostingSetupsForItems($context['items']);
    manufacturingCompletionSeedInventoryLayers($context);

    manufacturingCompletionFirmAndRelease($bulkOrder->fresh(), $user->id);
    manufacturingCompletionConsumeAll($bulkOrder->fresh(), $user->id);
    $service->postOutput($bulkOrder->fresh(), '2', $user->id);
    $service->finish($bulkOrder->fresh(), $user->id);

    $bulkLink = $bulkOrder->fresh()->supplyLinksAsChild()->firstOrFail();
    $bulkReservation = $bulkLink->materialReservations()->firstOrFail();
    $bulkOutput = manufacturingCompletionOutputEntry($bulkOrder);
    $bulkOutputValue = manufacturingCompletionValueEntryForItemLedger($bulkOutput);
    $bulkRawCost = manufacturingCompletionConsumptionCost($bulkOrder);

    expect((float) $bulkLink->fresh()->supplied_quantity_base)->toBe(2.0)
        ->and((float) data_get($bulkReservation->fresh()->metadata, 'available_quantity_base'))->toBe(2.0)
        ->and(round((float) $bulkOutput->fresh()->cost_amount_actual, 4))->toBe(round($bulkRawCost, 4))
        ->and(round((float) $bulkOutputValue->cost_amount_actual, 4))->toBe(round($bulkRawCost, 4))
        ->and((float) $bulkOutputValue->quantity)->toBe(2.0)
        ->and((float) $bulkOutputValue->unit_cost)->toBeGreaterThan(0);

    manufacturingCompletionFirmAndRelease($bottledOrder->fresh(), $user->id);
    manufacturingCompletionConsumeAll($bottledOrder->fresh(), $user->id);

    $bottledBulkConsumption = manufacturingCompletionConsumptionEntry($bottledOrder, $context['items']['bulk']);
    $bulkApplication = $bottledBulkConsumption->outboundApplications()->firstOrFail();
    $bottledBulkValue = manufacturingCompletionValueEntryForItemLedger($bottledBulkConsumption);

    expect($bulkApplication->inbound_item_ledger_entry_id)->toBe($bulkOutput->id)
        ->and((float) $bulkApplication->applied_quantity)->toBe(2.0)
        ->and((float) $bulkApplication->unit_cost)->toBeGreaterThan(0)
        ->and(round((float) $bulkApplication->cost_amount, 4))->toBe(round($bulkRawCost, 4))
        ->and((float) $bottledBulkValue->quantity)->toBe(-2.0)
        ->and((float) $bottledBulkValue->unit_cost)->toBeGreaterThan(0)
        ->and(round((float) $bottledBulkValue->cost_amount_actual, 4))->toBe(round($bulkRawCost, 4));

    $service->postOutput($bottledOrder->fresh(), '2', $user->id);
    $service->finish($bottledOrder->fresh(), $user->id);

    $bottledOutput = manufacturingCompletionOutputEntry($bottledOrder);
    $bottledOutputValue = manufacturingCompletionValueEntryForItemLedger($bottledOutput);
    $bottledAccumulatedCost = manufacturingCompletionConsumptionCost($bottledOrder);

    expect(round((float) $bottledOutput->fresh()->cost_amount_actual, 4))->toBe(round($bottledAccumulatedCost, 4))
        ->and(round((float) $bottledOutputValue->cost_amount_actual, 4))->toBe(round($bottledAccumulatedCost, 4));

    manufacturingCompletionFirmAndRelease($rootOrder->fresh(), $user->id);
    manufacturingCompletionConsumeAll($rootOrder->fresh(), $user->id);

    $rootBottledConsumption = manufacturingCompletionConsumptionEntry($rootOrder, $context['items']['bottled']);
    $bottledApplication = $rootBottledConsumption->outboundApplications()->firstOrFail();
    $rootBottledValue = manufacturingCompletionValueEntryForItemLedger($rootBottledConsumption);

    expect($bottledApplication->inbound_item_ledger_entry_id)->toBe($bottledOutput->id)
        ->and((float) $bottledApplication->applied_quantity)->toBe(2.0)
        ->and(round((float) $bottledApplication->cost_amount, 4))->toBe(round($bottledAccumulatedCost, 4))
        ->and((float) $rootBottledValue->quantity)->toBe(-2.0)
        ->and((float) $rootBottledValue->unit_cost)->toBeGreaterThan(0)
        ->and(round((float) $rootBottledValue->cost_amount_actual, 4))->toBe(round($bottledAccumulatedCost, 4));

    $service->postOutput($rootOrder->fresh(), '1', $user->id);
    $service->finish($rootOrder->fresh(), $user->id);

    $rootOutput = manufacturingCompletionOutputEntry($rootOrder);
    $rootOutputValue = manufacturingCompletionValueEntryForItemLedger($rootOutput);
    $rootAccumulatedCost = manufacturingCompletionConsumptionCost($rootOrder);

    app(ProductionHierarchyProgressService::class)->sync($result['hierarchy']->fresh());

    expect($result['hierarchy']->fresh()->status)->toBe(ProductionHierarchyStatus::Completed)
        ->and(round((float) $rootOutput->fresh()->cost_amount_actual, 4))->toBe(round($rootAccumulatedCost, 4))
        ->and(round((float) $rootOutputValue->cost_amount_actual, 4))->toBe(round($rootAccumulatedCost, 4))
        ->and(round($rootAccumulatedCost, 4))->toBe(round($bottledAccumulatedCost + manufacturingCompletionDirectConsumptionCost($rootOrder, [
            $context['items']['tray'],
            $context['items']['carton'],
        ]), 4));

    expect(manufacturingCompletionConsumptionCost($bulkOrder))->toBeGreaterThan(0.0)
        ->and(manufacturingCompletionConsumptionCost($bottledOrder))->toBeGreaterThan(manufacturingCompletionConsumptionCost($bulkOrder))
        ->and(manufacturingCompletionConsumptionCost($rootOrder))->toBeGreaterThan(manufacturingCompletionConsumptionCost($bottledOrder));

    $childRawValueEntriesOwnedByParent = ValueEntry::query()
        ->where('production_order_no', $bottledOrder->document_number)
        ->whereIn('item_no', collect($context['raw_items'])->pluck('item_code')->all())
        ->count();

    expect($childRawValueEntriesOwnedByParent)->toBe(0)
        ->and(GlEntry::query()->whereIn('item_ledger_entry_id', [
            $bulkOutput->id,
            $bottledBulkConsumption->id,
            $bottledOutput->id,
            $rootBottledConsumption->id,
            $rootOutput->id,
        ])->count())->toBeGreaterThanOrEqual(10);

    $this->artisan('biwms:manufacturing-cost-reconcile', [
        '--production-order' => $rootOrder->document_number,
        '--details' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('Phase 2a3 child output supply mismatch: OK')
        ->expectsOutputToContain('Phase 2a3 parent consumption exceeds supplied: OK');
});

it('keeps partial child supply and parent consumption cumulative and idempotent across retries', function (): void {
    $context = manufacturingPlanningFixture();
    grantManufacturingPlanningPostingPermissions($context['user']);
    app(MultiLevelProductionPlanningService::class)->plan($context['root_order'], $context['user']->id);
    $rootOrder = $context['root_order']->fresh();
    $extractOrder = manufacturingPlanningChildOrder('SFG-EXTRACT');
    createManufacturingPlanningPostingAccounts($extractOrder);
    createManufacturingPlanningPostingAccounts($rootOrder);
    $extractOrder->update(['status' => ProductionOrderStatus::RELEASED]);
    $rootOrder->update(['status' => ProductionOrderStatus::RELEASED]);

    app(ProductionOrderService::class)->postOutput($extractOrder->fresh(), '0.40', $context['user']->id);
    app(ProductionSupplyFulfilmentService::class)->syncChildOutputSupply($extractOrder->fresh());
    app(ProductionSupplyFulfilmentService::class)->syncChildOutputSupply($extractOrder->fresh());

    $link = $extractOrder->fresh()->supplyLinksAsChild()->firstOrFail();
    $reservation = $link->materialReservations()->firstOrFail();

    expect((float) $link->fresh()->supplied_quantity_base)->toBe(0.4)
        ->and((float) data_get($reservation->fresh()->metadata, 'available_quantity_base'))->toBe(0.4);

    app(ProductionOrderService::class)->postOutput($extractOrder->fresh(), '0.25', $context['user']->id);
    app(ProductionSupplyFulfilmentService::class)->syncChildOutputSupply($extractOrder->fresh());

    expect((float) $link->fresh()->supplied_quantity_base)->toBe(0.65)
        ->and((float) $link->fresh()->produced_quantity_base)->toBe(0.65);

    $extractComponent = $rootOrder->components()
        ->whereHas('item', fn ($query) => $query->where('item_code', 'SFG-EXTRACT'))
        ->firstOrFail();

    app(ProductionOrderService::class)->postConsumption($rootOrder->fresh(), [[
        'component_id' => $extractComponent->id,
        'quantity' => '0.30',
    ]], $context['user']->id);
    app(ProductionReservationConsumptionService::class)->syncComponentConsumption($extractComponent->fresh());
    app(ProductionReservationConsumptionService::class)->syncComponentConsumption($extractComponent->fresh());

    expect((float) $reservation->fresh()->remaining_quantity_base)->toBe(1.7)
        ->and((float) data_get($reservation->fresh()->metadata, 'consumed_quantity_base'))->toBe(0.3)
        ->and((float) data_get($reservation->fresh()->metadata, 'available_quantity_base'))->toBe(0.65);

    app(ProductionOrderService::class)->postConsumption($rootOrder->fresh(), [[
        'component_id' => $extractComponent->id,
        'quantity' => '0.20',
    ]], $context['user']->id);

    expect((float) $reservation->fresh()->remaining_quantity_base)->toBe(1.5)
        ->and((float) $link->fresh()->consumed_quantity_base)->toBe(0.5)
        ->and(ProductionOrderSupplyLink::query()->count())->toBe(2)
        ->and(ProductionMaterialReservation::query()->count())->toBe(2);
});

it('blocks destructive replanning after partial execution and reports underproduction readiness gaps', function (): void {
    $context = manufacturingPlanningFixture();
    grantManufacturingPlanningPostingPermissions($context['user']);
    app(MultiLevelProductionPlanningService::class)->plan($context['root_order'], $context['user']->id);
    $rootOrder = $context['root_order']->fresh();
    $extractOrder = manufacturingPlanningChildOrder('SFG-EXTRACT');
    createManufacturingPlanningPostingAccounts($extractOrder);
    createManufacturingPlanningPostingAccounts($rootOrder);
    $extractOrder->update(['status' => ProductionOrderStatus::RELEASED]);

    app(ProductionOrderService::class)->postOutput($extractOrder->fresh(), '1.00', $context['user']->id);

    expect(fn () => app(MultiLevelProductionPlanningService::class)->plan($rootOrder->fresh(), $context['user']->id))
        ->toThrow(RuntimeException::class, 'ledger activity');

    $link = $extractOrder->fresh()->supplyLinksAsChild()->firstOrFail();
    ProductionOrderSupplyLink::allowServiceMutation(fn () => $link->forceFill([
        'status' => ProductionSupplyLinkStatus::Exception,
    ])->save());
    $extractOrder->forceFill([
        'status' => ProductionOrderStatus::FINISHED,
        'posted' => true,
    ])->save();
    $rootOrder->update(['status' => ProductionOrderStatus::RELEASED]);

    expect(fn () => app(ProductionOrderService::class)->finish($rootOrder->fresh(), $context['user']->id))
        ->toThrow(RuntimeException::class, 'Child supply is not fully available');

    $this->artisan('biwms:manufacturing-cost-reconcile', [
        '--production-order' => $rootOrder->document_number,
        '--details' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('Phase 2a3 child finished with supply shortage');
});

it('reports broken phase 2a runtime states without mutating production data', function (): void {
    $context = manufacturingPlanningFixture();
    app(MultiLevelProductionPlanningService::class)->plan($context['root_order'], $context['user']->id);
    $rootOrder = $context['root_order']->fresh();
    $extractOrder = manufacturingPlanningChildOrder('SFG-EXTRACT');
    $link = $extractOrder->supplyLinksAsChild()->firstOrFail();
    $reservation = $link->materialReservations()->firstOrFail();

    ProductionOrderSupplyLink::allowServiceMutation(fn () => $link->forceFill([
        'produced_quantity_base' => '0.50000000',
        'supplied_quantity_base' => '1.50000000',
        'consumed_quantity_base' => '1.75000000',
    ])->save());
    ProductionMaterialReservation::allowServiceMutation(fn () => $reservation->forceFill([
        'remaining_quantity_base' => '0.00000000',
        'metadata' => [
            ...(array) $reservation->metadata,
            'available_quantity_base' => '0.25000000',
        ],
    ])->save());
    ProductionHierarchy::allowServiceMutation(fn () => $rootOrder->productionHierarchy->forceFill([
        'status' => ProductionHierarchyStatus::Completed,
    ])->save());

    $this->artisan('biwms:manufacturing-cost-reconcile', [
        '--production-order' => $rootOrder->document_number,
        '--details' => true,
    ])->assertSuccessful()
        ->expectsOutputToContain('Phase 2a3 child output supply mismatch')
        ->expectsOutputToContain('Phase 2a3 reservation available mismatch')
        ->expectsOutputToContain('Phase 2a3 reservation consumption exceeds available')
        ->expectsOutputToContain('Phase 2a3 parent consumption exceeds supplied')
        ->expectsOutputToContain('Phase 2a3 hierarchy status mismatch');

    expect($link->fresh()->consumed_quantity_base)->toBe('1.75000000')
        ->and($reservation->fresh()->status)->toBe(ProductionReservationStatus::Active);
});

/**
 * @return array<string, mixed>
 */
function manufacturingPlanningFixture(): array
{
    $user = User::factory()->create();
    test()->actingAs($user);
    app(ProductionOrderNumberSeriesSetupService::class)->ensure();
    Location::factory()->create(['code' => 'MAIN-PHASE2A']);

    $finished = manufacturingPlanningItem('FG-CARTON', ItemType::FINISHED_GOOD);
    $extract = manufacturingPlanningItem('SFG-EXTRACT', ItemType::SEMI_FINISHED);
    $mix = manufacturingPlanningItem('SFG-MIX', ItemType::SEMI_FINISHED);
    $raw = manufacturingPlanningItem('RAW-GINSENG', ItemType::RAW_MATERIAL);
    $packaging = manufacturingPlanningItem('PKG-CARTON', ItemType::PACKAGING);

    $mixBom = manufacturingPlanningBom('BOM-SFG-MIX', $mix);
    $mix->update(['production_bom_id' => $mixBom->id]);
    $mixBom->lines()->create([
        'line_number' => 10000,
        'type' => ProductionBomLine::TYPE_ITEM,
        'item_id' => $raw->id,
        'quantity_per' => 3,
        'unit_of_measure_code' => 'PCS',
    ]);

    $extractBom = manufacturingPlanningBom('BOM-SFG-EXTRACT', $extract);
    $extract->update(['production_bom_id' => $extractBom->id]);
    $extractBom->lines()->create([
        'line_number' => 10000,
        'type' => ProductionBomLine::TYPE_PRODUCTION_BOM,
        'production_bom_id_related' => $mixBom->id,
        'quantity_per' => 4,
        'unit_of_measure_code' => 'PCS',
    ]);

    $finishedBom = manufacturingPlanningBom('BOM-FG-CARTON', $finished);
    $finished->update(['production_bom_id' => $finishedBom->id]);
    $finishedBom->lines()->create([
        'line_number' => 10000,
        'type' => ProductionBomLine::TYPE_PRODUCTION_BOM,
        'production_bom_id_related' => $extractBom->id,
        'quantity_per' => 2,
        'unit_of_measure_code' => 'PCS',
    ]);
    $finishedBom->lines()->create([
        'line_number' => 20000,
        'type' => ProductionBomLine::TYPE_ITEM,
        'item_id' => $packaging->id,
        'quantity_per' => 1,
        'unit_of_measure_code' => 'PCS',
    ]);

    $rootOrder = manufacturingPlanningOrder('PROD-ROOT-001', $finished, $finishedBom, 1);

    return [
        'user' => $user,
        'finished_item' => $finished,
        'finished_bom' => $finishedBom,
        'root_order' => $rootOrder,
    ];
}

function manufacturingPlanningItem(string $code, ItemType $type): Item
{
    return Item::factory()->create([
        'item_code' => $code,
        'description' => $code,
        'item_type' => $type,
        'unit_cost' => 10,
    ]);
}

function manufacturingPlanningBom(string $code, Item $item): ProductionBom
{
    return ProductionBom::query()->create([
        'code' => $code,
        'description' => $code,
        'item_id' => $item->id,
        'unit_of_measure_code' => 'PCS',
        'status' => 'CERTIFIED',
    ]);
}

function manufacturingPlanningOrder(string $documentNumber, Item $item, ProductionBom $bom, float|int|string $quantity): ProductionOrder
{
    return ProductionOrder::withoutAutomaticDocumentNumbering(fn (): ProductionOrder => ProductionOrder::query()->create([
        'document_number' => $documentNumber,
        'status' => ProductionOrderStatus::PLANNED,
        'item_id' => $item->id,
        'quantity' => $quantity,
        'quantity_base' => $quantity,
        'unit_of_measure_code' => 'PCS',
        'production_bom_id' => $bom->id,
        'location_code' => 'MAIN-PHASE2A',
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'costing_method' => CostingMethod::FIFO,
        'production_level' => 0,
        'hierarchy_path' => '1',
        'hierarchy_planning_version' => 1,
    ]));
}

function manufacturingPlanningChildOrder(string $itemCode): ProductionOrder
{
    return ProductionOrder::query()
        ->whereHas('item', fn ($query) => $query->where('item_code', $itemCode))
        ->where('order_origin', ProductionOrderOrigin::GeneratedChild)
        ->firstOrFail();
}

function grantManufacturingPlanningPostingPermissions(User $user): void
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

function createManufacturingPlanningPostingAccounts(ProductionOrder $order): void
{
    $location = Location::query()->firstOrCreate(['code' => 'MAIN-PHASE2A'], ['name' => 'Main Phase 2A']);
    $inventoryAccount = ChartOfAccount::factory()->create(['account_number' => 'P2A-INV-'.$order->id]);
    $wipAccount = ChartOfAccount::factory()->create(['account_number' => 'P2A-WIP-'.$order->id]);
    $appliedAccount = ChartOfAccount::factory()->create(['account_number' => 'P2A-APP-'.$order->id]);
    $varianceAccount = ChartOfAccount::factory()->create(['account_number' => 'P2A-VAR-'.$order->id]);

    InventoryPostingSetup::query()->updateOrCreate([
        'inventory_posting_group_id' => $order->inventory_posting_group_id,
        'location_id' => $location->id,
    ], [
        'inventory_account_id' => $inventoryAccount->id,
        'wip_account_id' => $wipAccount->id,
    ]);

    if (! $order->general_business_posting_group_id || ! $order->general_product_posting_group_id) {
        return;
    }

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

/**
 * @return array<string, mixed>
 */
function manufacturingCompletionHerbalFixture(): array
{
    $user = User::factory()->create();
    test()->actingAs($user);
    grantManufacturingPlanningPostingPermissions($user);
    app(ProductionOrderNumberSeriesSetupService::class)->ensure();
    Location::factory()->create(['code' => 'MAIN-PHASE2A']);

    $finishedCarton = manufacturingCompletionItem('FG-HERBAL-CARTON', ItemType::FINISHED_GOOD, '0');
    $bottled = manufacturingCompletionItem('SFG-BOTTLED-HERBAL', ItemType::SEMI_FINISHED, '0');
    $bulk = manufacturingCompletionItem('SFG-BULK-HERBAL', ItemType::SEMI_FINISHED, '0');

    $rawItems = [
        'ginseng' => manufacturingCompletionItem('RAW-GINSENG-HERBAL', ItemType::RAW_MATERIAL, '778.50'),
        'yohimbine' => manufacturingCompletionItem('RAW-YOHIMBINE-HERBAL', ItemType::RAW_MATERIAL, '129.75'),
        'sodium_benzoate' => manufacturingCompletionItem('RAW-SODIUM-BENZ', ItemType::RAW_MATERIAL, '11.25'),
        'ficus_carica' => manufacturingCompletionItem('RAW-FICUS-CARICA', ItemType::RAW_MATERIAL, '6.875'),
        'sodium_saccharine' => manufacturingCompletionItem('RAW-SODIUM-SACCH', ItemType::RAW_MATERIAL, '18.33333333'),
    ];

    $packagingItems = [
        'bottle' => manufacturingCompletionItem('PKG-HERBAL-BOTTLE', ItemType::PACKAGING, '4.25'),
        'cap' => manufacturingCompletionItem('PKG-HERBAL-CAP', ItemType::PACKAGING, '1.10'),
        'label' => manufacturingCompletionItem('PKG-HERBAL-LABEL', ItemType::PACKAGING, '0.65'),
        'sleeve' => manufacturingCompletionItem('PKG-HERBAL-SLEEVE', ItemType::PACKAGING, '0.90'),
        'tray' => manufacturingCompletionItem('PKG-HERBAL-TRAY', ItemType::PACKAGING, '3.40'),
        'carton' => manufacturingCompletionItem('PKG-HERBAL-CARTON', ItemType::PACKAGING, '9.75'),
    ];

    $bulkBom = manufacturingPlanningBom('BOM-SFG-BULK-HERBAL', $bulk);
    $bulk->update(['production_bom_id' => $bulkBom->id]);
    $lineNumber = 10000;
    foreach ([
        [$rawItems['ginseng'], '17.065728'],
        [$rawItems['yohimbine'], '39.710304'],
        [$rawItems['sodium_benzoate'], '0.75000000'],
        [$rawItems['ficus_carica'], '289.57527360'],
        [$rawItems['sodium_saccharine'], '1.23456789'],
    ] as [$item, $quantityPer]) {
        $bulkBom->lines()->create([
            'line_number' => $lineNumber,
            'type' => ProductionBomLine::TYPE_ITEM,
            'item_id' => $item->id,
            'quantity_per' => $quantityPer,
            'unit_of_measure_code' => 'PCS',
        ]);
        $lineNumber += 10000;
    }

    $bottledBom = manufacturingPlanningBom('BOM-SFG-BOTTLED-HERBAL', $bottled);
    $bottled->update(['production_bom_id' => $bottledBom->id]);
    $bottledBom->lines()->create([
        'line_number' => 10000,
        'type' => ProductionBomLine::TYPE_PRODUCTION_BOM,
        'production_bom_id_related' => $bulkBom->id,
        'quantity_per' => 1,
        'unit_of_measure_code' => 'PCS',
    ]);
    $lineNumber = 20000;
    foreach ([
        $packagingItems['bottle'],
        $packagingItems['cap'],
        $packagingItems['label'],
        $packagingItems['sleeve'],
    ] as $item) {
        $bottledBom->lines()->create([
            'line_number' => $lineNumber,
            'type' => ProductionBomLine::TYPE_ITEM,
            'item_id' => $item->id,
            'quantity_per' => 1,
            'unit_of_measure_code' => 'PCS',
        ]);
        $lineNumber += 10000;
    }

    $finishedBom = manufacturingPlanningBom('BOM-FG-HERBAL-CARTON', $finishedCarton);
    $finishedCarton->update(['production_bom_id' => $finishedBom->id]);
    $finishedBom->lines()->create([
        'line_number' => 10000,
        'type' => ProductionBomLine::TYPE_PRODUCTION_BOM,
        'production_bom_id_related' => $bottledBom->id,
        'quantity_per' => 2,
        'unit_of_measure_code' => 'PCS',
    ]);
    $finishedBom->lines()->create([
        'line_number' => 20000,
        'type' => ProductionBomLine::TYPE_ITEM,
        'item_id' => $packagingItems['tray']->id,
        'quantity_per' => 1,
        'unit_of_measure_code' => 'PCS',
    ]);
    $finishedBom->lines()->create([
        'line_number' => 30000,
        'type' => ProductionBomLine::TYPE_ITEM,
        'item_id' => $packagingItems['carton']->id,
        'quantity_per' => 1,
        'unit_of_measure_code' => 'PCS',
    ]);

    $rootOrder = manufacturingPlanningOrder('PROD-HERBAL-3L-001', $finishedCarton, $finishedBom, 1);

    return [
        'user' => $user,
        'root_order' => $rootOrder,
        'raw_items' => array_values($rawItems),
        'items' => [
            'finished_carton' => $finishedCarton,
            'bottled' => $bottled,
            'bulk' => $bulk,
            ...$rawItems,
            ...$packagingItems,
        ],
    ];
}

function manufacturingCompletionItem(string $code, ItemType $type, string $unitCost): Item
{
    return Item::factory()->create([
        'item_code' => $code,
        'description' => $code,
        'item_type' => $type,
        'unit_cost' => $unitCost,
    ]);
}

function manufacturingCompletionChildOrder(string $itemCode): ProductionOrder
{
    return ProductionOrder::query()
        ->whereHas('item', fn ($query) => $query->where('item_code', $itemCode))
        ->where('order_origin', ProductionOrderOrigin::GeneratedChild)
        ->firstOrFail();
}

/**
 * @param  array<string, Item>  $items
 */
function manufacturingCompletionCreateInventoryPostingSetupsForItems(array $items): void
{
    $location = Location::query()->where('code', 'MAIN-PHASE2A')->firstOrFail();

    foreach ($items as $item) {
        InventoryPostingSetup::query()->updateOrCreate([
            'inventory_posting_group_id' => $item->inventory_posting_group_id,
            'location_id' => $location->id,
        ], [
            'inventory_account_id' => ChartOfAccount::factory()->create(['account_number' => 'P2AC-INV-'.$item->id])->id,
            'wip_account_id' => ChartOfAccount::factory()->create(['account_number' => 'P2AC-WIP-'.$item->id])->id,
        ]);
    }
}

/**
 * @param  array<string, mixed>  $context
 */
function manufacturingCompletionSeedInventoryLayers(array $context): void
{
    $rootOrder = $context['root_order']->fresh();
    $items = $context['items'];

    $expectedQuantities = [
        'ginseng' => '34.13145600',
        'yohimbine' => '79.42060800',
        'sodium_benzoate' => '1.50000000',
        'ficus_carica' => '579.15054720',
        'sodium_saccharine' => '2.46913578',
        'bottle' => '2.00000000',
        'cap' => '2.00000000',
        'label' => '2.00000000',
        'sleeve' => '2.00000000',
        'tray' => '1.00000000',
        'carton' => '1.00000000',
    ];

    foreach ($expectedQuantities as $key => $quantity) {
        manufacturingCompletionSeedInventoryLayer($items[$key], $quantity, (string) $items[$key]->unit_cost, $rootOrder);
    }
}

function manufacturingCompletionSeedInventoryLayer(Item $item, string $quantity, string $unitCost, ProductionOrder $sourceOrder): ItemLedgerEntry
{
    $location = Location::query()->where('code', 'MAIN-PHASE2A')->firstOrFail();
    $costAmount = round((float) $quantity * (float) $unitCost, 4);

    $entry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::POSITIVE_ADJUSTMENT,
        'document_type' => 'OPENING',
        'document_number' => 'OPEN-HERBAL-'.$item->id,
        'document_line_number' => 10000,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'quantity' => $quantity,
        'remaining_quantity' => $quantity,
        'open' => true,
        'cost_amount_actual' => $costAmount,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'posting_date' => now()->toDateString(),
        'entry_date' => now(),
        'source_type' => ProductionOrder::class,
        'source_id' => $sourceOrder->id,
    ]);

    $item->increment('inventory', $quantity);

    return $entry;
}

function manufacturingCompletionConsumeAll(ProductionOrder $order, int $userId): void
{
    $lines = $order->components()
        ->orderBy('line_number')
        ->get()
        ->map(fn (ProductionOrderComponent $component): array => [
            'component_id' => $component->id,
            'quantity' => (string) $component->expected_quantity,
        ])
        ->all();

    app(ProductionOrderService::class)->postConsumption($order->fresh(), $lines, $userId);
}

function manufacturingCompletionFirmAndRelease(ProductionOrder $order, int $userId): void
{
    $service = app(ProductionOrderService::class);

    if ($order->status === ProductionOrderStatus::PLANNED) {
        $service->changeStatus($order->fresh(), ProductionOrderStatus::FIRM_PLANNED, $userId);
    }

    $service->release($order->fresh(), $userId);
}

function manufacturingCompletionOutputEntry(ProductionOrder $order): ItemLedgerEntry
{
    return $order->fresh()->itemLedgerEntries()
        ->where('entry_type', ItemLedgerEntryType::OUTPUT)
        ->where('item_id', $order->item_id)
        ->latest('id')
        ->firstOrFail();
}

function manufacturingCompletionConsumptionEntry(ProductionOrder $order, Item $item): ItemLedgerEntry
{
    return $order->fresh()->itemLedgerEntries()
        ->where('entry_type', ItemLedgerEntryType::CONSUMPTION)
        ->where('item_id', $item->id)
        ->latest('id')
        ->firstOrFail();
}

function manufacturingCompletionValueEntryForItemLedger(ItemLedgerEntry $entry): ValueEntry
{
    return ValueEntry::query()
        ->where('item_ledger_entry_no', $entry->entry_number)
        ->where('document_no', $entry->document_number)
        ->where('document_line_no', $entry->document_line_number)
        ->firstOrFail();
}

function manufacturingCompletionConsumptionCost(ProductionOrder $order): float
{
    return round((float) $order->fresh()->itemLedgerEntries()
        ->where('entry_type', ItemLedgerEntryType::CONSUMPTION)
        ->sum('cost_amount_actual'), 4);
}

/**
 * @param  array<int, Item>  $items
 */
function manufacturingCompletionDirectConsumptionCost(ProductionOrder $order, array $items): float
{
    return round((float) $order->fresh()->itemLedgerEntries()
        ->where('entry_type', ItemLedgerEntryType::CONSUMPTION)
        ->whereIn('item_id', collect($items)->pluck('id')->all())
        ->sum('cost_amount_actual'), 4);
}
