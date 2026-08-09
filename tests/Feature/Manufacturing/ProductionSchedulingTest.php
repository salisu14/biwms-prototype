<?php

declare(strict_types=1);

use App\Enums\ItemType;
use App\Enums\ProductionOrderSourceType;
use App\Enums\ProductionOrderStatus;
use App\Enums\ProductionSchedulingMode;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralProductPostingGroup;
use App\Models\InventoryPostingGroup;
use App\Models\Item;
use App\Models\Manufacturing\MachineCenter;
use App\Models\Manufacturing\ProductionAlternateResource;
use App\Models\Manufacturing\ProductionOperationSchedule;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\ProductionOrderComponent;
use App\Models\Manufacturing\ProductionOrderRoutingLine;
use App\Models\Manufacturing\ProductionSchedule;
use App\Models\Manufacturing\WorkCenter;
use App\Models\Manufacturing\WorkCenterCalendar;
use App\Models\Manufacturing\WorkCenterGroup;
use App\Models\Permission;
use App\Models\User;
use App\Services\Manufacturing\ProductionCampaignPlanningService;
use App\Services\Manufacturing\ProductionSchedulingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

uses(RefreshDatabase::class);

it('forward schedules routing operations without creating posting records', function (): void {
    $fixture = phase2cFixture();
    $beforeCounts = phase2cPostingCounts();

    $result = app(ProductionSchedulingService::class)->generate([
        'production_order_ids' => [$fixture['order']->id],
        'horizon_start_at' => '2026-08-10 08:00:00',
        'horizon_end_at' => '2026-08-10 17:00:00',
        'mode' => ProductionSchedulingMode::Forward,
    ]);

    expect($result->schedule)->toBeInstanceOf(ProductionSchedule::class)
        ->and($result->summary['operations_scheduled'])->toBe(2)
        ->and($result->exceptions)->toBeEmpty()
        ->and(ProductionOperationSchedule::query()->count())->toBe(2)
        ->and(phase2cPostingCounts())->toBe($beforeCounts);

    $first = ProductionOperationSchedule::query()->orderBy('sequence')->firstOrFail();
    $second = ProductionOperationSchedule::query()->orderByDesc('sequence')->firstOrFail();

    expect($first->scheduled_start_at->format('H:i'))->toBe('08:00')
        ->and($second->scheduled_start_at->greaterThanOrEqualTo($first->scheduled_finish_at))->toBeTrue();
});

it('backward schedules from due date and flags infeasible horizons', function (): void {
    $fixture = phase2cFixture();

    $result = app(ProductionSchedulingService::class)->generate([
        'production_order_ids' => [$fixture['order']->id],
        'horizon_start_at' => '2026-08-10 08:00:00',
        'horizon_end_at' => '2026-08-10 17:00:00',
        'mode' => ProductionSchedulingMode::Backward,
    ]);

    expect($result->summary['operations_scheduled'])->toBe(2)
        ->and(ProductionOperationSchedule::query()->max('scheduled_finish_at'))->not->toBeNull();

    $impossible = app(ProductionSchedulingService::class)->generate([
        'production_order_ids' => [$fixture['order']->id],
        'horizon_start_at' => '2026-08-10 16:30:00',
        'horizon_end_at' => '2026-08-10 17:00:00',
        'mode' => ProductionSchedulingMode::Backward,
    ]);

    expect($impossible->exceptions)->not->toBeEmpty();
});

it('prevents exclusive machine overlap and selects a configured alternate', function (): void {
    $fixture = phase2cFixture();
    $alternateMachine = MachineCenter::query()->create([
        'code' => 'EXT-TANK-02',
        'name' => 'Extraction Tank 2',
        'work_center_id' => $fixture['workCenter']->id,
        'capacity' => 1,
        'efficiency' => 100,
    ]);

    ProductionAlternateResource::query()->create([
        'primary_machine_center_id' => $fixture['machineCenter']->id,
        'alternate_machine_center_id' => $alternateMachine->id,
        'priority' => 10,
        'is_active' => true,
    ]);

    $blockingSchedule = ProductionSchedule::query()->create([
        'schedule_no' => 'BLOCKER',
        'horizon_start_at' => '2026-08-10 08:00:00',
        'horizon_end_at' => '2026-08-10 17:00:00',
        'status' => 'generated',
        'scheduling_mode' => 'forward',
    ]);
    ProductionOperationSchedule::query()->create([
        'production_schedule_id' => $blockingSchedule->id,
        'production_order_id' => $fixture['order']->id,
        'production_order_routing_line_id' => $fixture['firstRoutingLine']->id,
        'work_center_id' => $fixture['workCenter']->id,
        'machine_center_id' => $fixture['machineCenter']->id,
        'scheduled_start_at' => '2026-08-10 08:00:00',
        'scheduled_finish_at' => '2026-08-10 09:30:00',
        'capacity_required_minutes' => 90,
        'quantity_base' => 100,
        'idempotency_key' => 'blocking-machine-slot',
    ]);

    $result = app(ProductionSchedulingService::class)->generate([
        'production_order_ids' => [$fixture['order']->id],
        'horizon_start_at' => '2026-08-10 08:00:00',
        'horizon_end_at' => '2026-08-10 17:00:00',
        'mode' => ProductionSchedulingMode::Forward,
    ]);

    $scheduled = ProductionOperationSchedule::query()
        ->where('production_schedule_id', $result->schedule->id)
        ->where('production_order_routing_line_id', $fixture['firstRoutingLine']->id)
        ->firstOrFail();

    expect($scheduled->machine_center_id)->toBe($alternateMachine->id)
        ->and($scheduled->uses_alternate_resource)->toBeTrue();
});

it('detects schedule reconciliation overlap fixtures', function (): void {
    $fixture = phase2cFixture();
    $schedule = ProductionSchedule::query()->create([
        'schedule_no' => 'BROKEN',
        'horizon_start_at' => '2026-08-10 08:00:00',
        'horizon_end_at' => '2026-08-10 17:00:00',
        'status' => 'generated',
        'scheduling_mode' => 'forward',
    ]);

    foreach (['broken-1', 'broken-2'] as $key) {
        ProductionOperationSchedule::query()->create([
            'production_schedule_id' => $schedule->id,
            'production_order_id' => $fixture['order']->id,
            'production_order_routing_line_id' => $fixture['firstRoutingLine']->id,
            'work_center_id' => $fixture['workCenter']->id,
            'machine_center_id' => $fixture['machineCenter']->id,
            'scheduled_start_at' => '2026-08-10 08:00:00',
            'scheduled_finish_at' => '2026-08-10 09:00:00',
            'capacity_required_minutes' => 60,
            'quantity_base' => 100,
            'idempotency_key' => $key,
        ]);
    }

    $this->artisan('biwms:production-schedule-reconcile --json')
        ->assertSuccessful()
        ->expectsOutputToContain('overlapping_exclusive_machine_assignment');
});

it('creates planner selected campaigns without posting side effects', function (): void {
    $fixture = phase2cFixture();
    $secondOrder = phase2cProductionOrder('PROD-2C-002', $fixture['item']);
    $beforeCounts = phase2cPostingCounts();

    $campaign = app(ProductionCampaignPlanningService::class)->createPlannerSelected(
        'CAMP-2C-001',
        'Herbal extraction run',
        [$fixture['order']->id, $secondOrder->id],
        $fixture['workCenter']->id,
    );

    expect($campaign->orders)->toHaveCount(2)
        ->and(phase2cPostingCounts())->toBe($beforeCounts);
});

it('flags known material shortages without generating procurement or inventory movements', function (): void {
    $fixture = phase2cFixture();
    $componentItem = Item::factory()->create(['inventory' => 5]);
    ProductionOrderComponent::query()->create([
        'production_order_id' => $fixture['order']->id,
        'line_number' => 10000,
        'item_id' => $componentItem->id,
        'description' => 'Raw herb',
        'unit_of_measure_code' => 'KG',
        'quantity_per' => 1,
        'expected_quantity' => 20,
        'expected_quantity_base' => 20,
        'remaining_quantity' => 20,
        'is_manufactured_requirement' => false,
    ]);
    $beforeCounts = phase2cPostingCounts();

    $result = app(ProductionSchedulingService::class)->generate([
        'production_order_ids' => [$fixture['order']->id],
        'horizon_start_at' => '2026-08-10 08:00:00',
        'horizon_end_at' => '2026-08-10 17:00:00',
        'mode' => ProductionSchedulingMode::Forward,
    ]);

    expect(collect($result->exceptions)->pluck('exception_type')->map(fn ($type) => $type->value ?? $type)->contains('material_unavailable'))->toBeTrue()
        ->and(phase2cPostingCounts())->toBe($beforeCounts);
});

it('protects planner permissions separately from operator execution permissions', function (): void {
    $planner = User::factory()->create();
    $operator = User::factory()->create();
    Permission::query()->firstOrCreate(['name' => 'manufacturing.production_schedule.generate', 'guard_name' => 'web']);
    Permission::query()->firstOrCreate(['name' => 'factory.production_operation_execution.start', 'guard_name' => 'web']);

    $planner->givePermissionTo('manufacturing.production_schedule.generate');
    $operator->givePermissionTo('factory.production_operation_execution.start');

    expect(Gate::forUser($planner)->allows('generate', ProductionSchedule::class))->toBeTrue()
        ->and(Gate::forUser($operator)->allows('generate', ProductionSchedule::class))->toBeFalse();
});

it('requires freeze horizon override before rescheduling frozen near-term operations', function (): void {
    Carbon::setTestNow('2026-08-10 07:00:00');
    $fixture = phase2cFixture();
    $result = app(ProductionSchedulingService::class)->generate([
        'production_order_ids' => [$fixture['order']->id],
        'horizon_start_at' => '2026-08-10 08:00:00',
        'horizon_end_at' => '2026-08-10 17:00:00',
        'mode' => ProductionSchedulingMode::Forward,
        'freeze_horizon_minutes' => 480,
    ]);

    expect(fn () => app(ProductionSchedulingService::class)->reschedule($result->schedule, ['reason' => 'breakdown']))
        ->toThrow(RuntimeException::class, 'freeze horizon');

    $rescheduled = app(ProductionSchedulingService::class)->reschedule($result->schedule, [
        'reason' => 'breakdown',
        'override_freeze' => true,
    ]);

    expect($rescheduled->schedule)->toBeInstanceOf(ProductionSchedule::class)
        ->and($result->schedule->fresh()->status->value)->toBe('superseded');

    Carbon::setTestNow();
});

/**
 * @return array<string, mixed>
 */
function phase2cFixture(): array
{
    $group = WorkCenterGroup::query()->create(['code' => 'SECTION-A', 'name' => 'Extraction and Mixing']);
    $workCenter = WorkCenter::query()->create([
        'code' => 'EXTRACTION',
        'name' => 'Extraction',
        'work_center_group_id' => $group->id,
        'unit_of_measure_code' => 'MINUTES',
        'capacity' => 1,
        'efficiency' => 100,
        'queue_time' => 0,
    ]);
    $machineCenter = MachineCenter::query()->create([
        'code' => 'EXT-TANK-01',
        'name' => 'Extraction Tank 1',
        'work_center_id' => $workCenter->id,
        'capacity' => 1,
        'efficiency' => 100,
    ]);

    WorkCenterCalendar::query()->create([
        'work_center_id' => $workCenter->id,
        'date' => '2026-08-10',
        'is_working_day' => true,
        'start_time' => '08:00:00',
        'end_time' => '17:00:00',
        'capacity' => 540,
        'efficiency' => 100,
    ]);

    $item = Item::factory()->create(['item_type' => ItemType::FINISHED_GOOD]);
    $user = User::factory()->create();
    $order = phase2cProductionOrder('PROD-2C-001', $item, $user);
    $firstRoutingLine = phase2cRoutingLine($order, $workCenter, $machineCenter, 10000, '10', 30, 60);
    phase2cRoutingLine($order, $workCenter, null, 20000, '20', 15, 45);

    return compact('group', 'workCenter', 'machineCenter', 'item', 'order', 'firstRoutingLine', 'user');
}

function phase2cProductionOrder(string $documentNumber, Item $item, ?User $user = null): ProductionOrder
{
    $user ??= User::factory()->create();
    $businessGroup = GeneralBusinessPostingGroup::query()->firstOrCreate(['code' => 'MFG-2C'], ['description' => 'MFG 2C']);
    $productGroup = GeneralProductPostingGroup::query()->firstOrCreate(['code' => 'FG-2C'], ['description' => 'FG 2C']);
    $inventoryGroup = InventoryPostingGroup::query()->firstOrCreate(['code' => 'INV-2C'], ['description' => 'INV 2C']);

    return ProductionOrder::query()->create([
        'document_number' => $documentNumber,
        'status' => ProductionOrderStatus::RELEASED,
        'source_type' => ProductionOrderSourceType::ITEM,
        'source_no' => $item->item_code,
        'item_id' => $item->id,
        'description' => $item->description,
        'quantity' => 100,
        'quantity_base' => 100,
        'unit_of_measure_code' => 'PCS',
        'general_business_posting_group_id' => $businessGroup->id,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
        'due_date' => '2026-08-10',
        'starting_date_time' => '2026-08-10 08:00:00',
        'priority' => 10,
        'created_by' => $user->id,
    ]);
}

function phase2cRoutingLine(ProductionOrder $order, WorkCenter $workCenter, ?MachineCenter $machineCenter, int $lineNumber, string $operationNo, int $setupMinutes, int $runMinutes): ProductionOrderRoutingLine
{
    return ProductionOrderRoutingLine::query()->create([
        'production_order_id' => $order->id,
        'line_number' => $lineNumber,
        'operation_no' => $operationNo,
        'description' => 'Operation '.$operationNo,
        'work_center_id' => $workCenter->id,
        'machine_center_id' => $machineCenter?->id,
        'setup_time' => $setupMinutes,
        'run_time' => $runMinutes,
        'wait_time' => 0,
        'move_time' => 0,
        'setup_time_unit' => 'MINUTES',
        'run_time_unit' => 'MINUTES',
        'expected_output_quantity' => 100,
    ]);
}

/**
 * @return array<string, int>
 */
function phase2cPostingCounts(): array
{
    return [
        'item_ledger_entries' => DB::table('item_ledger_entries')->count(),
        'item_application_entries' => DB::table('item_application_entries')->count(),
        'value_entries' => DB::table('value_entries')->count(),
        'capacity_ledger_entries' => DB::table('capacity_ledger_entries')->count(),
        'gl_entries' => DB::table('gl_entries')->count(),
    ];
}
