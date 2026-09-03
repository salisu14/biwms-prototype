<?php

declare(strict_types=1);

use App\Enums\CategoryType;
use App\Enums\ItemType;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Item;
use App\Models\Location;
use App\Models\UnitOfMeasure;
use App\Models\User;
use Database\Seeders\PermissionsTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(PermissionsTableSeeder::class);
});

it('renders the item view in a wide enterprise layout with distinct cost sections', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo([
        'sales.item.view_any',
        'sales.item.view',
    ]);

    $currency = Currency::factory()->create([
        'code' => 'NGN',
        'symbol' => 'NGN',
        'is_lcy' => true,
    ]);
    $uom = UnitOfMeasure::query()->create([
        'uom_code' => 'PCS',
        'description' => 'Pieces',
        'conversion_factor' => 1,
        'is_base_uom' => true,
    ]);
    $location = Location::factory()->create([
        'code' => 'GBS-FGN',
        'name' => 'Gabasawa Finished Goods Store',
    ]);
    $category = Category::query()->create([
        'category_code' => 'FGN',
        'category_name' => 'Uncategorized',
        'hierarchy_path' => 'Uncategorized',
        'category_type' => CategoryType::FINISHED_GOOD,
        'level' => 0,
        'sort_order' => 1,
        'is_active' => true,
    ]);

    $item = Item::factory()->create([
        'item_code' => '1000',
        'description' => 'Mai Sasanci',
        'item_type' => ItemType::FINISHED_GOOD,
        'unit_price' => 1200,
        'standard_cost' => 850,
        'inventory' => 50,
        'reorder_point' => 10,
        'bin_code' => 'A-01-01',
        'currency_id' => $currency->id,
        'base_uom_id' => $uom->id,
        'location_id' => $location->id,
        'item_category_id' => $category->id,
    ]);

    $this->actingAs($user)
        ->get("/admin/items/{$item->getKey()}")
        ->assertSuccessful()
        ->assertSee('1000 - Mai Sasanci')
        ->assertSee('Item Overview')
        ->assertSee('Financials')
        ->assertSee('Stock Status')
        ->assertSee('Logistics')
        ->assertSee('Actual Performance (YTD)')
        ->assertSee('Manufacturing')
        ->assertSee('Posting Groups &amp; Configuration', false)
        ->assertSee('Order History Snapshot')
        ->assertSee('Restrictions')
        ->assertSee('System Information')
        ->assertSee('Standard / Reference Cost')
        ->assertSee('Current Actual Inventory Cost (LCY)')
        ->assertSee('Last Actual Production Cost (LCY)')
        ->assertSee('Average Actual Production Cost (LCY)')
        ->assertSee('Mai Sasanci')
        ->assertSee('Finished Good')
        ->assertSee('PCS')
        ->assertSee('Gabasawa Finished Goods Store');
});

it('keeps item view layout imports on Filament 5 schema components', function (): void {
    $source = file_get_contents(app_path('Filament/Resources/Items/Schemas/ItemInfolist.php'));
    $pageSource = file_get_contents(app_path('Filament/Resources/Items/Pages/ViewItem.php'));

    expect($source)
        ->toContain('use Filament\Schemas\Components\Grid;')
        ->toContain('use Filament\Schemas\Components\Section;')
        ->not->toContain('Filament\Infolists\Components\Grid')
        ->not->toContain('Filament\Infolists\Components\Section')
        ->and($pageSource)
        ->toContain('use Filament\Support\Enums\Width;')
        ->toContain('Width::Full');
});
