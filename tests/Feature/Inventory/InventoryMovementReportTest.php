<?php

use App\Enums\ItemLedgerEntryType;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Services\InventoryReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;

uses(RefreshDatabase::class);

test('inventory movement summary includes production movements with signed values', function () {
    $location = Location::factory()->create();
    $baseUom = UnitOfMeasure::create([
        'uom_code' => 'PCS',
        'description' => 'Pieces',
        'conversion_factor' => 1,
        'is_base_uom' => true,
    ]);

    $item = Item::factory()->create([
        'base_uom_id' => $baseUom->id,
    ]);

    ItemLedgerEntry::create([
        'entry_type' => ItemLedgerEntryType::PURCHASE,
        'document_number' => 'OPEN-1',
        'document_line_number' => 10000,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'quantity' => 10,
        'remaining_quantity' => 10,
        'cost_amount_actual' => 100,
        'posting_date' => Carbon::parse('2026-05-31'),
        'entry_date' => Carbon::parse('2026-05-31 08:00:00'),
        'open' => true,
    ]);

    ItemLedgerEntry::create([
        'entry_type' => ItemLedgerEntryType::OUTPUT,
        'document_number' => 'PROD-1',
        'document_line_number' => 20000,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'quantity' => 1,
        'remaining_quantity' => 1,
        'cost_amount_actual' => 15,
        'posting_date' => Carbon::parse('2026-06-01'),
        'entry_date' => Carbon::parse('2026-06-01 09:00:00'),
        'open' => true,
    ]);

    ItemLedgerEntry::create([
        'entry_type' => ItemLedgerEntryType::CONSUMPTION,
        'document_number' => 'PROD-1',
        'document_line_number' => 30000,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'quantity' => -2,
        'remaining_quantity' => -2,
        'cost_amount_actual' => 20,
        'posting_date' => Carbon::parse('2026-06-01'),
        'entry_date' => Carbon::parse('2026-06-01 10:00:00'),
        'open' => false,
    ]);

    $row = app(InventoryReportService::class)
        ->getMovementSummary(
            startDate: Carbon::parse('2026-06-01'),
            endDate: Carbon::parse('2026-06-30'),
            locationId: $location->id,
        )
        ->whereKey($item->id)
        ->firstOrFail();

    expect((float) $row->opening_qty)->toBe(10.0)
        ->and((float) $row->opening_value)->toBe(100.0)
        ->and((float) $row->production_output_qty)->toBe(1.0)
        ->and((float) $row->production_output_value)->toBe(15.0)
        ->and((float) $row->production_consumption_qty)->toBe(-2.0)
        ->and((float) $row->production_consumption_value)->toBe(-20.0);

    $closingQuantity = (float) $row->opening_qty
        + (float) $row->purchase_in_qty + (float) $row->purchase_out_qty
        + (float) $row->pos_adj_qty + (float) $row->neg_adj_qty
        + (float) $row->production_output_qty + (float) $row->production_consumption_qty
        + (float) $row->assembly_output_qty + (float) $row->assembly_consumption_qty
        + (float) $row->sale_out_qty + (float) $row->sale_in_qty
        + (float) $row->transfer_qty;

    $closingValue = (float) $row->opening_value
        + (float) $row->purchase_in_value + (float) $row->purchase_out_value
        + (float) $row->pos_adj_value + (float) $row->neg_adj_value
        + (float) $row->production_output_value + (float) $row->production_consumption_value
        + (float) $row->assembly_output_value + (float) $row->assembly_consumption_value
        + (float) $row->sale_out_value + (float) $row->sale_in_value
        + (float) $row->transfer_value;

    expect($closingQuantity)->toBe(9.0)
        ->and($closingValue)->toBe(95.0)
        ->and($row->base_unit_of_measure)->toBe('PCS');
});
