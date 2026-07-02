<?php

use App\Models\BankAccount;
use App\Models\Business;
use App\Models\ChartOfAccount;
use App\Models\CustomerPostingGroup;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Location;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\Role;
use App\Models\User;
use App\Models\VendorPostingGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\BufferedOutput;

uses(RefreshDatabase::class);

it('reports structured pilot readiness checks', function (): void {
    $output = new BufferedOutput;

    expect(Artisan::call('biwms:pilot-check', ['--json' => true], $output))->toBe(0);

    $report = json_decode($output->fetch(), true);
    $checks = collect($report['checks'])->keyBy('key');

    expect($report['mode'])->toBe('report-only')
        ->and($report['summary'])->toHaveKeys(['pass', 'warning', 'error'])
        ->and($checks->keys()->all())->toContain(
            'company_profile',
            'chart_of_accounts',
            'posting_groups',
            'number_series',
            'bank_cash_account',
            'inventory_locations',
            'super_admin_mfa',
            'security_audit',
            'reconciliation_commands',
        )
        ->and($checks->pluck('status')->unique()->all())->each->toBeIn(['pass', 'warning', 'error']);
});

it('passes configured pilot data readiness checks with minimum setup', function (): void {
    $account = ChartOfAccount::factory()->create([
        'structural_type' => 'posting',
        'blocked' => false,
    ]);

    $location = Location::factory()->create([
        'is_active' => true,
        'blocked' => false,
    ]);

    $generalBusinessPostingGroup = GeneralBusinessPostingGroup::factory()->create([
        'blocked' => false,
    ]);

    $generalProductPostingGroup = GeneralProductPostingGroup::query()->create([
        'code' => 'RETAIL',
        'description' => 'Retail items',
        'blocked' => false,
    ]);

    $inventoryPostingGroup = InventoryPostingGroup::query()->create([
        'code' => 'FINISHED',
        'description' => 'Finished goods',
        'blocked' => false,
    ]);

    Business::query()->create([
        'code' => 'PILOT',
        'name' => 'Pilot Company',
        'is_active' => true,
    ]);

    CustomerPostingGroup::factory()->create([
        'receivables_account_id' => $account->id,
        'blocked' => false,
    ]);

    VendorPostingGroup::factory()->create([
        'payables_account_id' => $account->id,
        'blocked' => false,
    ]);

    GeneralPostingSetup::query()->create([
        'general_business_posting_group_id' => $generalBusinessPostingGroup->id,
        'general_product_posting_group_id' => $generalProductPostingGroup->id,
        'sales_account_id' => $account->id,
        'inventory_account_id' => $account->id,
        'blocked' => false,
    ]);

    InventoryPostingSetup::query()->create([
        'location_id' => $location->id,
        'inventory_posting_group_id' => $inventoryPostingGroup->id,
        'inventory_account_id' => $account->id,
    ]);

    $numberSeries = NumberSeries::query()->create([
        'code' => 'PILOT-SALES',
        'description' => 'Pilot Sales Documents',
        'prefix' => 'PS',
        'starting_number' => 1,
        'current_number' => 0,
        'year' => now()->year,
        'is_active' => true,
        'allow_manual' => false,
        'module' => 'sales',
    ]);

    NumberSeriesLine::query()->create([
        'number_series_id' => $numberSeries->id,
        'starting_date' => now()->startOfYear(),
        'starting_no' => 1,
        'ending_no' => 999999,
        'increment_by' => 1,
        'last_no_used' => 0,
        'no_of_digits' => 6,
        'prefix' => 'PS-',
        'blocked' => false,
    ]);

    BankAccount::factory()->create([
        'gl_account_id' => $account->id,
        'active' => true,
    ]);

    $superAdminRole = Role::query()->create([
        'name' => 'super_admin',
        'guard_name' => 'web',
    ]);

    $superAdmin = User::factory()->create([
        'two_factor_secret' => 'confirmed-secret',
        'two_factor_confirmed_at' => now(),
    ]);
    $superAdmin->assignRole($superAdminRole);

    $output = new BufferedOutput;

    expect(Artisan::call('biwms:pilot-check', ['--json' => true], $output))->toBe(0);

    $report = json_decode($output->fetch(), true);
    $checks = collect($report['checks'])->keyBy('key');

    expect($checks->get('company_profile')['status'])->toBe('pass')
        ->and($checks->get('chart_of_accounts')['status'])->toBe('pass')
        ->and($checks->get('posting_groups')['status'])->toBe('pass')
        ->and($checks->get('number_series')['status'])->toBe('pass')
        ->and($checks->get('bank_cash_account')['status'])->toBe('pass')
        ->and($checks->get('inventory_locations')['status'])->toBe('pass')
        ->and($checks->get('super_admin_mfa')['status'])->toBe('pass')
        ->and($checks->has('security_audit'))->toBeTrue()
        ->and($checks->has('reconciliation_commands'))->toBeTrue();
});
