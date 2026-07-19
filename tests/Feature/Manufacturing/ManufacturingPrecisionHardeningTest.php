<?php

declare(strict_types=1);

use App\Enums\AccountType;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemLedgerEntryType;
use App\Enums\ProductionOrderStatus;
use App\Models\ChartOfAccount;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\ItemUomAssignment;
use App\Models\Location;
use App\Models\Manufacturing\ProductionBom;
use App\Models\Manufacturing\ProductionBomLine;
use App\Models\Manufacturing\ProductionBomVersionLine;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Permission;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\ValueEntry;
use App\Services\Manufacturing\ProductionOrderService;
use App\Support\DecimalFormatter;
use App\Support\DecimalMath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('widens manufacturing quantity and conversion columns for PostgreSQL precision', function (): void {
    if (DB::getDriverName() !== 'pgsql') {
        $this->markTestSkipped('PostgreSQL numeric precision is verified only on pgsql.');
    }

    $columns = collect(DB::select(<<<'SQL'
        select table_name, column_name, numeric_precision, numeric_scale
        from information_schema.columns
        where table_schema = current_schema()
          and (
            (table_name = 'production_bom_lines' and column_name = 'quantity_per')
            or (table_name = 'production_order_components' and column_name in ('expected_quantity_base', 'remaining_quantity'))
            or (table_name = 'item_ledger_entries' and column_name in ('quantity', 'remaining_quantity'))
            or (table_name = 'value_entries' and column_name in ('quantity', 'unit_cost'))
            or (table_name = 'item_uom_assignments' and column_name = 'conversion_factor')
          )
    SQL))->keyBy(fn (object $column): string => "{$column->table_name}.{$column->column_name}");

    expect((int) $columns['production_bom_lines.quantity_per']->numeric_precision)->toBe(24)
        ->and((int) $columns['production_bom_lines.quantity_per']->numeric_scale)->toBe(8)
        ->and((int) $columns['production_order_components.expected_quantity_base']->numeric_scale)->toBe(8)
        ->and((int) $columns['item_ledger_entries.quantity']->numeric_scale)->toBe(8)
        ->and((int) $columns['value_entries.quantity']->numeric_scale)->toBe(8)
        ->and((int) $columns['value_entries.unit_cost']->numeric_scale)->toBe(8)
        ->and((int) $columns['item_uom_assignments.conversion_factor']->numeric_scale)->toBe(12);
});

it('preserves tiny BOM quantities and never formats non-zero quantities as zero', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    $gram = UnitOfMeasure::query()->create([
        'uom_code' => 'G',
        'description' => 'Gram',
        'conversion_factor' => '1.000000000000',
        'is_base_uom' => true,
    ]);

    $item = Item::factory()->create([
        'item_code' => 'RM-TINY',
        'description' => 'Tiny raw material',
        'base_uom_id' => $gram->id,
        'unit_cost' => '123456789.12345678',
    ]);

    $bom = ProductionBom::query()->create([
        'code' => 'BOM-TINY',
        'description' => 'Tiny BOM',
        'item_id' => $item->id,
        'unit_of_measure_code' => 'G',
        'status' => 'CERTIFIED',
        'version' => '1.0',
    ]);

    $line = $bom->lines()->create([
        'line_number' => 10000,
        'type' => ProductionBomLine::TYPE_ITEM,
        'item_id' => $item->id,
        'description' => 'Tiny raw material',
        'unit_of_measure_code' => 'G',
        'quantity_per' => '0.00002011',
    ]);

    $line->refresh();

    expect($line->quantity_per)->toBe('0.00002011')
        ->and(DecimalFormatter::quantity($line->quantity_per, 'G'))->toBe('0.00002011 G')
        ->and(DecimalFormatter::quantity('0.50000000', 'G'))->toBe('0.5 G')
        ->and(DecimalFormatter::quantity('1.00000000', 'PCS'))->toBe('1 PCS');
});

it('explodes precise BOM quantities and preserves consumption ledger precision', function (): void {
    $user = User::factory()->create();
    grantManufacturingPrecisionPermissions($user);
    $this->actingAs($user);

    $location = Location::factory()->create(['code' => 'MAIN']);

    $bottle = UnitOfMeasure::query()->create([
        'uom_code' => 'BTL',
        'description' => 'Bottle',
        'conversion_factor' => '1.000000000000',
        'is_base_uom' => true,
    ]);
    $gram = UnitOfMeasure::query()->create([
        'uom_code' => 'G',
        'description' => 'Gram',
        'conversion_factor' => '1.000000000000',
        'is_base_uom' => true,
    ]);

    $finishedGood = Item::factory()->create([
        'item_code' => 'FG-MAI',
        'description' => 'Mai Sasanci Bottle',
        'base_uom_id' => $bottle->id,
        'unit_cost' => '0.00000000',
    ]);
    $rawMaterial = Item::factory()->create([
        'item_code' => 'FICUS-G',
        'description' => 'Ficus Carica',
        'base_uom_id' => $gram->id,
        'unit_cost' => '1.23456789',
        'inventory' => '100000.00000000',
    ]);

    $inventoryAccount = ChartOfAccount::query()->create([
        'account_number' => '1200-PRECISION',
        'name' => 'Precision Inventory',
        'account_category' => 'asset',
        'account_type' => AccountType::ASSET,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    $wipAccount = ChartOfAccount::query()->create([
        'account_number' => '1210-PRECISION',
        'name' => 'Precision WIP',
        'account_category' => 'asset',
        'account_type' => AccountType::ASSET,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);

    foreach (array_unique([$rawMaterial->inventory_posting_group_id, $finishedGood->inventory_posting_group_id]) as $inventoryPostingGroupId) {
        InventoryPostingSetup::query()->updateOrCreate([
            'inventory_posting_group_id' => $inventoryPostingGroupId,
            'location_id' => $location->id,
        ], [
            'inventory_account_id' => $inventoryAccount->id,
            'wip_account_id' => $wipAccount->id,
        ]);
    }

    $bom = ProductionBom::query()->create([
        'code' => 'BOM-MAI',
        'description' => 'Mai Sasanci formula',
        'item_id' => $finishedGood->id,
        'unit_of_measure_code' => 'BTL',
        'status' => 'CERTIFIED',
        'version' => '1.0',
    ]);

    $quantityPerBottle = DecimalMath::div('75000', '149184', 8);

    $bom->lines()->create([
        'line_number' => 10000,
        'type' => ProductionBomLine::TYPE_ITEM,
        'item_id' => $rawMaterial->id,
        'description' => 'Ficus Carica',
        'unit_of_measure_code' => 'G',
        'quantity_per' => $quantityPerBottle,
        'location_code' => $location->code,
    ]);

    ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::PURCHASE,
        'item_id' => $rawMaterial->id,
        'location_id' => $location->id,
        'quantity' => '100000.00000000',
        'remaining_quantity' => '100000.00000000',
        'open' => true,
        'posting_date' => now(),
        'document_number' => 'OPEN-FICUS',
        'document_line_number' => 10000,
        'general_product_posting_group_id' => $rawMaterial->general_product_posting_group_id,
        'inventory_posting_group_id' => $rawMaterial->inventory_posting_group_id,
        'entry_date' => now(),
    ]);

    $order = ProductionOrder::query()->create([
        'document_number' => 'PO-MAI-PRECISION',
        'status' => ProductionOrderStatus::FIRM_PLANNED,
        'item_id' => $finishedGood->id,
        'description' => 'Mai precision batch',
        'quantity' => '149184.00000000',
        'quantity_base' => '149184.00000000',
        'unit_of_measure_code' => 'BTL',
        'starting_date_time' => now(),
        'general_product_posting_group_id' => $finishedGood->general_product_posting_group_id,
        'inventory_posting_group_id' => $finishedGood->inventory_posting_group_id,
        'production_bom_id' => $bom->id,
        'costing_method' => 'FIFO',
        'unit_cost' => '0.00000000',
        'cost_rollup' => '0.00000000',
        'flushing_method' => 'MANUAL',
        'location_code' => $location->code,
        'created_by' => $user->id,
    ]);

    app(ProductionOrderService::class)->refresh($order, lines: true, routing: false, components: true);

    $component = $order->fresh()->components()->firstOrFail();
    $expectedDifference = DecimalMath::sub($component->expected_quantity_base, '75000.00000000', 8);

    expect($component->quantity_per)->toBe($quantityPerBottle)
        ->and(DecimalMath::isLessThanOrEqualToTolerance($expectedDifference, '0.0005'))->toBeTrue()
        ->and(DecimalFormatter::quantity($component->quantity_per, 'G'))->not->toBe('0 G');

    $tinyComponent = $order->components()->create([
        'line_number' => 20000,
        'item_id' => $rawMaterial->id,
        'description' => 'Tiny precision consumption',
        'unit_of_measure_code' => 'G',
        'quantity_per' => '0.00002011',
        'expected_quantity' => '0.00002011',
        'expected_quantity_base' => '0.00002011',
        'remaining_quantity' => '0.00002011',
        'flushing_method' => 'MANUAL',
        'location_code' => $location->code,
    ]);

    $order->forceFill(['status' => ProductionOrderStatus::RELEASED])->save();

    app(ProductionOrderService::class)->postConsumption($order->fresh(), [[
        'component_id' => $tinyComponent->id,
        'quantity' => '0.00002011',
    ]], $user->id);

    $consumptionEntry = ItemLedgerEntry::query()
        ->where('document_number', 'PO-MAI-PRECISION')
        ->where('document_line_number', 20000)
        ->where('entry_type', ItemLedgerEntryType::CONSUMPTION)
        ->firstOrFail();

    $valueEntry = ValueEntry::query()
        ->where('item_ledger_entry_no', $consumptionEntry->entry_number)
        ->firstOrFail();

    expect($consumptionEntry->quantity)->toBe('-0.00002011')
        ->and($valueEntry->quantity)->toBe('-0.00002011')
        ->and($valueEntry->unit_cost)->toBe('-1.23456789')
        ->and($tinyComponent->fresh()->actual_quantity_consumed)->toBe('0.00002011')
        ->and($tinyComponent->fresh()->remaining_quantity)->toBe('0.00000000');
});

it('preserves item-scoped unit conversion precision across multi-level units', function (): void {
    $piece = UnitOfMeasure::query()->create([
        'uom_code' => 'PCS',
        'description' => 'Piece',
        'conversion_factor' => '1.000000000000',
        'is_base_uom' => true,
    ]);
    $packet = UnitOfMeasure::query()->create([
        'uom_code' => 'PKT',
        'description' => 'Packet',
        'conversion_factor' => '12.000000000000',
        'is_base_uom' => false,
    ]);
    $carton = UnitOfMeasure::query()->create([
        'uom_code' => 'CT',
        'description' => 'Carton',
        'conversion_factor' => '288.000000000000',
        'is_base_uom' => false,
    ]);

    $item = Item::factory()->create([
        'item_code' => 'FG-CT-PRECISION',
        'description' => 'Carton precision item',
        'base_uom_id' => $piece->id,
    ]);

    ItemUomAssignment::query()->create([
        'item_id' => $item->id,
        'uom_id' => $piece->id,
        'uom_type' => 'BASE',
        'conversion_factor' => '1.000000000000',
        'is_default' => true,
    ]);
    ItemUomAssignment::query()->create([
        'item_id' => $item->id,
        'uom_id' => $packet->id,
        'uom_type' => 'SALES',
        'conversion_factor' => '12.000000000000',
        'is_default' => false,
    ]);
    ItemUomAssignment::query()->create([
        'item_id' => $item->id,
        'uom_id' => $carton->id,
        'uom_type' => 'SALES',
        'conversion_factor' => '288.000000000000',
        'is_default' => false,
    ]);

    expect($item->fresh()->getConversionFactorForUomDecimal('CT'))->toBe('288.000000000000')
        ->and(DecimalMath::mul('1', $item->fresh()->getConversionFactorForUomDecimal('CT'), 8))->toBe('288.00000000')
        ->and(DecimalMath::mul('24', $item->fresh()->getConversionFactorForUomDecimal('PKT'), 8))->toBe('288.00000000');
});

it('calculates BOM expected quantities and rollup costs without premature currency rounding', function (): void {
    $this->actingAs(User::factory()->create());

    $gram = UnitOfMeasure::query()->create([
        'uom_code' => 'G',
        'description' => 'Gram',
        'conversion_factor' => '1.000000000000',
        'is_base_uom' => true,
    ]);

    $componentItem = Item::factory()->create([
        'item_code' => 'RM-COST-PRECISION',
        'description' => 'Cost precision raw material',
        'base_uom_id' => $gram->id,
        'unit_cost' => '1.23456789',
    ]);
    $finishedItem = Item::factory()->create([
        'item_code' => 'FG-COST-PRECISION',
        'description' => 'Cost precision finished item',
        'base_uom_id' => $gram->id,
    ]);

    $bom = ProductionBom::query()->create([
        'code' => 'BOM-COST-PRECISION',
        'description' => 'Cost precision BOM',
        'item_id' => $finishedItem->id,
        'unit_of_measure_code' => 'G',
        'status' => 'CERTIFIED',
        'version' => '1.0',
    ]);

    $bom->lines()->create([
        'line_number' => 10000,
        'type' => ProductionBomLine::TYPE_ITEM,
        'item_id' => $componentItem->id,
        'description' => 'Cost precision component',
        'unit_of_measure_code' => 'G',
        'quantity_per' => '0.00002011',
    ]);

    $versionLine = new ProductionBomVersionLine([
        'quantity_per' => '0.50273488',
        'scrap_percent' => '0.00',
    ]);

    expect($bom->fresh('lines.item')->calculateCostDecimal())->toBe('0.00002483')
        ->and($versionLine->getExpectedQuantityDecimal('149184'))->toBe('75000.00033792');
});

function grantManufacturingPrecisionPermissions(User $user): void
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
