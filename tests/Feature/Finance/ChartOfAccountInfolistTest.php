<?php

declare(strict_types=1);

use App\Enums\AccountCategory;
use App\Enums\AccountStructuralType;
use App\Enums\IncomeBalanceType;
use App\Models\ChartOfAccount;
use App\Models\User;
use Database\Seeders\PermissionsTableSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->seed(PermissionsTableSeeder::class);
});

it('renders the chart of account infolist with readable statuses and formatting', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('chart_of_account.manage');

    $account = ChartOfAccount::factory()->create([
        'account_number' => '6100',
        'name' => 'Factory Overhead Expense',
        'search_name' => 'FACTORY OVERHEAD',
        'account_category' => AccountCategory::OPERATING_EXPENSE,
        'structural_type' => AccountStructuralType::POSTING,
        'income_balance' => IncomeBalanceType::INCOME_STATEMENT,
        'no_of_blank_lines' => 2,
        'balance' => -1250.50,
        'balance_at_date' => 300,
        'direct_posting' => true,
        'blocked' => false,
    ]);

    $this->actingAs($user)
        ->get("/admin/chart-of-accounts/{$account->getKey()}")
        ->assertSuccessful()
        ->assertSee('General Information')
        ->assertSee('Factory Overhead Expense')
        ->assertSee('Operating Expense')
        ->assertSee('Posting')
        ->assertSee('Income Statement')
        ->assertSee('2 line(s)')
        ->assertSee('Active - Ready for Posting')
        ->assertSee('FACTORY OVERHEAD');
});

it('renders non-posting and blocked chart of account states safely', function (): void {
    $user = User::factory()->create();
    $user->givePermissionTo('chart_of_account.manage');

    $account = ChartOfAccount::factory()->create([
        'account_number' => '1000',
        'name' => 'Assets Heading',
        'account_category' => 'asset',
        'structural_type' => 'heading',
        'income_balance' => 0,
        'no_of_blank_lines' => 0,
        'direct_posting' => false,
        'blocked' => true,
        'blocked_from' => now()->toDateString(),
    ]);

    $this->actingAs($user)
        ->get("/admin/chart-of-accounts/{$account->getKey()}")
        ->assertSuccessful()
        ->assertSee('Assets Heading')
        ->assertSee('Asset')
        ->assertSee('Heading')
        ->assertSee('Balance Sheet')
        ->assertSee('0 line(s)')
        ->assertSee('Blocked')
        ->assertSee('No Direct Posting')
        ->assertSee('Heading Account');
});
