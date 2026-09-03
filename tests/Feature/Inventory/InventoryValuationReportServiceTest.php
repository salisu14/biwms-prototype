<?php

use App\Enums\ItemLedgerEntryType;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Services\Inventory\InventoryValuationReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

function valuationEntry(Item $item, Location $location, array $attributes): ItemLedgerEntry
{
    return ItemLedgerEntry::query()->create(array_merge([
        'entry_type' => ItemLedgerEntryType::PURCHASE,
        'document_type' => 'PURCHASE_RECEIPT',
        'document_number' => 'VAL-001',
        'document_line_number' => 10000,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'quantity' => 1,
        'remaining_quantity' => 1,
        'cost_amount_actual' => 0,
        'cost_amount_expected' => 0,
        'posting_date' => Carbon::parse('2026-08-01'),
        'entry_date' => Carbon::parse('2026-08-01 08:00:00'),
        'open' => true,
    ], $attributes));
}

it('derives expected and actual valuation from value entries, not item stock', function (): void {
    $item = Item::factory()->create(['inventory' => 999]);
    $location = Location::factory()->create();

    valuationEntry($item, $location, [
        'document_number' => 'VAL-RECEIPT',
        'cost_amount_expected' => 100,
    ]);

    $rows = app(InventoryValuationReportService::class)->generate(
        '2026-08-01',
        '2026-08-31',
        ['location_id' => $location->id],
    );

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['closing_qty'])->toBe(1.0)
        ->and($rows->first()['expected_cost'])->toBe(100.0)
        ->and($rows->first()['inventory_value'])->toBe(100.0)
        ->and($rows->first()['unit_cost'])->toBe(100.0);
});

it('keeps outbound quantity and value movement signed while exposing positive unit cost', function (): void {
    $item = Item::factory()->create(['inventory' => 50]);
    $location = Location::factory()->create();

    valuationEntry($item, $location, [
        'entry_type' => ItemLedgerEntryType::PURCHASE,
        'document_number' => 'VAL-IN',
        'quantity' => 10,
        'remaining_quantity' => 10,
        'cost_amount_actual' => 100,
    ]);
    valuationEntry($item, $location, [
        'entry_type' => ItemLedgerEntryType::SALE,
        'document_number' => 'VAL-OUT',
        'quantity' => -2,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 20,
    ]);

    $row = app(InventoryValuationReportService::class)->generate('2026-08-01', '2026-08-31', [
        'location_id' => $location->id,
    ])->first();

    expect($row['closing_qty'])->toBe(8.0)
        ->and($row['inventory_value'])->toBe(80.0)
        ->and($row['sales_value'])->toBe(20.0)
        ->and($row['unit_cost'])->toBe(10.0);
});

it('groups valuation by item and location and supports explicit business filtering', function (): void {
    $item = Item::factory()->create();
    $firstLocation = Location::factory()->create();
    $secondLocation = Location::factory()->create();

    valuationEntry($item, $firstLocation, ['cost_amount_actual' => 25]);
    valuationEntry($item, $secondLocation, [
        'document_number' => 'VAL-SECOND',
        'cost_amount_actual' => 50,
    ]);

    $rows = app(InventoryValuationReportService::class)->generate('2026-08-01', '2026-08-31');

    expect($rows)->toHaveCount(2)
        ->and($rows->pluck('location_id')->sort()->values()->all())
        ->toBe(collect([$firstLocation->id, $secondLocation->id])->sort()->values()->all());
});

it('returns a safe zero unit cost when the ledger quantity is zero', function (): void {
    $item = Item::factory()->create();
    $location = Location::factory()->create();

    valuationEntry($item, $location, [
        'quantity' => 0,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 0,
    ]);

    $row = app(InventoryValuationReportService::class)->generate('2026-08-01', '2026-08-31')->first();

    expect($row['closing_qty'])->toBe(0.0)
        ->and($row['inventory_value'])->toBe(0.0)
        ->and($row['unit_cost'])->toBe(0.0);
});
