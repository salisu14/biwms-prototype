<?php

use App\Enums\ItemLedgerEntryType;
use App\Models\Business;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\ValueEntry;
use App\Services\Finance\ProfitabilityReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('item cost measures keep reference, inventory, and production costs distinct', function () {
    $item = Item::factory()->create([
        'standard_cost' => 12,
        'unit_price' => 20,
    ]);
    $legacyEntryNumbers = ItemLedgerEntry::query()->where('item_id', $item->id)->pluck('entry_number');
    ValueEntry::query()->whereIn('item_ledger_entry_no', $legacyEntryNumbers)->delete();
    ItemLedgerEntry::query()->where('item_id', $item->id)->delete();
    $location = Location::factory()->create();
    $business = Business::create(['code' => 'PROFIT-TEST', 'name' => 'Profitability Test', 'is_active' => true]);

    $nextItemLedgerEntryNo = random_int(100000000, 200000000);
    $entryDefaults = [
        'document_number' => 'PROFITABILITY-001',
        'document_line_number' => 10000,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'entry_date' => '2026-08-01 08:00:00',
        'open' => true,
        'dimensions' => ['business_id' => $business->id],
    ];
    $makeValue = function (array $values) use ($business): ValueEntry {
        return ValueEntry::create([
            'entry_no' => $values['entry_no'],
            'item_no' => (string) $values['item_no'],
            'location_code' => 'MAIN',
            'posting_date' => $values['posting_date'],
            'quantity' => $values['quantity'],
            'cost_amount_actual' => $values['cost_amount_actual'],
            'unit_cost' => $values['unit_cost'],
            'expected_cost' => false,
            'business_id' => $business->id,
            ...$values,
        ]);
    };

    $purchase = ItemLedgerEntry::create([
        ...$entryDefaults,
        'entry_number' => $nextItemLedgerEntryNo++,
        'entry_type' => ItemLedgerEntryType::PURCHASE,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'quantity' => 10,
        'remaining_quantity' => 10,
        'posting_date' => '2026-08-01',
        'cost_amount_actual' => 100,
    ]);
    $makeValue([
        'entry_no' => random_int(300000000, 310000000),
        'item_ledger_entry_no' => $purchase->entry_number,
        'item_ledger_entry_type' => 1,
        'item_no' => $item->item_code,
        'posting_date' => '2026-08-01',
        'quantity' => 10,
        'cost_amount_actual' => 100,
        'unit_cost' => 10,
        'expected_cost' => false,
    ]);

    $sale = ItemLedgerEntry::create([
        ...$entryDefaults,
        'entry_number' => $nextItemLedgerEntryNo++,
        'entry_type' => ItemLedgerEntryType::SALE,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'quantity' => -2,
        'remaining_quantity' => 0,
        'posting_date' => '2026-08-02',
        'cost_amount_actual' => 20,
    ]);
    $makeValue([
        'entry_no' => random_int(320000000, 330000000),
        'item_ledger_entry_no' => $sale->entry_number,
        'item_ledger_entry_type' => 2,
        'item_no' => $item->item_code,
        'posting_date' => '2026-08-02',
        'quantity' => -2,
        'cost_amount_actual' => 20,
        'unit_cost' => 10,
        'expected_cost' => false,
    ]);

    $output = ItemLedgerEntry::create([
        ...$entryDefaults,
        'entry_number' => $nextItemLedgerEntryNo++,
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'quantity' => 5,
        'remaining_quantity' => 5,
        'posting_date' => '2026-08-03',
        'cost_amount_actual' => 75,
    ]);
    $makeValue([
        'entry_no' => random_int(340000000, 350000000),
        'item_ledger_entry_no' => $output->entry_number,
        'item_ledger_entry_type' => 7,
        'item_no' => $item->item_code,
        'posting_date' => '2026-08-03',
        'quantity' => 5,
        'cost_amount_actual' => 75,
        'unit_cost' => 15,
        'expected_cost' => false,
    ]);

    $service = app(ProfitabilityReportService::class);

    $indicative = $service->itemIndicativeMeasures($item);

    $costs = $service->itemCostMeasures($item, $business->id);

    expect($indicative)
        ->toMatchArray([
            'selling_price' => 20.0,
            'standard_reference_cost' => 12.0,
            'indicative_unit_margin' => 8.0,
            'indicative_margin_percent' => 40.0,
        ])
        ->and(round((float) $indicative['markup_percent'], 2))->toBe(66.67)
        ->and($costs['last_actual_production_cost'])->toBeGreaterThan(0)
        ->and($costs['average_actual_production_cost'])->toBeGreaterThan(0)
        ->and($costs['current_actual_inventory_cost'])->toBeGreaterThan(0)
        ->and($costs['current_actual_inventory_cost'])->not->toBe((float) $item->standard_cost);
});

test('actual item performance is unavailable without posted sales', function () {
    $item = Item::factory()->create(['standard_cost' => 0]);

    expect(app(ProfitabilityReportService::class)->itemActualPerformance($item))
        ->toMatchArray([
            'quantity_sold' => null,
            'net_revenue' => null,
            'actual_cogs' => null,
            'gross_profit' => null,
            'gross_margin_percent' => null,
        ]);
});
