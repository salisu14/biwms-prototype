<?php

use App\Enums\ItemLedgerEntryType;
use App\Enums\ProductionOrderStatus;
use App\Models\CapacityLedgerEntry;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Manufacturing\ProductionPerformanceReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('production performance report uses posted output quantity and standard cost fallback', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $generalBusinessPostingGroup = GeneralBusinessPostingGroup::factory()->create();
    $location = Location::factory()->create(['code' => 'MAIN']);
    $baseUom = UnitOfMeasure::create([
        'uom_code' => 'PCS',
        'description' => 'Pieces',
        'conversion_factor' => 1,
        'is_base_uom' => true,
    ]);

    $finishedGood = Item::factory()->create([
        'base_uom_id' => $baseUom->id,
        'standard_cost' => 10,
        'unit_cost' => 12,
    ]);

    $rawMaterial = Item::factory()->create([
        'base_uom_id' => $baseUom->id,
        'standard_cost' => 4,
        'unit_cost' => 4,
    ]);

    $order = ProductionOrder::create([
        'document_number' => 'PO-PERF-001',
        'status' => ProductionOrderStatus::FINISHED,
        'item_id' => $finishedGood->id,
        'quantity' => 1,
        'quantity_base' => 1,
        'unit_of_measure_code' => 'PCS',
        'starting_date_time' => Carbon::parse('2026-06-01 08:00:00'),
        'finished_at' => Carbon::parse('2026-06-01 12:00:00'),
        'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
        'general_product_posting_group_id' => $finishedGood->general_product_posting_group_id,
        'inventory_posting_group_id' => $finishedGood->inventory_posting_group_id,
        'cost_rollup' => 0,
        'unit_cost' => 0,
        'location_code' => $location->code,
        'created_by' => $user->id,
    ]);

    CapacityLedgerEntry::create([
        'production_order_id' => $order->id,
        'posting_date' => Carbon::parse('2026-06-01'),
        'document_number' => $order->document_number,
        'setup_time' => 0,
        'run_time' => 1,
        'direct_cost' => 15,
        'overhead_cost' => 5,
        'unit_cost' => 4,
        'total_cost' => 20,
    ]);

    ItemLedgerEntry::create([
        'entry_type' => ItemLedgerEntryType::CONSUMPTION,
        'document_number' => $order->document_number,
        'document_line_number' => 10000,
        'item_id' => $rawMaterial->id,
        'location_id' => $location->id,
        'general_product_posting_group_id' => $rawMaterial->general_product_posting_group_id,
        'inventory_posting_group_id' => $rawMaterial->inventory_posting_group_id,
        'quantity' => -5,
        'remaining_quantity' => -5,
        'cost_amount_actual' => 30,
        'posting_date' => Carbon::parse('2026-06-01'),
        'entry_date' => Carbon::parse('2026-06-01 09:00:00'),
        'source_type' => ProductionOrder::class,
        'source_id' => $order->id,
        'open' => false,
    ]);

    ItemLedgerEntry::create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_number' => $order->document_number,
        'document_line_number' => 20000,
        'item_id' => $finishedGood->id,
        'location_id' => $location->id,
        'general_product_posting_group_id' => $finishedGood->general_product_posting_group_id,
        'inventory_posting_group_id' => $finishedGood->inventory_posting_group_id,
        'quantity' => 5,
        'remaining_quantity' => 5,
        'cost_amount_actual' => 0,
        'posting_date' => Carbon::parse('2026-06-01'),
        'entry_date' => Carbon::parse('2026-06-01 11:00:00'),
        'source_type' => ProductionOrder::class,
        'source_id' => $order->id,
        'open' => true,
    ]);

    $row = app(ProductionPerformanceReportService::class)
        ->query()
        ->where('production_orders.id', $order->id)
        ->firstOrFail();

    expect((float) $row->produced_qty_sql)->toBe(5.0)
        ->and($row->standard_cost_source_sql)->toBe('item_standard_cost')
        ->and((float) $row->standard_unit_cost_sql)->toBe(10.0)
        ->and((float) $row->standard_total_cost_sql)->toBe(50.0)
        ->and((float) $row->actual_total_cost_sql)->toBe(50.0)
        ->and((float) $row->actual_unit_cost_sql)->toBe(10.0)
        ->and((float) $row->variance_amount_sql)->toBe(0.0)
        ->and((float) $row->variance_percent_sql)->toBe(0.0)
        ->and($row->unit_of_measure_code)->toBe('PCS');
});

test('production performance report keeps base quantity and standard unit cost separate for order uom', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    $generalBusinessPostingGroup = GeneralBusinessPostingGroup::factory()->create();
    $location = Location::factory()->create(['code' => 'MAIN']);
    $baseUom = UnitOfMeasure::create([
        'uom_code' => 'PCS',
        'description' => 'Pieces',
        'conversion_factor' => 1,
        'is_base_uom' => true,
    ]);
    $orderUom = UnitOfMeasure::create([
        'uom_code' => 'CT',
        'description' => 'Carton',
        'conversion_factor' => 288,
        'is_base_uom' => false,
    ]);

    $finishedGood = Item::factory()->create([
        'base_uom_id' => $baseUom->id,
        'standard_cost' => 850,
        'unit_cost' => 850,
    ]);

    $rawMaterial = Item::factory()->create([
        'base_uom_id' => $baseUom->id,
        'standard_cost' => 4,
        'unit_cost' => 4,
    ]);

    $finishedGood->uoms()->attach($baseUom->id, [
        'uom_type' => 'BASE',
        'conversion_factor' => 1,
        'is_default' => true,
    ]);
    $finishedGood->uoms()->attach($orderUom->id, [
        'uom_type' => 'MANUFACTURING',
        'conversion_factor' => 288,
        'is_default' => true,
    ]);

    $order = ProductionOrder::create([
        'document_number' => 'PO-PERF-CT-001',
        'status' => ProductionOrderStatus::FINISHED,
        'item_id' => $finishedGood->id,
        'quantity' => 1,
        'quantity_base' => 288,
        'unit_of_measure_code' => 'CT',
        'starting_date_time' => Carbon::parse('2026-06-01 08:00:00'),
        'finished_at' => Carbon::parse('2026-06-01 12:00:00'),
        'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
        'general_product_posting_group_id' => $finishedGood->general_product_posting_group_id,
        'inventory_posting_group_id' => $finishedGood->inventory_posting_group_id,
        'cost_rollup' => 0,
        'unit_cost' => 0,
        'location_code' => $location->code,
        'created_by' => $user->id,
    ]);

    ItemLedgerEntry::create([
        'entry_type' => ItemLedgerEntryType::CONSUMPTION,
        'document_number' => $order->document_number,
        'document_line_number' => 10000,
        'item_id' => $rawMaterial->id,
        'location_id' => $location->id,
        'general_product_posting_group_id' => $rawMaterial->general_product_posting_group_id,
        'inventory_posting_group_id' => $rawMaterial->inventory_posting_group_id,
        'quantity' => -288,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 1300,
        'posting_date' => Carbon::parse('2026-06-01'),
        'entry_date' => Carbon::parse('2026-06-01 09:00:00'),
        'source_type' => ProductionOrder::class,
        'source_id' => $order->id,
        'open' => false,
    ]);

    ItemLedgerEntry::create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_number' => $order->document_number,
        'document_line_number' => 20000,
        'item_id' => $finishedGood->id,
        'location_id' => $location->id,
        'general_product_posting_group_id' => $finishedGood->general_product_posting_group_id,
        'inventory_posting_group_id' => $finishedGood->inventory_posting_group_id,
        'quantity' => 288,
        'remaining_quantity' => 288,
        'cost_amount_actual' => 1300,
        'posting_date' => Carbon::parse('2026-06-01'),
        'entry_date' => Carbon::parse('2026-06-01 11:00:00'),
        'source_type' => ProductionOrder::class,
        'source_id' => $order->id,
        'open' => true,
    ]);

    $row = app(ProductionPerformanceReportService::class)
        ->query()
        ->where('production_orders.id', $order->id)
        ->firstOrFail();

    expect((float) $row->produced_qty_sql)->toBe(288.0)
        ->and($row->base_unit_of_measure)->toBe('PCS')
        ->and($row->unit_of_measure_code)->toBe('CT')
        ->and($row->standard_cost_source_sql)->toBe('item_standard_cost')
        ->and((float) $row->standard_unit_cost_sql)->toBe(850.0)
        ->and((float) $row->standard_total_cost_sql)->toBe(244800.0)
        ->and((float) $row->actual_total_cost_sql)->toBe(1300.0)
        ->and((float) $row->variance_amount_sql)->toBe(-243500.0);
});
