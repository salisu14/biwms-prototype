<?php

declare(strict_types=1);

use App\Enums\ItemType;
use App\Enums\ProductionOrderSourceType;
use App\Enums\ProductionOrderStatus;
use App\Filament\Resources\ProductionOrders\Pages\CreateProductionOrder;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralProductPostingGroup;
use App\Models\InventoryPostingGroup;
use App\Models\Item;
use App\Models\Location;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Manufacturing\ProductionOrderNumberSeriesSetupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

function productionOrderNumberSeriesUser(): User
{
    $permissions = [
        'factory.production_order.planned.view_any',
        'factory.production_order.planned.view',
        'factory.production_order.view_any',
        'factory.production_order.view',
        'factory.production_order.create',
    ];

    foreach ($permissions as $permission) {
        Permission::query()->firstOrCreate([
            'name' => $permission,
            'guard_name' => 'web',
        ]);
    }

    $role = Role::query()->firstOrCreate([
        'name' => 'factory-manager',
        'guard_name' => 'web',
    ]);
    $role->syncPermissions($permissions);

    $user = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole($role);

    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

/**
 * @return array<string, mixed>
 */
function productionOrderNumberSeriesPayload(): array
{
    $inventoryPostingGroup = InventoryPostingGroup::query()->firstOrCreate(
        ['code' => 'FG'],
        ['description' => 'Finished Goods', 'blocked' => false],
    );
    $generalProductPostingGroup = GeneralProductPostingGroup::query()->firstOrCreate(
        ['code' => 'FG'],
        [
            'description' => 'Finished Goods',
            'default_vat_product_posting_group_id' => null,
            'auto_create_vat_prod_posting_group' => false,
            'blocked' => false,
        ],
    );
    $generalBusinessPostingGroup = GeneralBusinessPostingGroup::factory()->create();
    $location = Location::query()->firstOrCreate(
        ['code' => 'MAIN'],
        ['name' => 'Main Location', 'is_active' => true, 'blocked' => false],
    );
    $item = Item::factory()->create([
        'item_type' => ItemType::FINISHED_GOOD,
        'blocked' => false,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'unit_cost' => 125,
    ]);

    return [
        'status' => ProductionOrderStatus::SIMULATED->value,
        'source_type' => ProductionOrderSourceType::ITEM->value,
        'source_no' => $item->item_code,
        'item_id' => $item->id,
        'description' => $item->description,
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'quantity_base' => 1,
        'conversion_factor' => 1,
        'flushing_method' => 'MANUAL',
        'scrap_percent' => 0,
        'due_date' => now()->addDays(7)->toDateString(),
        'location_code' => $location->code,
        'costing_method' => 'STANDARD',
        'unit_cost' => 125,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
        'priority' => 100,
        'reserved_from_stock' => false,
    ];
}

function productionOrderNumberSeriesLine(): ?NumberSeriesLine
{
    return NumberSeriesLine::query()
        ->whereHas('series', fn ($query) => $query->where('code', ProductionOrderNumberSeriesSetupService::CODE))
        ->first();
}

it('renders the production order create page without consuming numbers when the series is missing', function (): void {
    $user = productionOrderNumberSeriesUser();

    $this->actingAs($user)
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get('/factory/production-orders/create')
        ->assertSuccessful();

    Livewire::actingAs($user)
        ->test(CreateProductionOrder::class)
        ->assertNotified('Production Order Number Series is not configured');

    expect(ProductionOrder::query()->count())->toBe(0)
        ->and(NumberSeries::query()->where('code', ProductionOrderNumberSeriesSetupService::CODE)->exists())->toBeFalse();
});

it('blocks production order creation with a clear configuration notification when the series is missing', function (): void {
    $user = productionOrderNumberSeriesUser();

    Livewire::actingAs($user)
        ->test(CreateProductionOrder::class)
        ->fillForm(productionOrderNumberSeriesPayload())
        ->call('create')
        ->assertNotified('Production Order Number Series is not configured');

    expect(ProductionOrder::query()->count())->toBe(0);
});

it('provisions the production order series idempotently without resetting an existing counter', function (): void {
    $service = app(ProductionOrderNumberSeriesSetupService::class);

    $first = $service->ensure();
    $line = NumberSeriesLine::query()->findOrFail($first['line_id']);
    $line->update(['last_no_used' => 41]);

    $second = $service->ensure();

    expect($first['series_created'])->toBeTrue()
        ->and($first['line_created'])->toBeTrue()
        ->and($second['series_created'])->toBeFalse()
        ->and($second['line_created'])->toBeFalse()
        ->and(NumberSeries::query()->where('code', ProductionOrderNumberSeriesSetupService::CODE)->count())->toBe(1)
        ->and(NumberSeriesLine::query()->where('number_series_id', $first['series_id'])->count())->toBe(1)
        ->and($line->refresh()->last_no_used)->toBe(41);
});

it('provisions the production order series from the setup command', function (): void {
    $this->artisan('biwms:production-order-series-setup')
        ->assertSuccessful()
        ->expectsOutputToContain('Production Order Number Series PROD-ORDER: series created, line created');

    $this->artisan('biwms:production-order-series-setup')
        ->assertSuccessful()
        ->expectsOutputToContain('Production Order Number Series PROD-ORDER: series found, line found');

    expect(NumberSeries::query()->where('code', ProductionOrderNumberSeriesSetupService::CODE)->count())->toBe(1)
        ->and(productionOrderNumberSeriesLine()?->last_no_used)->toBe(0);
});

it('does not consume a number when the create page is opened or validation fails', function (): void {
    app(ProductionOrderNumberSeriesSetupService::class)->ensure();
    $user = productionOrderNumberSeriesUser();

    Livewire::actingAs($user)
        ->test(CreateProductionOrder::class);

    expect(productionOrderNumberSeriesLine()?->last_no_used)->toBe(0);

    Livewire::actingAs($user)
        ->test(CreateProductionOrder::class)
        ->call('create')
        ->assertHasFormErrors();

    expect(productionOrderNumberSeriesLine()?->last_no_used)->toBe(0);
});

it('consumes exactly one production order number for each successful create', function (): void {
    app(ProductionOrderNumberSeriesSetupService::class)->ensure();
    $user = productionOrderNumberSeriesUser();

    $component = Livewire::actingAs($user)
        ->test(CreateProductionOrder::class);

    expect(productionOrderNumberSeriesLine()?->last_no_used)->toBe(0);

    $component
        ->fillForm(productionOrderNumberSeriesPayload());

    expect(productionOrderNumberSeriesLine()?->last_no_used)->toBe(0);

    $component
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ProductionOrder::query()->count())->toBe(1);

    expect(ProductionOrder::query()->first()?->document_number)->toBe('PROD-00001');

    expect(productionOrderNumberSeriesLine()?->last_no_used)->toBe(1)
        ->and(ProductionOrder::query()->first()?->document_number)->toBe('PROD-00001');

    Livewire::actingAs($user)
        ->test(CreateProductionOrder::class)
        ->fillForm(productionOrderNumberSeriesPayload())
        ->call('create')
        ->assertHasNoFormErrors();

    expect(productionOrderNumberSeriesLine()?->last_no_used)->toBe(2)
        ->and(ProductionOrder::query()->orderByDesc('id')->first()?->document_number)->toBe('PROD-00002');
});
