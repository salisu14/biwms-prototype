<?php

declare(strict_types=1);

use App\Enums\AccountCategory;
use App\Enums\IncomeBalanceType;
use App\Filament\Resources\GeneralPostingSetups\Pages\CreateGeneralPostingSetup;
use App\Filament\Resources\GeneralPostingSetups\Pages\EditGeneralPostingSetup;
use App\Models\ChartOfAccount;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('hydrates existing general posting setup combinations on edit and saves account changes without clearing posting groups', function (): void {
    $fixture = generalPostingSetupFilamentFixture();
    $this->actingAs($fixture['user']);

    Livewire::actingAs($fixture['user'])
        ->test(EditGeneralPostingSetup::class, ['record' => $fixture['setup']->getRouteKey()])
        ->assertFormSet([
            'general_business_posting_group_id' => $fixture['foreignGroup']->id,
            'general_product_posting_group_id' => $fixture['rawMaterialGroup']->id,
        ])
        ->fillForm([
            'purchase_account_id' => $fixture['replacementPurchaseAccount']->id,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $fixture['setup']->refresh();

    expect($fixture['setup']->general_business_posting_group_id)->toBe($fixture['foreignGroup']->id)
        ->and($fixture['setup']->general_product_posting_group_id)->toBe($fixture['rawMaterialGroup']->id)
        ->and($fixture['setup']->purchase_account_id)->toBe($fixture['replacementPurchaseAccount']->id)
        ->and($fixture['setup']->inventory_account_id)->toBe($fixture['accounts']['inventory']->id)
        ->and($fixture['setup']->direct_cost_applied_account_id)->toBe($fixture['accounts']['directCostApplied']->id)
        ->and($fixture['setup']->purchase_variance_account_id)->toBe($fixture['accounts']['purchaseVariance']->id)
        ->and($fixture['setup']->sales_account_id)->toBe($fixture['accounts']['sales']->id)
        ->and($fixture['setup']->cogs_account_id)->toBe($fixture['accounts']['cogs']->id);
});

it('allows editing an existing general posting setup while keeping its own combination', function (): void {
    $fixture = generalPostingSetupFilamentFixture();

    Livewire::actingAs($fixture['user'])
        ->test(EditGeneralPostingSetup::class, ['record' => $fixture['setup']->getRouteKey()])
        ->fillForm([
            'blocked' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($fixture['setup']->fresh()->blocked)->toBeTrue()
        ->and($fixture['setup']->fresh()->general_business_posting_group_id)->toBe($fixture['foreignGroup']->id)
        ->and($fixture['setup']->fresh()->general_product_posting_group_id)->toBe($fixture['rawMaterialGroup']->id);
});

it('rejects duplicate general posting setup combinations on create and edit', function (): void {
    $fixture = generalPostingSetupFilamentFixture();

    Livewire::actingAs($fixture['user'])
        ->test(CreateGeneralPostingSetup::class)
        ->fillForm(generalPostingSetupFilamentPayload(
            $fixture,
            $fixture['foreignGroup']->id,
            $fixture['rawMaterialGroup']->id,
        ))
        ->call('create')
        ->assertHasFormErrors(['general_product_posting_group_id']);

    Livewire::actingAs($fixture['user'])
        ->test(EditGeneralPostingSetup::class, ['record' => $fixture['domesticFinishedSetup']->getRouteKey()])
        ->fillForm([
            'general_business_posting_group_id' => $fixture['foreignGroup']->id,
            'general_product_posting_group_id' => $fixture['rawMaterialGroup']->id,
        ])
        ->call('save')
        ->assertHasFormErrors(['general_product_posting_group_id']);
});

it('renders representative general posting setup pages', function (): void {
    $fixture = generalPostingSetupFilamentFixture();

    $this->actingAs($fixture['user'])
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get('/admin/general-posting-setups')
        ->assertSuccessful();

    $this->actingAs($fixture['user'])
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get('/admin/general-posting-setups/create')
        ->assertSuccessful();

    $this->actingAs($fixture['user'])
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get("/admin/general-posting-setups/{$fixture['setup']->id}")
        ->assertSuccessful();

    $this->actingAs($fixture['user'])
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get("/admin/general-posting-setups/{$fixture['setup']->id}/edit")
        ->assertSuccessful()
        ->assertSee('FOREIGN')
        ->assertSee('RAWMAT');
});

/**
 * @return array<string, mixed>
 */
function generalPostingSetupFilamentFixture(): array
{
    $role = Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);
    $user->assignRole($role);

    $foreignGroup = GeneralBusinessPostingGroup::query()->create([
        'code' => 'FOREIGN',
        'description' => 'Foreign Vendors',
        'blocked' => false,
    ]);
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
        'sales' => generalPostingSetupFilamentAccount('40100', 'Sales', AccountCategory::REVENUE, IncomeBalanceType::INCOME_STATEMENT),
        'cogs' => generalPostingSetupFilamentAccount('50100', 'COGS', AccountCategory::COGS, IncomeBalanceType::INCOME_STATEMENT),
        'purchase' => generalPostingSetupFilamentAccount('20200', 'Purchase Clearing', AccountCategory::LIABILITY, IncomeBalanceType::BALANCE_SHEET),
        'replacementPurchase' => generalPostingSetupFilamentAccount('20210', 'Purchase Clearing Alternate', AccountCategory::LIABILITY, IncomeBalanceType::BALANCE_SHEET),
        'inventory' => generalPostingSetupFilamentAccount('13100', 'Raw Materials Inventory', AccountCategory::INVENTORY, IncomeBalanceType::BALANCE_SHEET),
        'inventoryAdjustment' => generalPostingSetupFilamentAccount('50300', 'Inventory Adjustment', AccountCategory::DIRECT_EXPENSE, IncomeBalanceType::INCOME_STATEMENT),
        'directCostApplied' => generalPostingSetupFilamentAccount('62110', 'Direct Cost Applied', AccountCategory::DIRECT_EXPENSE, IncomeBalanceType::INCOME_STATEMENT),
        'purchaseVariance' => generalPostingSetupFilamentAccount('50310', 'Purchase Variance', AccountCategory::DIRECT_EXPENSE, IncomeBalanceType::INCOME_STATEMENT),
    ];

    $setup = GeneralPostingSetup::query()->create([
        'general_business_posting_group_id' => $foreignGroup->id,
        'general_product_posting_group_id' => $rawMaterialGroup->id,
        'sales_account_id' => $accounts['sales']->id,
        'cogs_account_id' => $accounts['cogs']->id,
        'purchase_account_id' => $accounts['purchase']->id,
        'inventory_account_id' => $accounts['inventory']->id,
        'inventory_adj_account_id' => $accounts['inventoryAdjustment']->id,
        'direct_cost_applied_account_id' => $accounts['directCostApplied']->id,
        'purchase_variance_account_id' => $accounts['purchaseVariance']->id,
        'blocked' => false,
    ]);

    $domesticFinishedSetup = GeneralPostingSetup::query()->create([
        'general_business_posting_group_id' => $domesticGroup->id,
        'general_product_posting_group_id' => $finishedGroup->id,
        'sales_account_id' => $accounts['sales']->id,
        'cogs_account_id' => $accounts['cogs']->id,
        'purchase_account_id' => $accounts['purchase']->id,
        'inventory_account_id' => $accounts['inventory']->id,
        'inventory_adj_account_id' => $accounts['inventoryAdjustment']->id,
        'blocked' => false,
    ]);

    return [
        'user' => $user,
        'foreignGroup' => $foreignGroup,
        'domesticGroup' => $domesticGroup,
        'rawMaterialGroup' => $rawMaterialGroup,
        'finishedGroup' => $finishedGroup,
        'setup' => $setup,
        'domesticFinishedSetup' => $domesticFinishedSetup,
        'accounts' => $accounts,
        'replacementPurchaseAccount' => $accounts['replacementPurchase'],
    ];
}

function generalPostingSetupFilamentAccount(
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

/**
 * @param  array<string, mixed>  $fixture
 * @return array<string, mixed>
 */
function generalPostingSetupFilamentPayload(array $fixture, int $businessGroupId, int $productGroupId): array
{
    return [
        'general_business_posting_group_id' => $businessGroupId,
        'general_product_posting_group_id' => $productGroupId,
        'sales_account_id' => $fixture['accounts']['sales']->id,
        'cogs_account_id' => $fixture['accounts']['cogs']->id,
        'purchase_account_id' => $fixture['accounts']['purchase']->id,
        'inventory_account_id' => $fixture['accounts']['inventory']->id,
        'inventory_adj_account_id' => $fixture['accounts']['inventoryAdjustment']->id,
        'blocked' => false,
    ];
}
