<?php

declare(strict_types=1);

use App\Enums\AccountCategory;
use App\Enums\AccountType;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemLedgerEntryType;
use App\Enums\ItemType;
use App\Enums\ProductionOrderSourceType;
use App\Enums\ProductionOrderStatus;
use App\Filament\Resources\ProductionOrders\Pages\CreateProductionOrder;
use App\Models\AccountingPeriod;
use App\Models\CapacityLedgerEntry;
use App\Models\ChartOfAccount;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralLedgerSetup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\GlEntry;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemApplicationEntry;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\NumberSeriesLine;
use App\Models\Permission;
use App\Models\User;
use App\Models\ValueEntry;
use App\Services\Manufacturing\ProductionOrderNumberSeriesSetupService;
use App\Services\Manufacturing\ProductionOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    GeneralLedgerSetup::query()->updateOrCreate(
        ['company_name' => 'Default Company'],
        [
            'allow_posting_from' => '2026-01-01',
            'allow_posting_to' => '2026-12-31',
        ],
    );

    AccountingPeriod::query()->firstOrCreate([
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ], [
        'name' => 'FY2026',
        'is_closed' => false,
    ]);
});

it('creates a draft production order from Filament without inventory or accounting side effects', function (): void {
    app(ProductionOrderNumberSeriesSetupService::class)->ensure();
    $user = productionOrderPostingBoundaryUser();
    $fixture = productionOrderPostingBoundaryFixture();

    $before = productionOrderPostingBoundarySnapshot($fixture['finishedGood']);

    Livewire::actingAs($user)
        ->test(CreateProductionOrder::class)
        ->fillForm([
            'status' => ProductionOrderStatus::SIMULATED->value,
            'source_type' => ProductionOrderSourceType::ITEM->value,
            'source_no' => $fixture['finishedGood']->item_code,
            'item_id' => $fixture['finishedGood']->id,
            'description' => $fixture['finishedGood']->description,
            'quantity' => 3,
            'unit_of_measure_code' => 'PCS',
            'quantity_base' => 3,
            'conversion_factor' => 1,
            'flushing_method' => 'MANUAL',
            'scrap_percent' => 0,
            'due_date' => now()->addDays(7)->toDateString(),
            'location_code' => $fixture['location']->code,
            'costing_method' => 'FIFO',
            'unit_cost' => 25,
            'inventory_posting_group_id' => $fixture['inventoryPostingGroup']->id,
            'general_product_posting_group_id' => $fixture['generalProductPostingGroup']->id,
            'general_business_posting_group_id' => $fixture['generalBusinessPostingGroup']->id,
            'priority' => 100,
            'reserved_from_stock' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $after = productionOrderPostingBoundarySnapshot($fixture['finishedGood']);

    expect(ProductionOrder::query()->count())->toBe(1)
        ->and(ProductionOrder::query()->first()?->document_number)->toBe('PROD-00001')
        ->and(productionOrderPostingBoundaryNumberSeriesLine()?->last_no_used)->toBe(1);

    productionOrderPostingBoundaryExpectNoFinishedOrLedgerDelta($before, $after);
});

it('edits a draft production order without inventory or accounting side effects', function (): void {
    $fixture = productionOrderPostingBoundaryFixture();
    $order = productionOrderPostingBoundaryOrder($fixture, ProductionOrderStatus::SIMULATED);

    $before = productionOrderPostingBoundarySnapshot($fixture['finishedGood']);

    $order->update([
        'description' => 'Draft description changed',
        'quantity' => 4,
        'quantity_base' => 4,
    ]);

    $after = productionOrderPostingBoundarySnapshot($fixture['finishedGood']);

    expect($order->fresh()->description)->toBe('Draft description changed');
    productionOrderPostingBoundaryExpectNoFinishedOrLedgerDelta($before, $after);
});

it('releases a manual production order without finished-goods inventory or output posting', function (): void {
    $user = productionOrderPostingBoundaryUser();
    $fixture = productionOrderPostingBoundaryFixture();
    $order = productionOrderPostingBoundaryOrder($fixture, ProductionOrderStatus::FIRM_PLANNED);
    productionOrderPostingBoundaryComponent($order, $fixture, 3);

    $before = productionOrderPostingBoundarySnapshot($fixture['finishedGood']);

    app(ProductionOrderService::class)->release($order, $user->id);

    $after = productionOrderPostingBoundarySnapshot($fixture['finishedGood']);

    expect($order->fresh()->status)->toBe(ProductionOrderStatus::RELEASED)
        ->and(productionOrderPostingBoundaryOutputQuantity($order))->toBe(0.0);

    productionOrderPostingBoundaryExpectNoFinishedOrLedgerDelta($before, $after);
});

it('posts consumption and output through the explicit production posting workflow only once', function (): void {
    $user = productionOrderPostingBoundaryUser();
    $this->actingAs($user);

    $fixture = productionOrderPostingBoundaryFixture();
    $order = productionOrderPostingBoundaryOrder($fixture, ProductionOrderStatus::FIRM_PLANNED, 3);
    productionOrderPostingBoundaryComponent($order, $fixture, 3);

    app(ProductionOrderService::class)->release($order, $user->id);

    $afterRelease = productionOrderPostingBoundarySnapshot($fixture['finishedGood']);
    $componentInventoryBeforeConsumption = (float) $fixture['rawMaterial']->fresh()->inventory;

    app(ProductionOrderService::class)->postConsumption($order->fresh(), [[
        'component_id' => $order->components()->firstOrFail()->id,
        'quantity' => 3,
        'scrap_quantity' => 0,
    ]], $user->id);

    $afterConsumption = productionOrderPostingBoundarySnapshot($fixture['finishedGood']);

    expect((float) $fixture['rawMaterial']->fresh()->inventory)->toBe($componentInventoryBeforeConsumption - 3.0)
        ->and(productionOrderPostingBoundaryConsumptionQuantity($order))->toBe(-3.0)
        ->and(productionOrderPostingBoundaryOutputQuantity($order))->toBe(0.0);

    productionOrderPostingBoundaryExpectNoFinishedDelta($afterRelease, $afterConsumption);

    app(ProductionOrderService::class)->postOutput($order->fresh(), 3, $user->id);

    $afterOutput = productionOrderPostingBoundarySnapshot($fixture['finishedGood']);

    expect((float) $fixture['finishedGood']->fresh()->inventory)->toBe($afterRelease['finished_inventory'] + 3.0)
        ->and(productionOrderPostingBoundaryOutputQuantity($order))->toBe(3.0)
        ->and(productionOrderPostingBoundaryOutputEntryCount($order))->toBe(1)
        ->and(ValueEntry::query()
            ->where('document_no', $order->document_number)
            ->where('item_no', $fixture['finishedGood']->item_code)
            ->count())->toBe(1);

    expect(fn () => app(ProductionOrderService::class)->postOutput($order->fresh(), 3, $user->id))
        ->toThrow(Exception::class, 'Cannot overproduce');

    $afterRetry = productionOrderPostingBoundarySnapshot($fixture['finishedGood']);
    expect($afterRetry)->toBe($afterOutput);

    app(ProductionOrderService::class)->finish($order->fresh(), $user->id);

    $afterFinish = productionOrderPostingBoundarySnapshot($fixture['finishedGood']);

    expect($order->fresh()->status)->toBe(ProductionOrderStatus::FINISHED)
        ->and(productionOrderPostingBoundaryOutputQuantity($order))->toBe(3.0)
        ->and(productionOrderPostingBoundaryOutputEntryCount($order))->toBe(1)
        ->and($afterFinish['finished_inventory'])->toBe($afterOutput['finished_inventory']);
});

it('cancels an untouched draft without ledger or accounting side effects', function (): void {
    $fixture = productionOrderPostingBoundaryFixture();
    $order = productionOrderPostingBoundaryOrder($fixture, ProductionOrderStatus::PLANNED);

    $before = productionOrderPostingBoundarySnapshot($fixture['finishedGood']);

    app(ProductionOrderService::class)->cancel($order);

    $after = productionOrderPostingBoundarySnapshot($fixture['finishedGood']);

    expect($order->fresh()->status)->toBe(ProductionOrderStatus::CANCELLED);
    productionOrderPostingBoundaryExpectNoFinishedOrLedgerDelta($before, $after);
});

function productionOrderPostingBoundaryUser(): User
{
    $permissions = [
        'factory.production_order.view_any',
        'factory.production_order.view',
        'factory.production_order.create',
        'factory.production_order.update',
        'factory.production_order.post_output',
        'factory.production_order.finish',
        'factory.production_order.planned.view_any',
        'factory.production_order.planned.view',
    ];

    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $user = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);
    $user->givePermissionTo($permissions);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

/**
 * @return array{
 *     generalBusinessPostingGroup: GeneralBusinessPostingGroup,
 *     generalProductPostingGroup: GeneralProductPostingGroup,
 *     inventoryPostingGroup: InventoryPostingGroup,
 *     location: Location,
 *     finishedGood: Item,
 *     rawMaterial: Item
 * }
 */
function productionOrderPostingBoundaryFixture(): array
{
    $generalBusinessPostingGroup = GeneralBusinessPostingGroup::query()->create([
        'code' => 'MFG-BOUNDARY',
        'description' => 'Manufacturing Boundary',
    ]);
    $generalProductPostingGroup = GeneralProductPostingGroup::query()->create([
        'code' => 'FG-BOUNDARY',
        'description' => 'Finished Goods Boundary',
    ]);
    $inventoryPostingGroup = InventoryPostingGroup::query()->create([
        'code' => 'INV-BOUNDARY',
        'description' => 'Inventory Boundary',
    ]);

    $inventoryAccount = productionOrderPostingBoundaryAccount('1200-BOUND', 'Inventory Boundary', AccountCategory::INVENTORY, AccountType::ASSET, IncomeBalanceType::BALANCE_SHEET);
    $wipAccount = productionOrderPostingBoundaryAccount('1210-BOUND', 'WIP Boundary', AccountCategory::INVENTORY, AccountType::ASSET, IncomeBalanceType::BALANCE_SHEET);
    $directAppliedAccount = productionOrderPostingBoundaryAccount('5100-BOUND', 'Direct Applied Boundary', AccountCategory::DIRECT_EXPENSE, AccountType::EXPENSE, IncomeBalanceType::INCOME_STATEMENT);
    $overheadAppliedAccount = productionOrderPostingBoundaryAccount('5200-BOUND', 'Overhead Applied Boundary', AccountCategory::DIRECT_EXPENSE, AccountType::EXPENSE, IncomeBalanceType::INCOME_STATEMENT);

    GeneralPostingSetup::query()->create([
        'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'direct_cost_applied_account_id' => $directAppliedAccount->id,
        'overhead_applied_account_id' => $overheadAppliedAccount->id,
        'inventory_adj_account_id' => $inventoryAccount->id,
    ]);

    InventoryPostingSetup::query()->create([
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
        'location_id' => null,
        'inventory_account_id' => $inventoryAccount->id,
        'wip_account_id' => $wipAccount->id,
    ]);

    $location = Location::factory()->create(['code' => 'MAIN']);

    $finishedGood = Item::factory()->create([
        'item_type' => ItemType::FINISHED_GOOD,
        'item_code' => 'FG-BOUNDARY',
        'description' => 'Finished Good Boundary',
        'inventory' => 0,
        'unit_cost' => 25,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
    ]);
    $rawMaterial = Item::factory()->create([
        'item_type' => ItemType::RAW_MATERIAL,
        'item_code' => 'RM-BOUNDARY',
        'description' => 'Raw Material Boundary',
        'inventory' => 10,
        'unit_cost' => 5,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
    ]);

    ItemLedgerEntry::query()->create([
        'entry_type' => ItemLedgerEntryType::PURCHASE,
        'item_id' => $rawMaterial->id,
        'location_id' => $location->id,
        'quantity' => 10,
        'remaining_quantity' => 10,
        'open' => true,
        'posting_date' => now(),
        'document_number' => 'INIT-RM-BOUNDARY',
        'document_line_number' => 10000,
        'cost_amount_actual' => 50,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
        'entry_date' => now(),
    ]);

    return compact(
        'generalBusinessPostingGroup',
        'generalProductPostingGroup',
        'inventoryPostingGroup',
        'location',
        'finishedGood',
        'rawMaterial',
    );
}

function productionOrderPostingBoundaryAccount(
    string $accountNumber,
    string $name,
    AccountCategory $accountCategory,
    AccountType $accountType,
    IncomeBalanceType $incomeBalanceType
): ChartOfAccount {
    return ChartOfAccount::query()->create([
        'account_number' => $accountNumber,
        'name' => $name,
        'account_category' => $accountCategory,
        'account_type' => $accountType,
        'income_balance' => $incomeBalanceType,
    ]);
}

/**
 * @param  array<string, mixed>  $fixture
 */
function productionOrderPostingBoundaryOrder(array $fixture, ProductionOrderStatus $status, float $quantityBase = 3): ProductionOrder
{
    return ProductionOrder::query()->create([
        'document_number' => 'PO-BOUNDARY-'.str_pad((string) (ProductionOrder::query()->count() + 1), 3, '0', STR_PAD_LEFT),
        'status' => $status,
        'source_type' => ProductionOrderSourceType::ITEM,
        'source_no' => $fixture['finishedGood']->item_code,
        'item_id' => $fixture['finishedGood']->id,
        'description' => $fixture['finishedGood']->description,
        'quantity' => $quantityBase,
        'quantity_base' => $quantityBase,
        'unit_of_measure_code' => 'PCS',
        'conversion_factor' => 1,
        'general_business_posting_group_id' => $fixture['generalBusinessPostingGroup']->id,
        'general_product_posting_group_id' => $fixture['generalProductPostingGroup']->id,
        'inventory_posting_group_id' => $fixture['inventoryPostingGroup']->id,
        'location_code' => $fixture['location']->code,
        'costing_method' => 'FIFO',
        'unit_cost' => 25,
        'cost_rollup' => 0,
        'flushing_method' => 'MANUAL',
        'due_date' => now()->addDays(7),
        'created_by' => auth()->id() ?? User::factory()->create()->id,
    ]);
}

/**
 * @param  array<string, mixed>  $fixture
 */
function productionOrderPostingBoundaryComponent(ProductionOrder $order, array $fixture, float $expectedQuantityBase): void
{
    $order->components()->create([
        'line_number' => 10000,
        'item_id' => $fixture['rawMaterial']->id,
        'description' => $fixture['rawMaterial']->description,
        'unit_of_measure_code' => 'PCS',
        'quantity_per' => 1,
        'expected_quantity' => $expectedQuantityBase,
        'expected_quantity_base' => $expectedQuantityBase,
        'remaining_quantity' => $expectedQuantityBase,
        'actual_quantity_consumed' => 0,
        'actual_scrap_quantity' => 0,
        'flushing_method' => 'MANUAL',
        'location_code' => $fixture['location']->code,
    ]);
}

/**
 * @return array<string, float|int>
 */
function productionOrderPostingBoundarySnapshot(Item $finishedGood): array
{
    return [
        'finished_inventory' => (float) $finishedGood->fresh()->inventory,
        'finished_ledger_quantity' => (float) ItemLedgerEntry::query()
            ->where('item_id', $finishedGood->id)
            ->sum('quantity'),
        'item_ledger_entries' => ItemLedgerEntry::query()->count(),
        'item_application_entries' => ItemApplicationEntry::query()->count(),
        'value_entries' => ValueEntry::query()->count(),
        'capacity_ledger_entries' => CapacityLedgerEntry::query()->count(),
        'gl_entries' => GlEntry::query()->count(),
    ];
}

/**
 * @param  array<string, float|int>  $before
 * @param  array<string, float|int>  $after
 */
function productionOrderPostingBoundaryExpectNoFinishedOrLedgerDelta(array $before, array $after): void
{
    expect($after)->toBe($before);
}

/**
 * @param  array<string, float|int>  $before
 * @param  array<string, float|int>  $after
 */
function productionOrderPostingBoundaryExpectNoFinishedDelta(array $before, array $after): void
{
    expect($after['finished_inventory'])->toBe($before['finished_inventory'])
        ->and($after['finished_ledger_quantity'])->toBe($before['finished_ledger_quantity']);
}

function productionOrderPostingBoundaryOutputQuantity(ProductionOrder $order): float
{
    return (float) ItemLedgerEntry::query()
        ->where('source_type', ProductionOrder::class)
        ->where('source_id', $order->id)
        ->where('entry_type', ItemLedgerEntryType::OUTPUT)
        ->sum('quantity');
}

function productionOrderPostingBoundaryConsumptionQuantity(ProductionOrder $order): float
{
    return (float) ItemLedgerEntry::query()
        ->where('source_type', ProductionOrder::class)
        ->where('source_id', $order->id)
        ->where('entry_type', ItemLedgerEntryType::CONSUMPTION)
        ->sum('quantity');
}

function productionOrderPostingBoundaryOutputEntryCount(ProductionOrder $order): int
{
    return ItemLedgerEntry::query()
        ->where('source_type', ProductionOrder::class)
        ->where('source_id', $order->id)
        ->where('entry_type', ItemLedgerEntryType::OUTPUT)
        ->count();
}

function productionOrderPostingBoundaryNumberSeriesLine(): ?NumberSeriesLine
{
    return NumberSeriesLine::query()
        ->whereHas('series', fn ($query) => $query->where('code', ProductionOrderNumberSeriesSetupService::CODE))
        ->first();
}
