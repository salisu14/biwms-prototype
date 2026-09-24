<?php

declare(strict_types=1);

use App\Enums\AccountCategory;
use App\Enums\IncomeBalanceType;
use App\Filament\Resources\GeneralPostingSetups\Pages\ListGeneralPostingSetups;
use App\Models\ChartOfAccount;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\Role;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('renders the general posting setup list', function (): void {
    $fixture = gpsVisibilityFixture();

    $this->actingAs($fixture['user'])
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get('/admin/general-posting-setups')
        ->assertSuccessful();
});

it('exposes Purchase Account as a table column and resolves it to number and name', function (): void {
    $fixture = gpsVisibilityFixture();

    Livewire::actingAs($fixture['user'])
        ->test(ListGeneralPostingSetups::class)
        ->assertSuccessful()
        ->assertTableColumnExists('purchaseAccount.account_number')
        ->assertSee('20200 — Purchase Clearing');
});

it('resolves every account column to number and name rather than raw ids', function (): void {
    $fixture = gpsVisibilityFixture();

    Livewire::actingAs($fixture['user'])
        ->test(ListGeneralPostingSetups::class)
        ->assertSuccessful()
        ->assertSee('40100 — Sales')
        ->assertSee('50100 — COGS')
        ->assertSee('13100 — Raw Materials Inventory');
});

it('renders Purchase Account on the view page and resolves variance accounts', function (): void {
    $fixture = gpsVisibilityFixture();

    $this->actingAs($fixture['user'])
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get("/admin/general-posting-setups/{$fixture['configuredSetup']->getKey()}")
        ->assertSuccessful()
        ->assertSee('Purchase Account / Clearing (GRNI)')
        ->assertSee('20200 — Purchase Clearing')
        ->assertSee('50310 — Purchase Variance');
});

it('shows a neutral placeholder for an unconfigured Purchase Account', function (): void {
    $fixture = gpsVisibilityFixture();

    $this->actingAs($fixture['user'])
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get("/admin/general-posting-setups/{$fixture['unconfiguredSetup']->getKey()}")
        ->assertSuccessful()
        ->assertSee('Purchase Account / Clearing (GRNI)')
        // Unconfigured mapping renders the neutral placeholder, never a raw id.
        ->assertSee('—');
});

it('still renders the edit form with the Purchase Account field', function (): void {
    $fixture = gpsVisibilityFixture();

    $this->actingAs($fixture['user'])
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get("/admin/general-posting-setups/{$fixture['configuredSetup']->getKey()}/edit")
        ->assertSuccessful()
        ->assertSee('Purchase Account');
});

it('keeps strict authorization enabled', function (): void {
    gpsVisibilityFixture();

    expect(Filament::getDefaultPanel()->isAuthorizationStrict())->toBeTrue();
});

/**
 * @return array{
 *     user: User,
 *     configuredSetup: GeneralPostingSetup,
 *     unconfiguredSetup: GeneralPostingSetup,
 *     accounts: array<string, ChartOfAccount>
 * }
 */
function gpsVisibilityFixture(): array
{
    $role = Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole($role);

    $domesticGroup = GeneralBusinessPostingGroup::query()->create([
        'code' => 'DOMESTIC',
        'description' => 'Domestic Vendors',
        'blocked' => false,
    ]);
    $rawMaterialGroup = GeneralProductPostingGroup::query()->create([
        'code' => 'RAWMAT',
        'description' => 'Raw Materials',
        'blocked' => false,
    ]);
    $finishedGroup = GeneralProductPostingGroup::query()->create([
        'code' => 'FINISHED',
        'description' => 'Finished Goods',
        'blocked' => false,
    ]);

    $accounts = [
        'sales' => gpsVisibilityAccount('40100', 'Sales', AccountCategory::REVENUE, IncomeBalanceType::INCOME_STATEMENT),
        'cogs' => gpsVisibilityAccount('50100', 'COGS', AccountCategory::COGS, IncomeBalanceType::INCOME_STATEMENT),
        'purchase' => gpsVisibilityAccount('20200', 'Purchase Clearing', AccountCategory::LIABILITY, IncomeBalanceType::BALANCE_SHEET),
        'inventory' => gpsVisibilityAccount('13100', 'Raw Materials Inventory', AccountCategory::INVENTORY, IncomeBalanceType::BALANCE_SHEET),
        'inventoryAdjustment' => gpsVisibilityAccount('50300', 'Inventory Adjustment', AccountCategory::DIRECT_EXPENSE, IncomeBalanceType::INCOME_STATEMENT),
        'purchaseVariance' => gpsVisibilityAccount('50310', 'Purchase Variance', AccountCategory::DIRECT_EXPENSE, IncomeBalanceType::INCOME_STATEMENT),
    ];

    $configuredSetup = GeneralPostingSetup::query()->create([
        'general_business_posting_group_id' => $domesticGroup->id,
        'general_product_posting_group_id' => $rawMaterialGroup->id,
        'sales_account_id' => $accounts['sales']->id,
        'cogs_account_id' => $accounts['cogs']->id,
        'purchase_account_id' => $accounts['purchase']->id,
        'inventory_account_id' => $accounts['inventory']->id,
        'inventory_adj_account_id' => $accounts['inventoryAdjustment']->id,
        'purchase_variance_account_id' => $accounts['purchaseVariance']->id,
        'blocked' => false,
    ]);

    $unconfiguredSetup = GeneralPostingSetup::query()->create([
        'general_business_posting_group_id' => $domesticGroup->id,
        'general_product_posting_group_id' => $finishedGroup->id,
        'purchase_account_id' => null,
        'blocked' => false,
    ]);

    return [
        'user' => $user,
        'configuredSetup' => $configuredSetup,
        'unconfiguredSetup' => $unconfiguredSetup,
        'accounts' => $accounts,
    ];
}

function gpsVisibilityAccount(
    string $number,
    string $name,
    AccountCategory $category,
    IncomeBalanceType $incomeBalance,
): ChartOfAccount {
    return ChartOfAccount::query()->create([
        'account_number' => $number,
        'name' => $name,
        'account_category' => $category,
        'income_balance' => $incomeBalance,
        'direct_posting' => true,
        'blocked' => false,
    ]);
}
