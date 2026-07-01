<?php

use App\Enums\ItemLedgerEntryType;
use App\Enums\ShipmentStatus;
use App\Enums\SourceDocument;
use App\Enums\WarehouseReceiptStatus;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\ValueEntry;
use App\Models\WarehouseReceipt;
use App\Models\WarehouseShipment;
use App\Services\Inventory\StockMovementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('moves stock between locations without changing total stock', function (): void {
    [$item, $sourceLocation, $destinationLocation] = warehouseMovementFixture(10);

    $entries = app(StockMovementService::class)->transfer(
        item: $item,
        sourceLocation: $sourceLocation,
        destinationLocation: $destinationLocation,
        quantityBase: 4,
        documentNumber: 'TR-VALID-001',
        postingDate: now(),
    );

    expect((float) locationLedgerQuantity($item, $sourceLocation))->toBe(6.0)
        ->and((float) locationLedgerQuantity($item, $destinationLocation))->toBe(4.0)
        ->and((float) ItemLedgerEntry::query()->where('item_id', $item->id)->sum('quantity'))->toBe(10.0)
        ->and((float) $item->fresh()->inventory)->toBe(10.0)
        ->and((float) $entries['source']->quantity)->toBe(-4.0)
        ->and((float) $entries['destination']->quantity)->toBe(4.0)
        ->and(ValueEntry::query()->where('item_ledger_entry_no', $entries['source']->entry_number)->exists())->toBeTrue()
        ->and(ValueEntry::query()->where('item_ledger_entry_no', $entries['destination']->entry_number)->exists())->toBeTrue();
});

it('blocks transfers with insufficient source stock and duplicate posting', function (): void {
    [$item, $sourceLocation, $destinationLocation] = warehouseMovementFixture(3);

    expect(fn () => app(StockMovementService::class)->transfer(
        item: $item,
        sourceLocation: $sourceLocation,
        destinationLocation: $destinationLocation,
        quantityBase: 4,
        documentNumber: 'TR-INSUFFICIENT-001',
        postingDate: now(),
    ))->toThrow(RuntimeException::class, 'Insufficient stock');

    app(StockMovementService::class)->transfer(
        item: $item,
        sourceLocation: $sourceLocation,
        destinationLocation: $destinationLocation,
        quantityBase: 2,
        documentNumber: 'TR-DUP-001',
        postingDate: now(),
    );

    expect(fn () => app(StockMovementService::class)->transfer(
        item: $item,
        sourceLocation: $sourceLocation,
        destinationLocation: $destinationLocation,
        quantityBase: 1,
        documentNumber: 'TR-DUP-001',
        postingDate: now(),
    ))->toThrow(RuntimeException::class, 'already been posted');
});

it('posts warehouse receipt into stock once', function (): void {
    [$item, $location] = warehouseMovementFixture(0);

    $receipt = WarehouseReceipt::query()->create([
        'document_number' => 'WR-POST-001',
        'location_id' => $location->id,
        'source_document' => SourceDocument::PURCHASE_ORDER->value,
        'source_document_id' => 1,
        'source_document_number' => 'PO-WR-001',
        'status' => WarehouseReceiptStatus::RELEASED,
        'receipt_date' => now()->toDateString(),
    ]);

    $receipt->lines()->create([
        'line_number' => 10000,
        'item_id' => $item->id,
        'description' => $item->description,
        'quantity' => 5,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'source_line_id' => 1,
    ]);

    app(StockMovementService::class)->postWarehouseReceipt($receipt);

    expect((float) locationLedgerQuantity($item, $location))->toBe(5.0)
        ->and((float) $item->fresh()->inventory)->toBe(5.0)
        ->and(ItemLedgerEntry::query()
            ->where('document_type', 'WAREHOUSE_RECEIPT')
            ->where('document_number', 'WR-POST-001')
            ->count())->toBe(1);

    expect(fn () => app(StockMovementService::class)->postWarehouseReceipt($receipt->fresh()))
        ->toThrow(RuntimeException::class, 'already been posted');
});

it('posts warehouse shipment out of stock once', function (): void {
    [$item, $location] = warehouseMovementFixture(10);

    $shipment = WarehouseShipment::query()->create([
        'document_number' => 'WS-POST-001',
        'location_id' => $location->id,
        'source_document' => SourceDocument::TRANSFER_ORDER->value,
        'source_document_id' => 1,
        'source_document_number' => 'TO-WS-001',
        'status' => ShipmentStatus::RELEASED->value,
        'shipment_date' => now()->toDateString(),
    ]);

    $shipment->lines()->create([
        'line_number' => 10000,
        'item_id' => $item->id,
        'description' => $item->description,
        'quantity' => 4,
        'quantity_picked' => 4,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'source_line_id' => 1,
    ]);

    app(StockMovementService::class)->postWarehouseShipment($shipment);

    expect((float) locationLedgerQuantity($item, $location))->toBe(6.0)
        ->and((float) $item->fresh()->inventory)->toBe(6.0)
        ->and(ItemLedgerEntry::query()
            ->where('document_type', 'WAREHOUSE_SHIPMENT')
            ->where('document_number', 'WS-POST-001')
            ->count())->toBe(1);

    expect(fn () => app(StockMovementService::class)->postWarehouseShipment($shipment->fresh()))
        ->toThrow(RuntimeException::class, 'already been posted');
});

it('reconcile detects unbalanced transfers location mismatch duplicate warehouse posting and missing warehouse ledgers', function (): void {
    [$item, $location] = warehouseMovementFixture(0);

    ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::TRANSFER,
        'document_type' => 'WAREHOUSE_TRANSFER',
        'document_number' => 'TR-BAD-001',
        'document_line_number' => 10000,
        'item_id' => $item->id,
        'location_id' => $location->id,
        'quantity' => -3,
        'remaining_quantity' => 0,
        'cost_amount_actual' => 30,
        'cost_amount_expected' => 0,
        'general_product_posting_group_id' => $item->general_product_posting_group_id,
        'inventory_posting_group_id' => $item->inventory_posting_group_id,
        'posting_date' => now(),
        'entry_date' => now(),
        'open' => false,
    ]);

    foreach ([1, 2] as $index) {
        ItemLedgerEntry::query()->create([
            'entry_type' => ItemLedgerEntryType::POSITIVE_ADJUSTMENT,
            'document_type' => 'WAREHOUSE_RECEIPT',
            'document_number' => 'WR-DUP-001',
            'document_line_number' => 10000,
            'item_id' => $item->id,
            'location_id' => $location->id,
            'quantity' => 1,
            'remaining_quantity' => 1,
            'cost_amount_actual' => 10,
            'cost_amount_expected' => 0,
            'general_product_posting_group_id' => $item->general_product_posting_group_id,
            'inventory_posting_group_id' => $item->inventory_posting_group_id,
            'posting_date' => now(),
            'entry_date' => now(),
            'open' => $index === 1,
        ]);
    }

    $receipt = WarehouseReceipt::query()->create([
        'document_number' => 'WR-MISSING-001',
        'location_id' => $location->id,
        'source_document' => SourceDocument::PURCHASE_ORDER->value,
        'source_document_id' => 2,
        'source_document_number' => 'PO-MISSING-001',
        'status' => WarehouseReceiptStatus::RECEIVED,
        'receipt_date' => now()->toDateString(),
        'posted_date' => now(),
    ]);

    $receipt->lines()->create([
        'line_number' => 10000,
        'item_id' => $item->id,
        'description' => $item->description,
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'source_line_id' => 1,
    ]);

    $shipment = WarehouseShipment::query()->create([
        'document_number' => 'WS-MISSING-001',
        'location_id' => $location->id,
        'source_document' => SourceDocument::TRANSFER_ORDER->value,
        'source_document_id' => 2,
        'source_document_number' => 'TO-MISSING-001',
        'status' => ShipmentStatus::SHIPPED->value,
        'shipment_date' => now()->toDateString(),
        'posted_date' => now(),
    ]);

    $shipment->lines()->create([
        'line_number' => 10000,
        'item_id' => $item->id,
        'description' => $item->description,
        'quantity' => 1,
        'quantity_picked' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'source_line_id' => 1,
    ]);

    expect(Artisan::call('biwms:inventory-reconcile', ['--json' => true]))->toBe(0);

    $report = json_decode(trim(Artisan::output()), true);

    expect($report['unbalanced_transfer_entries'])->toHaveCount(1)
        ->and($report['unbalanced_transfer_entries'][0]['classification'])->toBe('unbalanced_transfer_entries')
        ->and($report['transfer_source_destination_mismatches'])->toHaveCount(1)
        ->and($report['transfer_source_destination_mismatches'][0]['classification'])->toBe('transfer_source_destination_mismatch')
        ->and($report['duplicate_warehouse_postings'])->toHaveCount(1)
        ->and($report['duplicate_warehouse_postings'][0]['classification'])->toBe('duplicate_warehouse_posting')
        ->and(collect($report['missing_item_ledger_entries_for_posted_documents'])->pluck('classification')->all())
        ->toContain('warehouse_receipt_missing_item_ledger_entry', 'warehouse_shipment_missing_item_ledger_entry');
});

/**
 * @return array{0: Item, 1: Location, 2?: Location}
 */
function warehouseMovementFixture(float $openingQuantity): array
{
    $sourceLocation = Location::factory()->create(['code' => 'SRC']);
    $destinationLocation = Location::factory()->create(['code' => 'DST']);
    $item = Item::factory()->create([
        'item_code' => 'WH-MOVE',
        'description' => 'Warehouse Movement Item',
        'inventory' => $openingQuantity,
        'unit_cost' => 10,
        'location_id' => $sourceLocation->id,
    ]);

    if ($openingQuantity > 0) {
        ItemLedgerEntry::query()->create([
            'entry_type' => ItemLedgerEntryType::POSITIVE_ADJUSTMENT,
            'document_type' => 'OPENING',
            'document_number' => 'OPEN-WH-MOVE',
            'document_line_number' => 10000,
            'item_id' => $item->id,
            'location_id' => $sourceLocation->id,
            'quantity' => $openingQuantity,
            'remaining_quantity' => $openingQuantity,
            'cost_amount_actual' => $openingQuantity * 10,
            'cost_amount_expected' => 0,
            'general_product_posting_group_id' => $item->general_product_posting_group_id,
            'inventory_posting_group_id' => $item->inventory_posting_group_id,
            'posting_date' => now(),
            'entry_date' => now(),
            'open' => true,
        ]);
    }

    return [$item, $sourceLocation, $destinationLocation];
}

function locationLedgerQuantity(Item $item, Location $location): float
{
    return (float) ItemLedgerEntry::query()
        ->where('item_id', $item->id)
        ->where('location_id', $location->id)
        ->sum('quantity');
}
