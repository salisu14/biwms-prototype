<?php

declare(strict_types=1);

use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\ItemUomAssignment;
use App\Models\Location;
use App\Models\OpeningInventory;
use App\Models\OpeningInventoryLine;
use App\Models\UnitOfMeasure;
use App\Models\ValueEntry;
use App\Services\Inventory\OpeningInventoryService;
use App\Services\Inventory\ValueEntryService;
use App\Support\DecimalMath;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\OpeningInventorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('fresh seeded opening inventory reconciles item stock cache to item ledger and value entries', function (): void {
    $this->seed(DatabaseSeeder::class);

    expect(OpeningInventory::query()->where('document_number', OpeningInventorySeeder::DOCUMENT_NUMBER)->where('status', OpeningInventory::STATUS_POSTED)->exists())->toBeTrue()
        ->and(ItemLedgerEntry::query()->where('source_type', OpeningInventory::class)->count())->toBe(13)
        ->and(ValueEntry::query()->whereIn('item_ledger_entry_no', ItemLedgerEntry::query()->where('source_type', OpeningInventory::class)->select('entry_number'))->count())->toBe(13);

    Item::query()->each(function (Item $item): void {
        $ledgerQuantity = DecimalMath::quantity(
            ItemLedgerEntry::query()
                ->where('item_id', $item->id)
                ->sum('quantity')
        );

        expect($item->inventory)->toBe($ledgerQuantity);
    });

    expect(Artisan::call('biwms:inventory-reconcile', ['--json' => true]))->toBe(0);

    $report = json_decode(Artisan::output(), true);

    expect($report['stock_mismatches'])->toBe([])
        ->and($report['missing_value_entries'])->toBe([])
        ->and($report['value_entry_mismatches'])->toBe([])
        ->and($report['duplicate_opening_inventory_entries'])->toBe([])
        ->and($report['posted_opening_documents_without_ledger'])->toBe([]);
});

it('opening inventory seeder is idempotent', function (): void {
    $this->seed(DatabaseSeeder::class);

    $documentCount = OpeningInventory::query()->count();
    $lineCount = OpeningInventoryLine::query()->count();
    $ledgerCount = ItemLedgerEntry::query()->count();
    $valueEntryCount = ValueEntry::query()->count();
    $totalQuantity = DecimalMath::quantity(ItemLedgerEntry::query()->sum('quantity'));

    $this->seed(OpeningInventorySeeder::class);

    expect(OpeningInventory::query()->count())->toBe($documentCount)
        ->and(OpeningInventoryLine::query()->count())->toBe($lineCount)
        ->and(ItemLedgerEntry::query()->count())->toBe($ledgerCount)
        ->and(ValueEntry::query()->count())->toBe($valueEntryCount)
        ->and(DecimalMath::quantity(ItemLedgerEntry::query()->sum('quantity')))->toBe($totalQuantity);
});

it('posting the same opening inventory document twice does not duplicate ledger entries', function (): void {
    [$item, $location] = openingInventoryTestItem();

    $document = app(OpeningInventoryService::class)->createDraft(
        documentNumber: 'TEST-OPEN-DUP',
        source: 'TEST',
        postingDate: now()->toDateString(),
        lines: [[
            'item_id' => $item->id,
            'location_id' => $location->id,
            'unit_of_measure_id' => $item->base_uom_id,
            'unit_of_measure_code' => $item->baseUom?->uom_code,
            'quantity' => '10.00000000',
            'unit_cost' => '2.50000000',
        ]],
    );

    app(OpeningInventoryService::class)->post($document);
    app(OpeningInventoryService::class)->post($document->fresh());

    expect(ItemLedgerEntry::query()->where('source_type', OpeningInventory::class)->where('source_id', $document->id)->count())->toBe(1)
        ->and(ValueEntry::query()->count())->toBe(1)
        ->and($item->fresh()->inventory)->toBe('10.00000000');
});

it('posts opening inventory with item scoped alternate unit conversion and matching value cost', function (): void {
    [$item, $location] = openingInventoryTestItem([
        'item_code' => 'OPENING-CARTON-ITEM',
        'unit_cost' => '850.00000000',
    ]);

    $carton = UnitOfMeasure::query()->create([
        'uom_code' => 'CT',
        'description' => 'Carton',
        'conversion_factor' => '288.000000000000',
        'is_base_uom' => false,
    ]);

    ItemUomAssignment::query()->create([
        'item_id' => $item->id,
        'uom_id' => $carton->id,
        'uom_type' => 'PURCHASE',
        'conversion_factor' => '288.000000000000',
        'is_default' => false,
    ]);

    $document = app(OpeningInventoryService::class)->createDraft(
        documentNumber: 'TEST-OPEN-CT',
        source: 'TEST',
        postingDate: now()->toDateString(),
        lines: [[
            'item_id' => $item->id,
            'location_id' => $location->id,
            'unit_of_measure_id' => $carton->id,
            'unit_of_measure_code' => 'CT',
            'quantity' => '1.00000000',
            'unit_cost' => '850.00000000',
        ]],
    );

    app(OpeningInventoryService::class)->post($document);

    $itemLedgerEntry = ItemLedgerEntry::query()
        ->where('source_type', OpeningInventory::class)
        ->where('source_id', $document->id)
        ->firstOrFail();
    $valueEntry = ValueEntry::query()
        ->where('item_ledger_entry_no', $itemLedgerEntry->entry_number)
        ->firstOrFail();

    expect($document->lines()->first()->quantity)->toBe('1.00000000')
        ->and($document->lines()->first()->quantity_base)->toBe('288.00000000')
        ->and($itemLedgerEntry->quantity)->toBe('288.00000000')
        ->and($itemLedgerEntry->cost_amount_actual)->toBe('244800.0000')
        ->and($valueEntry->quantity)->toBe('288.00000000')
        ->and($valueEntry->cost_amount_actual)->toBe('244800.0000')
        ->and($valueEntry->unit_cost)->toBe('850.00000000')
        ->and($item->fresh()->inventory)->toBe('288.00000000');
});

it('posts opening inventory using item specific bag to grams conversion', function (): void {
    [$item, $location] = openingInventoryTestItem([
        'item_code' => 'OPENING-BAG-GRAM',
        'unit_cost' => '0.05000000',
    ]);

    $gram = $item->baseUom;
    $gram->forceFill(['uom_code' => 'G'])->save();

    $bag = UnitOfMeasure::query()->create([
        'uom_code' => 'BG',
        'description' => 'Bag',
        'conversion_factor' => '25000.000000000000',
        'is_base_uom' => false,
    ]);

    ItemUomAssignment::query()->create([
        'item_id' => $item->id,
        'uom_id' => $bag->id,
        'uom_type' => 'PURCHASE',
        'conversion_factor' => '25000.000000000000',
        'is_default' => false,
    ]);

    $document = app(OpeningInventoryService::class)->createDraft(
        documentNumber: 'TEST-OPEN-BG',
        source: 'TEST',
        postingDate: now()->toDateString(),
        lines: [[
            'item_id' => $item->id,
            'location_id' => $location->id,
            'unit_of_measure_id' => $bag->id,
            'unit_of_measure_code' => 'BG',
            'quantity' => '3.00000000',
            'unit_cost' => '0.05000000',
        ]],
    );

    app(OpeningInventoryService::class)->post($document);

    $itemLedgerEntry = ItemLedgerEntry::query()
        ->where('source_type', OpeningInventory::class)
        ->where('source_id', $document->id)
        ->firstOrFail();
    $valueEntry = ValueEntry::query()
        ->where('item_ledger_entry_no', $itemLedgerEntry->entry_number)
        ->firstOrFail();

    expect($document->lines()->first()->quantity_base)->toBe('75000.00000000')
        ->and($itemLedgerEntry->quantity)->toBe('75000.00000000')
        ->and($valueEntry->quantity)->toBe('75000.00000000')
        ->and($valueEntry->cost_amount_actual)->toBe('3750.0000')
        ->and($item->fresh()->inventory)->toBe('75000.00000000');
});

it('rolls back opening inventory posting when value entry creation fails', function (): void {
    [$item, $location] = openingInventoryTestItem();

    app()->instance(ValueEntryService::class, new class extends ValueEntryService
    {
        public function ensureForItemLedgerEntry(ItemLedgerEntry $entry): ?ValueEntry
        {
            return null;
        }
    });

    $document = app(OpeningInventoryService::class)->createDraft(
        documentNumber: 'TEST-OPEN-ROLLBACK',
        source: 'TEST',
        postingDate: now()->toDateString(),
        lines: [[
            'item_id' => $item->id,
            'location_id' => $location->id,
            'unit_of_measure_id' => $item->base_uom_id,
            'unit_of_measure_code' => $item->baseUom?->uom_code,
            'quantity' => '10.00000000',
            'unit_cost' => '2.50000000',
        ]],
    );

    expect(fn () => app(OpeningInventoryService::class)->post($document))
        ->toThrow(RuntimeException::class, 'failed to create a value entry');

    expect(ItemLedgerEntry::query()->where('source_type', OpeningInventory::class)->where('source_id', $document->id)->count())->toBe(0)
        ->and(ValueEntry::query()->count())->toBe(0)
        ->and($item->fresh()->inventory)->toBe('0.00000000')
        ->and($document->fresh()->status)->toBe(OpeningInventory::STATUS_DRAFT);
});

it('inventory opening repair is dry-run by default and applies only with apply flag', function (): void {
    [$item] = openingInventoryTestItem([
        'inventory' => '17.00000000',
        'unit_cost' => '3.00000000',
    ]);

    expect(Artisan::call('biwms:inventory-opening-repair', ['--details' => true]))->toBe(0)
        ->and(ItemLedgerEntry::query()->count())->toBe(0)
        ->and($item->fresh()->inventory)->toBe('17.00000000');

    expect(Artisan::call('biwms:inventory-opening-repair', ['--apply' => true]))->toBe(0)
        ->and(ItemLedgerEntry::query()->count())->toBe(1)
        ->and(ValueEntry::query()->count())->toBe(1)
        ->and($item->fresh()->inventory)->toBe('17.00000000');

    expect(Artisan::call('biwms:inventory-reconcile', ['--json' => true]))->toBe(0);

    $report = json_decode(Artisan::output(), true);

    expect($report['stock_mismatches'])->toBe([]);
});

it('legacy opening balance backfill delegates to controlled repair with value entries', function (): void {
    [$item] = openingInventoryTestItem([
        'inventory' => '11.00000000',
        'unit_cost' => '4.00000000',
    ]);

    expect(Artisan::call('inventory:backfill-opening-balances', ['--apply' => true, '--item' => [$item->item_code]]))->toBe(0)
        ->and(OpeningInventory::query()->where('source', 'REPAIR_OPENING_STOCK')->count())->toBe(1)
        ->and(ItemLedgerEntry::query()->where('source_type', OpeningInventory::class)->count())->toBe(1)
        ->and(ValueEntry::query()->count())->toBe(1);

    $itemLedgerEntry = ItemLedgerEntry::query()->firstOrFail();

    expect($itemLedgerEntry->quantity)->toBe('11.00000000')
        ->and(ValueEntry::query()->where('item_ledger_entry_no', $itemLedgerEntry->entry_number)->firstOrFail()->cost_amount_actual)->toBe('44.0000')
        ->and($item->fresh()->inventory)->toBe('11.00000000');
});

it('opening inventory seeder refuses production environment', function (): void {
    app()->detectEnvironment(fn (): string => 'production');

    expect(fn () => (new OpeningInventorySeeder)->run())
        ->toThrow(RuntimeException::class, 'Opening inventory seeding is disabled in production.');
});

/**
 * @param  array<string, mixed>  $itemAttributes
 * @return array{0: Item, 1: Location}
 */
function openingInventoryTestItem(array $itemAttributes = []): array
{
    $location = Location::factory()->create(['code' => 'OPENING-TEST']);
    $baseUom = UnitOfMeasure::query()->create([
        'uom_code' => 'PCS',
        'description' => 'Pieces',
        'conversion_factor' => '1.000000000000',
        'is_base_uom' => true,
    ]);

    $item = Item::factory()->create([
        'item_code' => 'OPENING-ITEM',
        'description' => 'Opening test item',
        'location_id' => $location->id,
        'base_uom_id' => $baseUom->id,
        'inventory' => '0.00000000',
        'unit_cost' => '2.00000000',
        ...$itemAttributes,
    ]);

    return [$item->fresh('baseUom'), $location];
}
