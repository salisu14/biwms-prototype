<?php

declare(strict_types=1);

use App\Data\Sales\SalesCreditMemoData;
use App\Data\Sales\SalesInvoiceData;
use App\Enums\ApprovalStatus;
use App\Enums\ItemType;
use App\Enums\SalesLinePricingStatus;
use App\Enums\SalesPriceSource;
use App\Exceptions\BusinessException;
use App\Filament\Resources\SalesCreditMemos\Pages\EditSalesCreditMemo;
use App\Filament\Resources\SalesCreditMemos\Pages\ViewSalesCreditMemo;
use App\Filament\Resources\SalesCreditMemos\RelationManagers\ItemsRelationManager;
use App\Filament\Resources\SalesInvoices\Pages\CreateSalesInvoice;
use App\Models\Customer;
use App\Models\Item;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\PostedSalesInvoice;
use App\Models\Role;
use App\Models\SalesCreditMemo;
use App\Models\SalesCreditMemoLine;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;
use App\Models\SalesOrder;
use App\Models\SalesPrice;
use App\Models\User;
use App\Services\Sales\SalesCreditMemoPricingGuard;
use App\Services\Sales\SalesCreditMemoService;
use App\Services\Sales\SalesInvoicePricingGuard;
use App\Services\Sales\SalesInvoiceService;
use App\Services\Sales\SalesOrderService;
use App\Services\Sales\SalesPricingResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function directPricingUser(): User
{
    $role = Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $user = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);

    $user->assignRole($role);
    auth()->login($user);

    return $user;
}

function directPricingNumberSeries(string $code, string $prefix): void
{
    $series = NumberSeries::query()->create([
        'code' => $code,
        'description' => "{$code} test series",
        'prefix' => $prefix,
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
        'prefix' => $prefix,
        'suffix' => '',
        'blocked' => false,
    ]);
}

function directPricingItem(): Item
{
    return Item::factory()->create([
        'item_type' => ItemType::FINISHED_GOOD,
        'unit_price' => 300000,
    ]);
}

function directPricingTag(Item $item, ?Customer $customer, string $currency, float $price, ?string $uom = null): SalesPrice
{
    return SalesPrice::query()->create([
        'item_id' => $item->id,
        'customer_id' => $customer?->id,
        'currency_code' => $currency,
        'price' => $price,
        'source' => SalesPriceSource::NEGOTIATED,
        'unit_of_measure_code' => $uom,
        'is_active' => true,
    ]);
}

function directPricingMemo(Customer $customer, string $currency, mixed $factor, string $number, string $status = 'draft'): SalesCreditMemo
{
    return SalesCreditMemo::query()->create([
        'memo_number' => $number,
        'customer_id' => $customer->id,
        'status' => $status,
        'effective_date' => now()->toDateString(),
        'currency_code' => $currency,
        'currency_factor' => $factor,
        'total_amount' => 0,
    ]);
}

function directPricingInvoice(Customer $customer, string $currency, mixed $factor, array $lines): SalesInvoice
{
    return app(SalesInvoiceService::class)->create(new SalesInvoiceData(
        customer_id: $customer->id,
        sales_order_id: null,
        invoice_date: now()->toDateString(),
        due_date: now()->addDays(30)->toDateString(),
        currency_code: $currency,
        lines: $lines,
        currency_factor: (string) $factor,
    ));
}

// ===========================================================================
// A. Direct Sales Invoice: trusted FCY price resolved + persisted
// ===========================================================================

it('prices a direct USD invoice line from the negotiated SalesPrice and persists provenance', function (): void {
    $user = directPricingUser();
    directPricingNumberSeries('S-INV', 'SINV-');

    $customer = Customer::factory()->create();
    $item = directPricingItem();
    $tag = directPricingTag($item, $customer, 'USD', 220, 'PCS');

    Livewire::actingAs($user)
        ->test(CreateSalesInvoice::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'sales_order_id' => null,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency_code' => 'USD',
            'currency_factor' => 1500,
            'lines' => [[
                'item_id' => null,
                'description' => $item->description,
                'quantity' => 1,
                'unit_of_measure' => 'PCS',
                'unit_price' => 0,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'vat_percent' => 0,
                'line_total' => 0,
            ]],
        ])
        ->set('data.lines.0.item_id', $item->id)
        ->call('create')
        ->assertHasNoFormErrors();

    $line = SalesInvoiceLine::query()->latest('id')->firstOrFail();

    expect((float) $line->unit_price)->toBe(220.0)
        ->and($line->pricing_status)->toBe(SalesLinePricingStatus::RESOLVED)
        ->and($line->price_source)->toBe(SalesPricingResolver::SOURCE_SALES_PRICE_CUSTOMER)
        ->and((int) $line->price_record_id)->toBe($tag->id)
        ->and((float) $line->unit_price)->not->toBe(300000.0)
        ->and((float) $line->unit_price)->not->toBe(200.0);
});

// ===========================================================================
// B. Direct Sales Invoice: no trusted FCY price -> UNRESOLVED + post blocked
// ===========================================================================

it('marks a direct USD invoice line unresolved and blocks posting when no USD price exists', function (): void {
    $user = directPricingUser();
    directPricingNumberSeries('S-INV', 'SINV-');

    $customer = Customer::factory()->create();
    $item = directPricingItem();

    Livewire::actingAs($user)
        ->test(CreateSalesInvoice::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'sales_order_id' => null,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency_code' => 'USD',
            'currency_factor' => 1500,
            'lines' => [[
                'item_id' => null,
                'description' => $item->description,
                'quantity' => 1,
                'unit_of_measure' => 'PCS',
                'unit_price' => 0,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'vat_percent' => 0,
                'line_total' => 0,
            ]],
        ])
        ->set('data.lines.0.item_id', $item->id)
        ->call('create')
        ->assertHasNoFormErrors();

    $line = SalesInvoiceLine::query()->latest('id')->firstOrFail();

    expect((float) $line->unit_price)->toBe(0.0)
        ->and($line->pricing_status)->toBe(SalesLinePricingStatus::UNRESOLVED)
        ->and($line->price_source)->toBe(SalesPricingResolver::SOURCE_MANUAL_REQUIRED)
        ->and($line->price_record_id)->toBeNull()
        ->and((float) $line->unit_price)->not->toBe(300000.0);

    $invoice = SalesInvoice::query()->with('lines')->latest('id')->firstOrFail();
    $invoice->forceFill(['status' => ApprovalStatus::APPROVED])->saveQuietly();

    expect(fn () => app(SalesInvoiceService::class)->post($invoice->fresh()))
        ->toThrow(BusinessException::class, 'unresolved pricing');
});

// ===========================================================================
// C. Direct Sales Invoice: explicit manual price clears the block
// ===========================================================================

it('treats an explicitly entered USD invoice price as manual and clears automatic provenance', function (): void {
    $user = directPricingUser();
    directPricingNumberSeries('S-INV', 'SINV-');

    $customer = Customer::factory()->create();
    $item = directPricingItem();
    // A trusted USD price exists, but the user overrides it manually.
    directPricingTag($item, $customer, 'USD', 220, 'PCS');

    Livewire::actingAs($user)
        ->test(CreateSalesInvoice::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'sales_order_id' => null,
            'invoice_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'currency_code' => 'USD',
            'currency_factor' => 1500,
            'lines' => [[
                'item_id' => $item->id,
                'description' => $item->description,
                'quantity' => 1,
                'unit_of_measure' => 'PCS',
                'unit_price' => 0,
                'discount_percent' => 0,
                'discount_amount' => 0,
                'vat_percent' => 0,
                'line_total' => 0,
            ]],
        ])
        ->set('data.lines.0.unit_price', 225)
        ->call('create')
        ->assertHasNoFormErrors();

    $line = SalesInvoiceLine::query()->latest('id')->firstOrFail();

    expect((float) $line->unit_price)->toBe(225.0)
        ->and($line->pricing_status)->toBe(SalesLinePricingStatus::MANUAL)
        ->and($line->price_source)->toBeNull()
        ->and($line->pricing_master_id)->toBeNull()
        ->and($line->price_record_id)->toBeNull();

    app(SalesInvoicePricingGuard::class)->assertCanPost($line->salesInvoice->fresh('lines'));

    expect(true)->toBeTrue();
});

// ===========================================================================
// D. Direct Sales Invoice: hydration / unrelated edits do not reprice
// ===========================================================================

it('does not reprice a direct invoice line on hydration or unrelated edits', function (): void {
    directPricingUser();
    directPricingNumberSeries('S-INV', 'SINV-');

    $customer = Customer::factory()->create();
    $item = directPricingItem();

    $invoice = directPricingInvoice($customer, 'NGN', 1, [[
        'item_id' => $item->id,
        'description' => $item->description,
        'quantity' => 1,
        'unit_of_measure' => 'PCS',
        'unit_price' => 111,
        'discount_percent' => 0,
        'discount_amount' => 0,
        'vat_percent' => 0,
    ]]);

    $line = $invoice->lines()->firstOrFail();

    expect((float) SalesInvoiceLine::query()->findOrFail($line->id)->unit_price)->toBe(111.0);

    $line->update(['description' => 'unrelated edit']);
    expect((float) $line->fresh()->unit_price)->toBe(111.0);

    $line->update(['quantity' => 4]);
    expect((float) $line->fresh()->unit_price)->toBe(111.0);
});

// ===========================================================================
// E/F. Credit Memo items relation manager
// ===========================================================================

it('prices a USD credit memo relation-manager line from the negotiated SalesPrice', function (): void {
    $user = directPricingUser();
    $customer = Customer::factory()->create();
    $item = directPricingItem();
    $memo = directPricingMemo($customer, 'USD', 1500, 'SCM-RM-TRUSTED');
    $tag = directPricingTag($item, $customer, 'USD', 220, 'PCS');

    Livewire::actingAs($user)
        ->test(ItemsRelationManager::class, [
            'ownerRecord' => $memo,
            'pageClass' => EditSalesCreditMemo::class,
        ])
        ->callTableAction('create', data: [
            'item_id' => $item->id,
            'quantity' => 1,
            'unit_price' => 0,
            'line_discount_percent' => 0,
            'vat_percent' => 0,
            'unit_of_measure_code' => 'PCS',
        ])
        ->assertHasNoTableActionErrors();

    $line = $memo->fresh()->items()->firstOrFail();

    expect((float) $line->unit_price)->toBe(220.0)
        ->and($line->pricing_status)->toBe(SalesLinePricingStatus::RESOLVED)
        ->and($line->price_source)->toBe(SalesPricingResolver::SOURCE_SALES_PRICE_CUSTOMER)
        ->and((int) $line->price_record_id)->toBe($tag->id)
        ->and((float) $line->unit_price)->not->toBe(300000.0)
        ->and((float) $line->unit_price)->not->toBe(200.0);
});

it('marks a USD credit memo relation-manager line unresolved without relabelling the NGN reference', function (): void {
    $user = directPricingUser();
    $customer = Customer::factory()->create();
    $item = directPricingItem();
    $memo = directPricingMemo($customer, 'USD', 1500, 'SCM-RM-UNRESOLVED');

    Livewire::actingAs($user)
        ->test(ItemsRelationManager::class, [
            'ownerRecord' => $memo,
            'pageClass' => EditSalesCreditMemo::class,
        ])
        ->callTableAction('create', data: [
            'item_id' => $item->id,
            'quantity' => 1,
            'unit_price' => 0,
            'line_discount_percent' => 0,
            'vat_percent' => 0,
            'unit_of_measure_code' => 'PCS',
        ])
        ->assertHasNoTableActionErrors();

    $line = $memo->fresh()->items()->firstOrFail();

    expect((float) $line->unit_price)->toBe(0.0)
        ->and($line->pricing_status)->toBe(SalesLinePricingStatus::UNRESOLVED)
        ->and($line->price_source)->toBe(SalesPricingResolver::SOURCE_MANUAL_REQUIRED)
        ->and($line->price_record_id)->toBeNull()
        ->and((float) $line->unit_price)->not->toBe(300000.0);
});

// ===========================================================================
// G. Direct/unlinked credit memo posting is blocked while unresolved
// ===========================================================================

it('blocks posting a direct USD credit memo with an unresolved line', function (): void {
    $user = directPricingUser();
    $customer = Customer::factory()->create();
    $item = directPricingItem();
    $memo = directPricingMemo($customer, 'USD', 1500, 'SCM-POST-BLOCK', ApprovalStatus::APPROVED->value);

    $memo->items()->create([
        'item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 0,
        'unit_of_measure_code' => 'PCS',
        'pricing_status' => SalesLinePricingStatus::UNRESOLVED,
    ]);

    expect(fn () => app(SalesCreditMemoService::class)->post($memo->fresh()))
        ->toThrow(BusinessException::class, 'unresolved pricing');
});

// ===========================================================================
// H. Explicit manual FCY memo price is allowed
// ===========================================================================

it('allows an explicitly manual positive FCY credit memo price', function (): void {
    $user = directPricingUser();
    $customer = Customer::factory()->create();
    $item = directPricingItem();
    $memo = directPricingMemo($customer, 'USD', 1500, 'SCM-MANUAL');

    Livewire::actingAs($user)
        ->test(ItemsRelationManager::class, [
            'ownerRecord' => $memo,
            'pageClass' => EditSalesCreditMemo::class,
        ])
        ->callTableAction('create', data: [
            'item_id' => $item->id,
            'quantity' => 1,
            'unit_price' => 250,
            'line_discount_percent' => 0,
            'vat_percent' => 0,
            'unit_of_measure_code' => 'PCS',
        ])
        ->assertHasNoTableActionErrors();

    $line = $memo->fresh()->items()->firstOrFail();

    expect((float) $line->unit_price)->toBe(250.0)
        ->and($line->pricing_status)->toBe(SalesLinePricingStatus::MANUAL)
        ->and($line->price_source)->toBeNull()
        ->and($line->price_record_id)->toBeNull();

    app(SalesCreditMemoPricingGuard::class)->assertCanPost($memo->fresh('items'));

    expect(true)->toBeTrue();
});

// ===========================================================================
// I. Linked credit memo preserves posted invoice economics
// ===========================================================================

it('preserves posted invoice economics on a linked credit memo line and does not reprice from current prices', function (): void {
    $user = directPricingUser();
    $customer = Customer::factory()->create();
    $item = directPricingItem();

    $posted = PostedSalesInvoice::query()->create([
        'document_number' => 'S-INV-LINKED-001',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'subtotal' => 1500,
        'total_amount' => 1500,
        'grand_total' => 1500,
        'currency_code' => 'USD',
        'currency_factor' => 1500,
        'amount_paid' => 0,
        'remaining_amount' => 1500,
        'paid_in_full' => false,
        'posted_by' => $user->id,
        'posted_at' => now(),
        'cancelled' => false,
    ]);

    $postedLine = $posted->lines()->create([
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'item_description' => $item->description,
        'posting_date' => now()->toDateString(),
        'quantity' => 10,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => 10,
        'unit_price' => 150,
        'unit_cost' => 10,
        'unit_cost_lcy' => 10,
        'line_total' => 1500,
        'line_amount' => 1500,
        'vat_percentage' => 0,
        'vat_amount' => 0,
        'amount_including_vat' => 1500,
        'cost_amount' => 100,
        'profit_amount' => 1400,
        'line_number' => 10000,
    ]);

    // A current price that must NOT be used for the linked return.
    directPricingTag($item, $customer, 'USD', 999, 'PCS');

    $memo = app(SalesCreditMemoService::class)->create(SalesCreditMemoData::from([
        'customer_id' => $customer->id,
        'sales_invoice_id' => null,
        'posted_sales_invoice_id' => $posted->id,
        'memo_number' => 'SCM-LINKED-001',
        'effective_date' => now()->toDateString(),
        // A linked memo reverses the posted invoice, so the source posted
        // invoice currency/factor are authoritative (Phase 3B2-B2).
        'currency_code' => 'USD',
        'reason' => 'Customer return',
        'currency_factor' => '1500',
        'items' => [[
            'item_id' => $item->id,
            'quantity' => 2,
            'unit_price' => 999,
            'posted_sales_invoice_line_id' => $postedLine->id,
            'unit_of_measure_code' => 'PCS',
        ]],
    ]));

    $line = $memo->fresh()->items()->firstOrFail();

    expect((float) $line->unit_price)->toBe(150.0)
        ->and((float) $line->unit_price)->not->toBe(999.0)
        ->and($line->posted_sales_invoice_line_id)->toBe($postedLine->id)
        ->and($line->price_source)->toBeNull()
        ->and($line->pricing_status)->toBeNull();

    // Linked lines are exempt from the unresolved-pricing guard.
    app(SalesCreditMemoPricingGuard::class)->assertCanPost($memo->fresh('items'));

    expect(true)->toBeTrue();
});

// ===========================================================================
// J. Credit memo relation manager no longer hard-codes NGN document money
// ===========================================================================

it('does not hard-code NGN document money in the credit memo relation manager', function (): void {
    $user = directPricingUser();
    $customer = Customer::factory()->create();
    $item = directPricingItem();
    $memo = directPricingMemo($customer, 'USD', 1500, 'SCM-DISPLAY-USD');

    $memo->items()->create([
        'item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 220,
        'unit_of_measure_code' => 'PCS',
        'vat_percent' => 0,
        'pricing_status' => SalesLinePricingStatus::RESOLVED,
    ]);

    Livewire::actingAs($user)
        ->test(ItemsRelationManager::class, [
            'ownerRecord' => $memo,
            'pageClass' => ViewSalesCreditMemo::class,
        ])
        ->assertSee('220.00')
        ->assertDontSee('₦');
});

// ===========================================================================
// K. SalesOrderService::addLine persists complete resolver provenance
// ===========================================================================

it('persists complete resolver provenance for a resolved line created through SalesOrderService', function (): void {
    directPricingUser();

    // Legacy service path whose line-number collaborator is not defined on the
    // model; provided here so the real addLine body executes.
    Builder::macro('nextLineNumber', fn (): int => 10);

    $customer = Customer::factory()->create();
    $item = directPricingItem();
    $order = SalesOrder::query()->create([
        'order_number' => 'SO-PROV-001',
        'customer_id' => $customer->id,
        'order_date' => now()->toDateString(),
        'status' => 'DRAFT',
        'currency_code' => 'NGN',
        'currency_factor' => 1,
    ]);

    $tag = directPricingTag($item, $customer, 'NGN', 150, 'PCS');

    $line = app(SalesOrderService::class)->addLine($order, $item, 1.0, uom: 'PCS');

    expect($line->pricing_status)->toBe(SalesLinePricingStatus::RESOLVED)
        ->and((int) $line->price_record_id)->toBe($tag->id)
        ->and($line->price_source)->toBe(SalesPricingResolver::SOURCE_SALES_PRICE_CUSTOMER)
        ->and($line->pricing_master_id)->toBeNull();
});

// ===========================================================================
// L. Manual transitions clear stale automatic provenance
// ===========================================================================

it('clears stale automatic provenance when a credit memo line price is edited manually', function (): void {
    $user = directPricingUser();
    $customer = Customer::factory()->create();
    $item = directPricingItem();
    $memo = directPricingMemo($customer, 'USD', 1500, 'SCM-STALE');
    $tag = directPricingTag($item, $customer, 'USD', 220, 'PCS');

    $line = $memo->items()->create([
        'item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 220,
        'unit_of_measure_code' => 'PCS',
        'vat_percent' => 0,
        'pricing_status' => SalesLinePricingStatus::RESOLVED,
        'price_source' => SalesPricingResolver::SOURCE_SALES_PRICE_CUSTOMER,
        'price_record_id' => $tag->id,
    ]);

    Livewire::actingAs($user)
        ->test(ItemsRelationManager::class, [
            'ownerRecord' => $memo,
            'pageClass' => EditSalesCreditMemo::class,
        ])
        ->callTableAction('edit', $line, data: [
            'item_id' => $item->id,
            'quantity' => 1,
            'unit_price' => 99,
            'line_discount_percent' => 0,
            'vat_percent' => 0,
            'unit_of_measure_code' => 'PCS',
        ])
        ->assertHasNoTableActionErrors();

    $fresh = SalesCreditMemoLine::query()->findOrFail($line->id);

    expect((float) $fresh->unit_price)->toBe(99.0)
        ->and($fresh->pricing_status)->toBe(SalesLinePricingStatus::MANUAL)
        ->and($fresh->price_source)->toBeNull()
        ->and($fresh->price_record_id)->toBeNull()
        ->and($fresh->pricing_master_id)->toBeNull();
});
