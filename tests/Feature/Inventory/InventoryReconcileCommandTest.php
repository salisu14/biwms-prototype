<?php

use App\Enums\ItemLedgerEntryType;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\ValueEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('reports clean inventory ledger state', function (): void {
    $location = Location::factory()->create();
    $item = Item::factory()->create([
        'inventory' => 10,
        'unit_cost' => 12,
    ]);

    ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::PURCHASE,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'PI-CLEAN-001',
        'document_line_number' => 10000,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'quantity' => 10,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 120,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
    ]);

    expect(Artisan::call('biwms:inventory-reconcile', ['--json' => true]))->toBe(0);

    $report = json_decode(trim(Artisan::output()), true);

    expect($report['stock_mismatches'])->toBeEmpty()
        ->and($report['negative_stock_violations'])->toBeEmpty()
        ->and($report['open_item_ledger_entries'])->toBeEmpty()
        ->and($report['missing_value_entries'])->toBeEmpty()
        ->and($report['value_entry_mismatches'])->toBeEmpty();
});

it('reports stock, negative quantity, open entry, missing value, and value mismatch issues', function (): void {
    $location = Location::factory()->create();
    $item = Item::factory()->create([
        'inventory' => 5,
        'unit_cost' => 10,
    ]);

    $entry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::SALE,
        'document_type' => 'SALES_INVOICE',
        'document_number' => 'SI-BAD-001',
        'document_line_number' => 10000,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'quantity' => -7,
        'remaining_quantity' => -7,
        'cost_amount_actual' => 70,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => true,
    ]);

    ValueEntry::query()
        ->where('item_ledger_entry_no', $entry->entry_number)
        ->update([
            'quantity' => -6,
            'cost_amount_actual' => 60,
        ]);

    $missingValueEntry = ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::PURCHASE,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'PI-MISSING-VALUE-001',
        'document_line_number' => 10000,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'quantity' => 1,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 10,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
    ]);

    ValueEntry::query()
        ->where('item_ledger_entry_no', $missingValueEntry->entry_number)
        ->delete();

    expect(Artisan::call('biwms:inventory-reconcile', ['--json' => true]))->toBe(0);

    $report = json_decode(trim(Artisan::output()), true);

    expect($report['stock_mismatches'])->toHaveCount(1)
        ->and($report['negative_stock_violations'])->toHaveCount(1)
        ->and($report['open_item_ledger_entries'])->toHaveCount(1)
        ->and($report['missing_value_entries'])->toHaveCount(1)
        ->and($report['value_entry_mismatches'])->toHaveCount(1);
});
