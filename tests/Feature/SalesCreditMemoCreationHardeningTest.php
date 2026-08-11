<?php

declare(strict_types=1);

use App\Enums\ApprovalStatus;
use App\Enums\ItemType;
use App\Filament\Resources\SalesCreditMemos\Pages\CreateSalesCreditMemo;
use App\Filament\Resources\SalesCreditMemos\SalesCreditMemoResource;
use App\Models\Customer;
use App\Models\Item;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\Role;
use App\Models\SalesCreditMemo;
use App\Models\SalesCreditMemoLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

it('casts sales credit memo status to approval status draft', function (): void {
    $customer = Customer::factory()->create();

    $memo = SalesCreditMemo::query()->create([
        'memo_number' => 'SCM-CAST-001',
        'customer_id' => $customer->id,
        'status' => ApprovalStatus::DRAFT,
        'effective_date' => now()->toDateString(),
        'currency_code' => 'NGN',
        'total_amount' => 0,
    ]);

    expect($memo->fresh()->status)->toBe(ApprovalStatus::DRAFT);
});

it('does not allocate a sales credit memo number when opening the create page', function (): void {
    $user = salesCreditMemoCreationUser();
    salesCreditMemoCreationNumberSeries();

    $this->actingAs($user)
        ->withSession(['two_factor_passed_at' => now()->timestamp])
        ->get(SalesCreditMemoResource::getUrl('create'))
        ->assertSuccessful();

    expect(salesCreditMemoCreationNumberSeriesLine()->last_no_used)->toBe(0)
        ->and(SalesCreditMemo::query()->count())->toBe(0);
});

it('shows controlled feedback when sales credit memo number series is missing', function (): void {
    $user = salesCreditMemoCreationUser();
    $payload = salesCreditMemoCreationPayload();

    Livewire::actingAs($user)
        ->test(CreateSalesCreditMemo::class)
        ->fillForm($payload)
        ->call('create')
        ->assertNotified('Sales Credit Memo Number Series is not configured');

    expect(SalesCreditMemo::query()->count())->toBe(0)
        ->and(SalesCreditMemoLine::query()->count())->toBe(0);
});

it('creates a draft sales credit memo through the service owned Filament create flow', function (): void {
    $user = salesCreditMemoCreationUser();
    salesCreditMemoCreationNumberSeries();
    $payload = salesCreditMemoCreationPayload();

    Livewire::actingAs($user)
        ->test(CreateSalesCreditMemo::class)
        ->fillForm($payload)
        ->call('create')
        ->assertHasNoFormErrors();

    $memo = SalesCreditMemo::query()
        ->with('items')
        ->where('memo_number', 'SCM-000001')
        ->firstOrFail();

    expect($memo->memo_number)->toBe('SCM-000001')
        ->and($memo->status)->toBe(ApprovalStatus::DRAFT)
        ->and($memo->items)->toHaveCount(1)
        ->and((float) $memo->items->first()->quantity)->toBe(2.0)
        ->and((float) $memo->total_amount)->toBe(215.0)
        ->and(salesCreditMemoCreationNumberSeriesLine()->last_no_used)->toBe(1);
});

function salesCreditMemoCreationUser(): User
{
    $role = Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);

    $user->assignRole($role);

    return $user;
}

function salesCreditMemoCreationNumberSeries(): void
{
    $series = NumberSeries::query()->create([
        'code' => 'S-CM',
        'description' => 'Sales Credit Memo test series',
        'prefix' => 'SCM-',
        'starting_number' => 1,
        'current_number' => 0,
        'year' => 2026,
        'is_active' => true,
        'allow_manual' => false,
        'module' => 'sales',
    ]);

    NumberSeriesLine::query()->create([
        'number_series_id' => $series->id,
        'starting_date' => '2026-01-01',
        'starting_no' => 0,
        'ending_no' => null,
        'increment_by' => 1,
        'last_no_used' => 0,
        'no_of_digits' => 6,
        'prefix' => 'SCM-',
        'suffix' => '',
        'blocked' => false,
    ]);
}

function salesCreditMemoCreationNumberSeriesLine(): NumberSeriesLine
{
    return NumberSeriesLine::query()
        ->whereHas('series', fn ($query) => $query->where('code', 'S-CM'))
        ->firstOrFail();
}

/**
 * @return array<string, mixed>
 */
function salesCreditMemoCreationPayload(): array
{
    $customer = Customer::factory()->create();
    $item = Item::factory()->create([
        'item_type' => ItemType::FINISHED_GOOD,
        'unit_price' => 100,
    ]);

    return [
        'customer_id' => $customer->id,
        'sales_invoice_id' => null,
        'effective_date' => now()->toDateString(),
        'reason' => 'Customer return',
        'currency_code' => 'NGN',
        'items' => [[
            'item_id' => $item->id,
            'description' => $item->description,
            'quantity' => 2,
            'unit_price' => 100,
            'vat_percent' => 7.5,
            'unit_of_measure_code' => $item->base_unit_of_measure,
            'qty_per_unit_of_measure' => 1,
        ]],
    ];
}
