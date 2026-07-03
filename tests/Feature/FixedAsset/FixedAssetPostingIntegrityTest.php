<?php

use App\Enums\AccountCategory;
use App\Enums\DepreciationCalculationMethod;
use App\Enums\DepreciationMethod;
use App\Enums\FAPostingType;
use App\Enums\FAStatus;
use App\Enums\FixedAssetType;
use App\Events\FixedAssetPosted;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\DepreciationBook;
use App\Models\FALedgerEntry;
use App\Models\FAPostingGroup;
use App\Models\FixedAsset;
use App\Models\GeneralLedgerSetup;
use App\Models\GlEntry;
use App\Models\Permission;
use App\Models\User;
use App\Services\FixedAsset\AcquisitionService;
use App\Services\FixedAsset\DisposalService;
use App\Services\FixedAsset\FAPostingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

test('fixed assets schema stores net book value as a normal decimal column', function () {
    expect(Schema::hasColumn('fixed_assets', 'net_book_value'))->toBeTrue();

    $columnType = Schema::getColumnType('fixed_assets', 'net_book_value');
    expect($columnType)->toBeIn(['decimal', 'numeric']);

    if (DB::getDriverName() === 'pgsql') {
        $column = DB::selectOne(
            'select is_generated from information_schema.columns where table_schema = current_schema() and table_name = ? and column_name = ?',
            ['fixed_assets', 'net_book_value']
        );

        expect($column?->is_generated)->toBe('NEVER');
    }

    if (DB::getDriverName() === 'sqlite') {
        $column = collect(DB::select('pragma table_xinfo(fixed_assets)'))
            ->firstWhere('name', 'net_book_value');

        expect((int) ($column->hidden ?? 0))->toBe(0);
    }
});

test('migrations do not use database generated column helpers', function () {
    $helpers = ['virtual'.'As(', 'stored'.'As(', 'generated'.'As('];

    $generatedColumnHelpers = collect(glob(database_path('migrations/*.php')))
        ->map(fn (string $path): string => file_get_contents($path) ?: '')
        ->filter(fn (string $contents): bool => collect($helpers)->contains(
            fn (string $helper): bool => str_contains($contents, $helper)
        ));

    expect($generatedColumnHelpers)->toBeEmpty();
});

test('fixed asset net book value is recalculated before save', function () {
    $fixture = fixedAssetPostingFixture();

    $asset = fixedAssetForPosting($fixture, [
        'fa_no' => 'FA-NBV-001',
        'acquisition_cost' => 5000,
        'book_value' => 5000,
        'accumulated_depreciation' => 1250,
    ]);

    expect((float) DB::table('fixed_assets')->where('id', $asset->id)->value('net_book_value'))->toBe(3750.0);

    $asset->forceFill([
        'book_value' => 6200,
        'accumulated_depreciation' => 1700,
    ])->save();

    expect((float) DB::table('fixed_assets')->where('id', $asset->id)->value('net_book_value'))->toBe(4500.0);
});

test('fixed asset acquisition creates ledger, balanced gl, and updates asset atomically', function () {
    Event::fake([FixedAssetPosted::class]);

    $fixture = fixedAssetPostingFixture();
    grantFixedAssetPermission($fixture['user'], 'fixed_asset.acquire');
    $this->actingAs($fixture['user']);

    $asset = app(AcquisitionService::class)->acquire([
        'fa_no' => 'FA-ACQ-001',
        'description' => 'Packaging Machine',
        'fa_type' => FixedAssetType::TANGIBLE,
        'fa_posting_group_id' => $fixture['postingGroup']->id,
        'depreciation_book_id' => $fixture['book']->id,
        'acquisition_date' => '2026-06-15',
        'acquisition_cost' => 10000,
        'depreciation_method' => DepreciationMethod::STRAIGHT_LINE,
        'useful_life_years' => 5,
        'offset_account_id' => $fixture['clearingAccount']->id,
        'document_no' => 'FA-ACQ-001',
    ]);

    $glEntries = GlEntry::query()->where('document_number', 'FA-ACQ-001')->get();

    expect($asset->fresh()->status)->toBe(FAStatus::ACTIVE)
        ->and((float) $asset->fresh()->acquisition_cost)->toBe(10000.0)
        ->and((float) $asset->fresh()->book_value)->toBe(10000.0)
        ->and((float) DB::table('fixed_assets')->where('id', $asset->id)->value('net_book_value'))->toBe(10000.0)
        ->and(FALedgerEntry::query()->where('fixed_asset_id', $asset->id)->where('fa_posting_type', FAPostingType::ACQUISITION)->exists())->toBeTrue()
        ->and(round((float) $glEntries->sum('debit_amount'), 2))->toBe(10000.0)
        ->and(round((float) $glEntries->sum('credit_amount'), 2))->toBe(10000.0);

    Event::assertDispatched(FixedAssetPosted::class);

    expect(fn () => app(FAPostingService::class)->postEntry($asset->fresh(), FAPostingType::ACQUISITION, 100, 'Duplicate acquisition', 'FA-ACQ-DUP', new DateTime('2026-06-16')))
        ->toThrow(RuntimeException::class, 'already posted');
});

test('fixed asset depreciation creates ledger and balanced gl, updates accumulated depreciation, and blocks same period duplicate', function () {
    $fixture = fixedAssetPostingFixture();
    grantFixedAssetPermission($fixture['user'], 'fixed_asset.depreciate');
    $this->actingAs($fixture['user']);

    $asset = fixedAssetForPosting($fixture, [
        'fa_no' => 'FA-DEP-001',
        'acquisition_cost' => 12000,
        'book_value' => 12000,
    ]);

    app(FAPostingService::class)->postEntry($asset, FAPostingType::DEPRECIATION, 1000, 'June depreciation', 'FA-DEP-202606', new DateTime('2026-06-30'));

    $glEntries = GlEntry::query()->where('document_number', 'FA-DEP-202606')->get();

    expect((float) $asset->fresh()->accumulated_depreciation)->toBe(1000.0)
        ->and((float) DB::table('fixed_assets')->where('id', $asset->id)->value('net_book_value'))->toBe(11000.0)
        ->and(FALedgerEntry::query()->where('fixed_asset_id', $asset->id)->where('fa_posting_type', FAPostingType::DEPRECIATION)->exists())->toBeTrue()
        ->and(round((float) $glEntries->sum('debit_amount'), 2))->toBe(1000.0)
        ->and(round((float) $glEntries->sum('credit_amount'), 2))->toBe(1000.0);

    expect(fn () => app(FAPostingService::class)->postEntry($asset->fresh(), FAPostingType::DEPRECIATION, 1000, 'Duplicate June depreciation', 'FA-DEP-DUP', new DateTime('2026-06-20')))
        ->toThrow(RuntimeException::class, 'already posted for 2026-06');
});

test('fixed asset posting requires permission and rolls back when account setup fails', function () {
    $fixture = fixedAssetPostingFixture();
    $this->actingAs($fixture['user']);

    $asset = fixedAssetForPosting($fixture, [
        'fa_no' => 'FA-NOAUTH-001',
        'acquisition_cost' => 12000,
        'book_value' => 12000,
    ]);

    expect(fn () => app(FAPostingService::class)->postEntry($asset, FAPostingType::DEPRECIATION, 500, 'Unauthorized', 'FA-NOAUTH', new DateTime('2026-06-30')))
        ->toThrow(AuthorizationException::class);

    grantFixedAssetPermission($fixture['user'], 'fixed_asset.depreciate');
    $fixture['postingGroup']->forceFill(['accumulated_depreciation_account_id' => null])->save();

    expect(fn () => app(FAPostingService::class)->postEntry($asset->fresh(), FAPostingType::DEPRECIATION, 500, 'Missing account', 'FA-MISSING-ACCOUNT', new DateTime('2026-06-30')))
        ->toThrow(RuntimeException::class, 'Posting accounts are incomplete');

    expect((float) $asset->fresh()->accumulated_depreciation)->toBe(0.0)
        ->and(FALedgerEntry::query()->where('document_no', 'FA-MISSING-ACCOUNT')->exists())->toBeFalse()
        ->and(GlEntry::query()->where('document_number', 'FA-MISSING-ACCOUNT')->exists())->toBeFalse();
});

test('fixed asset disposal clears cost and depreciation, posts proceeds and gain, and blocks duplicate disposal', function () {
    $fixture = fixedAssetPostingFixture();
    grantFixedAssetPermission($fixture['user'], 'fixed_asset.dispose');
    $this->actingAs($fixture['user']);

    $asset = fixedAssetForPosting($fixture, [
        'fa_no' => 'FA-DISP-001',
        'acquisition_cost' => 1000,
        'book_value' => 1000,
        'accumulated_depreciation' => 200,
    ]);

    app(DisposalService::class)->dispose($asset, 900, new DateTime('2026-06-30'), 'sale');

    $documentNo = 'FAD-'.$asset->id.'-20260630';
    $glEntries = GlEntry::query()->where('document_number', $documentNo)->get();

    expect($asset->fresh()->status)->toBe(FAStatus::DISPOSED)
        ->and((float) $asset->fresh()->book_value)->toBe(0.0)
        ->and((float) DB::table('fixed_assets')->where('id', $asset->id)->value('net_book_value'))->toBe(0.0)
        ->and((float) $asset->fresh()->disposal_gain_loss)->toBe(100.0)
        ->and(FALedgerEntry::query()->where('fixed_asset_id', $asset->id)->where('fa_posting_type', FAPostingType::DISPOSAL)->exists())->toBeTrue()
        ->and(round((float) $glEntries->sum('debit_amount'), 2))->toBe(1100.0)
        ->and(round((float) $glEntries->sum('credit_amount'), 2))->toBe(1100.0);

    expect(fn () => app(DisposalService::class)->dispose($asset->fresh(), 900, new DateTime('2026-06-30'), 'sale'))
        ->toThrow(RuntimeException::class, 'cannot be disposed');
});

function fixedAssetPostingFixture(): array
{
    ensureFixedAssetOpenPostingPeriod();

    $user = User::factory()->create();
    $assetAccount = ChartOfAccount::factory()->create(['account_category' => AccountCategory::FIXED_ASSET]);
    $clearingAccount = ChartOfAccount::factory()->create(['account_category' => AccountCategory::LIABILITY]);
    $depreciationExpense = ChartOfAccount::factory()->create(['account_category' => AccountCategory::OPERATING_EXPENSE]);
    $accumulatedDepreciation = ChartOfAccount::factory()->create(['account_category' => AccountCategory::LIABILITY]);
    $proceedsAccount = ChartOfAccount::factory()->create(['account_category' => AccountCategory::ASSET]);
    $gainAccount = ChartOfAccount::factory()->create(['account_category' => AccountCategory::REVENUE]);
    $lossAccount = ChartOfAccount::factory()->create(['account_category' => AccountCategory::OPERATING_EXPENSE]);

    $postingGroup = FAPostingGroup::query()->create([
        'code' => 'FA',
        'description' => 'Fixed Assets',
        'acquisition_cost_account_id' => $assetAccount->id,
        'depreciation_expense_account_id' => $depreciationExpense->id,
        'accumulated_depreciation_account_id' => $accumulatedDepreciation->id,
        'disposal_proceeds_account_id' => $proceedsAccount->id,
        'disposal_gain_account_id' => $gainAccount->id,
        'disposal_loss_account_id' => $lossAccount->id,
        'capitalization_account_id' => $clearingAccount->id,
    ]);

    $book = DepreciationBook::query()->create([
        'code' => 'MAIN',
        'description' => 'Main Book',
        'book_type' => 'corporate',
        'default_depreciation_method' => DepreciationMethod::STRAIGHT_LINE,
        'default_calculation_method' => DepreciationCalculationMethod::STRAIGHT_LINE,
        'is_active' => true,
        'integrate_with_gl' => true,
    ]);

    return compact('user', 'postingGroup', 'book', 'clearingAccount');
}

function fixedAssetForPosting(array $fixture, array $attributes = []): FixedAsset
{
    return FixedAsset::query()->create(array_merge([
        'fa_no' => fake()->unique()->bothify('FA-####'),
        'description' => 'Test Fixed Asset',
        'fa_type' => FixedAssetType::TANGIBLE,
        'fa_posting_group_id' => $fixture['postingGroup']->id,
        'depreciation_book_id' => $fixture['book']->id,
        'acquisition_date' => '2026-01-01',
        'depreciation_starting_date' => '2026-01-01',
        'acquisition_cost' => 0,
        'book_value' => 0,
        'accumulated_depreciation' => 0,
        'depreciation_method' => DepreciationMethod::STRAIGHT_LINE,
        'useful_life_years' => 5,
        'salvage_value' => 0,
        'status' => FAStatus::ACTIVE,
        'created_by' => $fixture['user']->id,
    ], $attributes));
}

function grantFixedAssetPermission(User $user, string $permission): void
{
    Permission::query()->firstOrCreate([
        'name' => $permission,
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo($permission);
}

function ensureFixedAssetOpenPostingPeriod(): void
{
    GeneralLedgerSetup::query()->firstOrCreate(['company_name' => 'Default Company'], [
        'allow_posting_from' => '2026-01-01',
        'allow_posting_to' => '2026-12-31',
    ]);

    AccountingPeriod::query()->firstOrCreate([
        'start_date' => '2026-01-01',
        'end_date' => '2026-12-31',
    ], [
        'name' => 'FY2026',
        'is_closed' => false,
    ]);
}
