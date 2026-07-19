<?php

declare(strict_types=1);

use App\Models\ChartOfAccount;
use App\Models\GlEntry;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\ItemUomAssignment;
use App\Models\Location;
use App\Models\Manufacturing\ProductionBom;
use App\Models\Manufacturing\ProductionBomLine;
use App\Models\Manufacturing\ProductionBomVersion;
use App\Models\Manufacturing\ProductionBomVersionLine;
use App\Models\Manufacturing\Routing;
use App\Models\OpeningInventory;
use App\Models\OpeningInventoryLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
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

it('operator manifest uses approved physical quantity instead of incorrect legacy cache', function (): void {
    [$item] = openingInventoryManifestItem('2100', 'Sodium Saccharine', 'G', '25000.00000000', '8.80000000');

    expect(Artisan::call('biwms:inventory-opening-repair', [
        '--manifest' => 'PROD-PHYSICAL-OPENING-2026-V1',
        '--item' => '2100',
        '--apply' => true,
    ]))->toBe(0);

    $itemLedgerEntry = ItemLedgerEntry::query()
        ->where('item_id', $item->id)
        ->firstOrFail();

    expect($itemLedgerEntry->quantity)->toBe('3400.00000000')
        ->and($itemLedgerEntry->quantity)->not->toBe('25000.00000000')
        ->and(ValueEntry::query()->where('item_ledger_entry_no', $itemLedgerEntry->entry_number)->firstOrFail()->quantity)->toBe('3400.00000000')
        ->and($item->fresh()->inventory)->toBe('3400.00000000');
});

it('operator manifest converts ficus source bags to approved gram base quantity', function (): void {
    [$item, $gram] = openingInventoryManifestItem('2410', 'Ficus Carica', 'G', '150000.00000000', '20000.00000000');
    $bag = UnitOfMeasure::query()->firstOrCreate([
        'uom_code' => 'BG',
    ], [
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

    expect(Artisan::call('biwms:inventory-opening-repair', [
        '--manifest' => 'PROD-PHYSICAL-OPENING-2026-V1',
        '--item' => '2410',
        '--apply' => true,
    ]))->toBe(0);

    $line = OpeningInventoryLine::query()->firstOrFail();
    $itemLedgerEntry = ItemLedgerEntry::query()
        ->where('item_id', $item->id)
        ->firstOrFail();

    expect($line->quantity)->toBe('3.00000000')
        ->and($line->quantity_base)->toBe('75000.00000000')
        ->and($line->unit_of_measure_id)->toBe($bag->id)
        ->and($itemLedgerEntry->quantity)->toBe('75000.00000000')
        ->and($itemLedgerEntry->cost_amount_actual)->toBe('60000.0000')
        ->and(ValueEntry::query()->where('item_ledger_entry_no', $itemLedgerEntry->entry_number)->firstOrFail()->unit_cost)->toBe('0.80000000')
        ->and($item->fresh()->inventory)->toBe('75000.00000000')
        ->and($gram->uom_code)->toBe('G');
});

it('operator manifest requires packaging items to use PCS and excludes unresolved carton quantity', function (): void {
    openingInventoryManifestItem('2600', 'Label', 'PCS', '10000.00000000', '20.00000000');

    expect(Artisan::call('biwms:inventory-opening-repair', [
        '--manifest' => 'PROD-PHYSICAL-OPENING-2026-V1',
        '--details' => true,
    ]))->toBe(0);

    $reportOutput = Artisan::output();

    expect($reportOutput)->toContain('item=2600')
        ->and($reportOutput)->toContain('approved=149184.00000000 PCS')
        ->and($reportOutput)->toContain('Excluded unresolved lines: Carton 158 CT.')
        ->and($reportOutput)->not->toContain('approved=158.00000000 CT');
});

it('operator manifest blocks apply when bottle and cap item mappings are missing', function (): void {
    openingInventoryManifestItem('2600', 'Label', 'PCS', '10000.00000000', '20.00000000');

    expect(Artisan::call('biwms:inventory-opening-repair', [
        '--manifest' => 'PROD-PHYSICAL-OPENING-2026-V1',
        '--details' => true,
        '--apply' => true,
    ]))->toBe(1)
        ->and(ItemLedgerEntry::query()->count())->toBe(0)
        ->and(OpeningInventory::query()->count())->toBe(0);

    $reportOutput = Artisan::output();

    expect($reportOutput)->toContain('Refusing to apply manifest repair')
        ->and($reportOutput)->toContain('missing_item_mapping');
});

it('prepares production item masters and makes the approved manifest fully repairable', function (): void {
    $fixture = openingInventoryProductionLikeFixture();
    $paperTrayId = $fixture['items']['2800']->id;
    $boxId = $fixture['items']['2900']->id;
    $rubberCapId = $fixture['items']['2500']->id;
    $bomId = $fixture['bom']->id;
    $routingId = $fixture['routing']->id;

    expect(Artisan::call('biwms:production-opening-item-master-prepare', [
        '--apply' => true,
        '--details' => true,
        '--bottle-cost' => '12.50000000',
        '--cap-cost' => '4.25000000',
    ]))->toBe(0);

    $bottle = Item::query()->where('item_code', 'BOTTLE-60ML')->firstOrFail();
    $cap = Item::query()->where('item_code', 'CAP-60ML')->firstOrFail();
    $paperTray = Item::query()->where('item_code', '2800')->firstOrFail();
    $box = Item::query()->where('item_code', '2900')->firstOrFail();
    $ficus = Item::query()->where('item_code', '2410')->firstOrFail();

    expect($bottle->baseUom?->uom_code)->toBe('PCS')
        ->and($cap->baseUom?->uom_code)->toBe('PCS')
        ->and($bottle->unit_cost)->toBe('12.50000000')
        ->and($cap->unit_cost)->toBe('4.25000000')
        ->and(Item::query()->whereKey($rubberCapId)->where('item_code', '2500')->exists())->toBeTrue()
        ->and($paperTray->id)->toBe($paperTrayId)
        ->and($paperTray->baseUom?->uom_code)->toBe('PCS')
        ->and($box->id)->toBe($boxId)
        ->and($box->baseUom?->uom_code)->toBe('PCS')
        ->and($ficus->baseUom?->uom_code)->toBe('G')
        ->and($ficus->getConversionFactorForUomDecimal('BG'))->toBe('25000.000000000000')
        ->and(ProductionBom::query()->whereKey($bomId)->exists())->toBeTrue()
        ->and(Routing::query()->whereKey($routingId)->exists())->toBeTrue()
        ->and(ProductionBomLine::query()->where('production_bom_id', $bomId)->where('item_id', $paperTrayId)->exists())->toBeTrue()
        ->and(ProductionBomVersionLine::query()->where('item_id', $boxId)->exists())->toBeTrue();

    expect(Artisan::call('biwms:inventory-opening-repair', [
        '--manifest' => 'PROD-PHYSICAL-OPENING-2026-V1',
        '--details' => true,
        '--export' => 'storage/app/reports/prod-physical-opening-preview-test.json',
    ]))->toBe(0);

    $preview = json_decode(file_get_contents(base_path('storage/app/reports/prod-physical-opening-preview-test.json')), true);
    $findings = collect($preview['findings']);

    expect($findings)->toHaveCount(11)
        ->and($findings->where('repairable', false)->count())->toBe(0)
        ->and($preview['excluded_lines'][0]['source_quantity'])->toBe('158')
        ->and($preview['total_proposed_opening_inventory_value'])->toBe('22524786.4800');

    expect(Artisan::call('biwms:inventory-opening-repair', [
        '--manifest' => 'PROD-PHYSICAL-OPENING-2026-V1',
        '--apply' => true,
    ]))->toBe(0);

    expect(OpeningInventory::query()->where('document_number', 'POPEN-2026-V1')->where('status', OpeningInventory::STATUS_POSTED)->count())->toBe(1)
        ->and(ItemLedgerEntry::query()->where('source_type', OpeningInventory::class)->count())->toBe(11)
        ->and(ValueEntry::query()->count())->toBe(11)
        ->and(GlEntry::query()->where('document_number', 'POPEN-2026-V1')->count())->toBe(22)
        ->and(Item::query()->where('item_code', 'BOTTLE-60ML')->value('inventory'))->toBe('149184.00000000')
        ->and(Item::query()->where('item_code', 'CAP-60ML')->value('inventory'))->toBe('149184.00000000')
        ->and(Item::query()->where('item_code', '2100')->value('inventory'))->toBe('3400.00000000')
        ->and(Item::query()->where('item_code', '2410')->value('inventory'))->toBe('75000.00000000')
        ->and(ItemLedgerEntry::query()->where('item_id', $fixture['items']['2100']->id)->value('quantity'))->not->toBe('25000.00000000');

    expect(Artisan::call('biwms:inventory-opening-repair', [
        '--manifest' => 'PROD-PHYSICAL-OPENING-2026-V1',
        '--apply' => true,
    ]))->toBe(0)
        ->and(OpeningInventory::query()->where('document_number', 'POPEN-2026-V1')->count())->toBe(1)
        ->and(ItemLedgerEntry::query()->where('source_type', OpeningInventory::class)->count())->toBe(11)
        ->and(ValueEntry::query()->count())->toBe(11);
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

/**
 * @return array{0: Item, 1: UnitOfMeasure}
 */
function openingInventoryManifestItem(
    string $itemCode,
    string $description,
    string $baseUomCode,
    string $legacyCacheQuantity,
    string $unitCost,
): array {
    $rawLocation = Location::query()->firstOrCreate([
        'code' => 'GBS-RAWMAT',
    ], [
        'name' => 'Gabasawa Raw Materials Store',
        'blocked' => false,
    ]);
    $packagingLocation = Location::query()->firstOrCreate([
        'code' => 'GBS-FGN',
    ], [
        'name' => 'Gabasawa Packaging Store',
        'blocked' => false,
    ]);
    $baseUom = UnitOfMeasure::query()->firstOrCreate([
        'uom_code' => $baseUomCode,
    ], [
        'description' => $baseUomCode,
        'conversion_factor' => '1.000000000000',
        'is_base_uom' => true,
    ]);
    $inventoryPostingGroup = InventoryPostingGroup::query()->firstOrCreate([
        'code' => str_starts_with($itemCode, '2') && in_array($itemCode, ['2100', '2200', '2300', '2400', '2410'], true) ? 'RAW' : 'PACKAGING',
    ], [
        'description' => 'Manifest Inventory Posting Group',
        'blocked' => false,
    ]);
    $inventoryAccount = ChartOfAccount::factory()->create([
        'account_number' => '12000-'.$itemCode,
        'name' => 'Inventory '.$itemCode,
    ]);
    ChartOfAccount::factory()->create([
        'account_number' => '30100',
        'name' => 'Opening Balance Equity',
        'direct_posting' => true,
    ]);

    $location = in_array($itemCode, ['2100', '2200', '2300', '2400', '2410'], true)
        ? $rawLocation
        : $packagingLocation;

    InventoryPostingSetup::query()->firstOrCreate([
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
        'location_id' => $location->id,
    ], [
        'inventory_account_id' => $inventoryAccount->id,
    ]);

    $item = Item::factory()->create([
        'item_code' => $itemCode,
        'description' => $description,
        'base_uom_id' => $baseUom->id,
        'location_id' => $location->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
        'inventory' => $legacyCacheQuantity,
        'unit_cost' => $unitCost,
    ]);

    ItemUomAssignment::query()->create([
        'item_id' => $item->id,
        'uom_id' => $baseUom->id,
        'uom_type' => 'BASE',
        'conversion_factor' => '1.000000000000',
        'is_default' => true,
    ]);

    return [$item->fresh('baseUom'), $baseUom];
}

/**
 * @return array{items: array<string, Item>, bom: ProductionBom, routing: Routing}
 */
function openingInventoryProductionLikeFixture(): array
{
    $user = User::factory()->create();
    auth()->login($user);
    $rawLocation = Location::query()->firstOrCreate([
        'code' => 'GBS-RAWMAT',
    ], [
        'name' => 'Gabasawa Raw Materials Store',
        'blocked' => false,
    ]);
    $packagingLocation = Location::query()->firstOrCreate([
        'code' => 'GBS-FGN',
    ], [
        'name' => 'Gabasawa Packaging Store',
        'blocked' => false,
    ]);
    $pcs = UnitOfMeasure::query()->firstOrCreate([
        'uom_code' => 'PCS',
    ], [
        'description' => 'Pieces',
        'conversion_factor' => '1.000000000000',
        'is_base_uom' => true,
    ]);
    $gram = UnitOfMeasure::query()->firstOrCreate([
        'uom_code' => 'G',
    ], [
        'description' => 'Gram',
        'conversion_factor' => '1.000000000000',
        'is_base_uom' => true,
    ]);
    $carton = UnitOfMeasure::query()->firstOrCreate([
        'uom_code' => 'CT',
    ], [
        'description' => 'Carton',
        'conversion_factor' => '288.000000000000',
        'is_base_uom' => false,
    ]);
    UnitOfMeasure::query()->firstOrCreate([
        'uom_code' => 'BG',
    ], [
        'description' => 'Bag',
        'conversion_factor' => '25000.000000000000',
        'is_base_uom' => false,
    ]);

    $rawPostingGroup = InventoryPostingGroup::query()->firstOrCreate([
        'code' => 'RAW',
    ], [
        'description' => 'Raw Materials',
        'blocked' => false,
    ]);
    $packagingPostingGroup = InventoryPostingGroup::query()->firstOrCreate([
        'code' => 'PACKAGING',
    ], [
        'description' => 'Packaging',
        'blocked' => false,
    ]);
    $rawInventoryAccount = ChartOfAccount::factory()->create([
        'account_number' => '13110',
        'name' => 'Inventory',
    ]);
    ChartOfAccount::factory()->create([
        'account_number' => '30100',
        'name' => 'Opening Balance Equity',
        'direct_posting' => true,
    ]);

    foreach ([[$rawPostingGroup, $rawLocation], [$packagingPostingGroup, $packagingLocation]] as [$group, $location]) {
        InventoryPostingSetup::query()->firstOrCreate([
            'inventory_posting_group_id' => $group->id,
            'location_id' => $location->id,
        ], [
            'inventory_account_id' => $rawInventoryAccount->id,
        ]);
    }

    $items = [
        '2500' => openingInventoryProductionItem('2500', 'Rubber & Cap', $gram, $packagingLocation, $packagingPostingGroup, '10000.00000000', '47.43000000'),
        '2600' => openingInventoryProductionItem('2600', 'Label', $pcs, $packagingLocation, $packagingPostingGroup, '10000.00000000', '20.00000000'),
        '2700' => openingInventoryProductionItem('2700', 'Shrink Sleeve', $pcs, $packagingLocation, $packagingPostingGroup, '1000.00000000', '150.00000000'),
        '2800' => openingInventoryProductionItem('2800', 'Paper Tray 12x60ml', $carton, $packagingLocation, $packagingPostingGroup, '50000.00000000', '70.14000000'),
        '2900' => openingInventoryProductionItem('2900', 'Mai Sasanci 3ply Box', $carton, $packagingLocation, $packagingPostingGroup, '10000.00000000', '723.00000000'),
        '2100' => openingInventoryProductionItem('2100', 'Sodium Saccharine', $gram, $rawLocation, $rawPostingGroup, '25000.00000000', '8.80000000'),
        '2200' => openingInventoryProductionItem('2200', 'Ginseng', $gram, $rawLocation, $rawPostingGroup, '400000.00000000', '778.50000000'),
        '2300' => openingInventoryProductionItem('2300', 'Yohimbine', $gram, $rawLocation, $rawPostingGroup, '1000000.00000000', '336.00000000'),
        '2400' => openingInventoryProductionItem('2400', 'Sodium Benzoate', $gram, $rawLocation, $rawPostingGroup, '150000.00000000', '2.80000000'),
        '2410' => openingInventoryProductionItem('2410', 'Ficus Carica', $gram, $rawLocation, $rawPostingGroup, '150000.00000000', '20000.00000000'),
    ];

    $finishedGood = openingInventoryProductionItem('1000', 'Mai Sasanci', $pcs, $packagingLocation, $packagingPostingGroup, '0.00000000', '850.00000000');
    $bom = ProductionBom::query()->create([
        'code' => 'BOM-OPENING-PREP',
        'description' => 'Opening prep BOM',
        'item_id' => $finishedGood->id,
        'unit_of_measure_code' => 'PCS',
        'created_by' => $user->id,
    ]);
    ProductionBomLine::query()->create([
        'production_bom_id' => $bom->id,
        'line_number' => 10000,
        'type' => 'ITEM',
        'item_id' => $items['2800']->id,
        'description' => 'Paper Tray',
        'unit_of_measure_code' => 'CT',
        'quantity_per' => '1.0000',
    ]);
    $version = ProductionBomVersion::query()->create([
        'production_bom_id' => $bom->id,
        'version_code' => 'V1',
        'description' => 'Opening prep version',
        'created_by' => $user->id,
    ]);
    ProductionBomVersionLine::query()->create([
        'production_bom_version_id' => $version->id,
        'line_number' => 10000,
        'type' => 'ITEM',
        'item_id' => $items['2900']->id,
        'description' => '3-ply Box',
        'unit_of_measure_code' => 'CT',
        'quantity_per' => '1.0000',
    ]);
    $routing = Routing::query()->create([
        'code' => 'ROUTE-OPENING-PREP',
        'description' => 'Opening prep routing',
        'item_id' => $finishedGood->id,
        'created_by' => $user->id,
    ]);

    return [
        'items' => $items,
        'bom' => $bom,
        'routing' => $routing,
    ];
}

function openingInventoryProductionItem(
    string $itemCode,
    string $description,
    UnitOfMeasure $baseUom,
    Location $location,
    InventoryPostingGroup $postingGroup,
    string $legacyCacheQuantity,
    string $unitCost,
): Item {
    $item = Item::factory()->create([
        'item_code' => $itemCode,
        'description' => $description,
        'base_uom_id' => $baseUom->id,
        'location_id' => $location->id,
        'inventory_posting_group_id' => $postingGroup->id,
        'inventory' => $legacyCacheQuantity,
        'unit_cost' => $unitCost,
        'standard_cost' => $unitCost,
        'last_direct_cost' => $unitCost,
    ]);

    ItemUomAssignment::query()->create([
        'item_id' => $item->id,
        'uom_id' => $baseUom->id,
        'uom_type' => 'BASE',
        'conversion_factor' => '1.000000000000',
        'is_default' => true,
    ]);

    return $item->fresh('baseUom');
}
