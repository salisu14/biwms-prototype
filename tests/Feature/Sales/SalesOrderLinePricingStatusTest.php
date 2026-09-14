<?php

declare(strict_types=1);

use App\Enums\ItemType;
use App\Enums\SalesLinePricingStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\SalesPriceSource;
use App\Exceptions\BusinessException;
use App\Filament\Resources\SalesOrders\Pages\EditSalesOrder;
use App\Filament\Resources\SalesOrders\RelationManagers\LinesRelationManager;
use App\Models\Customer;
use App\Models\CustomerPriceOverride;
use App\Models\Item;
use App\Models\PriceList;
use App\Models\PricingMaster;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\SalesPrice;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Sales\SalesOrderPricingGuard;
use App\Services\Sales\SalesPricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function salesPricingStatusUser(): User
{
    $user = User::factory()->create();
    Role::findOrCreate('super_admin', 'web');
    $user->assignRole('super_admin');
    auth()->login($user);

    return $user;
}

function salesPricingStatusOrder(Customer $customer, string $currency, mixed $factor): SalesOrder
{
    return SalesOrder::query()->create([
        'order_number' => 'SO-PS-'.strtoupper(substr(uniqid(), -8)),
        'customer_id' => $customer->id,
        'order_date' => now()->toDateString(),
        'status' => SalesOrderStatus::DRAFT,
        'currency_code' => $currency,
        'currency_factor' => $factor,
    ]);
}

function salesPricingStatusItem(): Item
{
    return Item::factory()->create([
        'unit_price' => 300000,
        'item_type' => ItemType::FINISHED_GOOD,
    ]);
}

function salesPricingStatusTag(Item $item, ?Customer $customer, string $currency, float $price, ?string $uom = null): SalesPrice
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

function salesPricingStatusRelationManager(User $user, SalesOrder $order)
{
    return Livewire::actingAs($user)->test(LinesRelationManager::class, [
        'ownerRecord' => $order,
        'pageClass' => EditSalesOrder::class,
    ]);
}

// ---------------------------------------------------------------------------
// A. Relation manager selects the negotiated explicit-currency price
// ---------------------------------------------------------------------------

it('prices a USD sales order line from the negotiated SalesPrice via the lines relation manager', function (): void {
    $user = salesPricingStatusUser();
    $customer = Customer::factory()->create();
    $item = salesPricingStatusItem();
    $order = salesPricingStatusOrder($customer, 'USD', 1500);
    $tag = salesPricingStatusTag($item, $customer, 'USD', 220, 'PCS');

    salesPricingStatusRelationManager($user, $order)
        ->callTableAction('create', data: [
            'item_id' => $item->id,
            'description' => $item->description,
            'quantity' => 1,
            'unit_of_measure_code' => 'PCS',
            'qty_per_unit_of_measure' => 1,
            'unit_price' => 0,
            'line_discount_percent' => 0,
        ])
        ->assertHasNoTableActionErrors();

    $line = $order->fresh()->lines()->firstOrFail();

    expect((float) $line->unit_price)->toBe(220.0)
        ->and($line->pricing_status)->toBe(SalesLinePricingStatus::RESOLVED)
        ->and($line->price_source)->toBe(SalesPricingResolver::SOURCE_SALES_PRICE_CUSTOMER)
        ->and((int) $line->price_record_id)->toBe($tag->id)
        // Not the NGN reference relabelled, and not the converted reference.
        ->and((float) $line->unit_price)->not->toBe(300000.0)
        ->and((float) $line->unit_price)->not->toBe(200.0);
});

// ---------------------------------------------------------------------------
// B. Relation manager with no trusted FCY price -> UNRESOLVED
// ---------------------------------------------------------------------------

it('marks a USD sales order line unresolved when no trusted USD price exists', function (): void {
    $user = salesPricingStatusUser();
    $customer = Customer::factory()->create();
    $item = salesPricingStatusItem(); // NGN 300,000 reference only
    $order = salesPricingStatusOrder($customer, 'USD', 1500);

    salesPricingStatusRelationManager($user, $order)
        ->callTableAction('create', data: [
            'item_id' => $item->id,
            'description' => $item->description,
            'quantity' => 1,
            'unit_of_measure_code' => 'PCS',
            'qty_per_unit_of_measure' => 1,
            'unit_price' => 0,
            'line_discount_percent' => 0,
        ])
        ->assertHasNoTableActionErrors();

    $line = $order->fresh()->lines()->firstOrFail();

    expect((float) $line->unit_price)->toBe(0.0)
        ->and($line->pricing_status)->toBe(SalesLinePricingStatus::UNRESOLVED)
        ->and($line->price_source)->toBe(SalesPricingResolver::SOURCE_MANUAL_REQUIRED)
        ->and($line->price_record_id)->toBeNull()
        ->and((float) $line->unit_price)->not->toBe(300000.0);
});

// ---------------------------------------------------------------------------
// C. Unresolved line blocks approval / shipment / invoicing
// ---------------------------------------------------------------------------

it('blocks approval, shipment and invoicing while a line price is unresolved', function (): void {
    $user = salesPricingStatusUser();
    $customer = Customer::factory()->create();
    $item = salesPricingStatusItem();
    $order = salesPricingStatusOrder($customer, 'USD', 1500);

    $order->lines()->create([
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'description' => $item->description,
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'unit_price' => 0,
    ]);

    $order->refresh()->load('lines');

    // A foreign zero-price line with no provenance fails closed on create.
    expect($order->lines->first()->pricing_status)->toBe(SalesLinePricingStatus::UNRESOLVED);

    expect(fn () => $order->fresh()->markAsReleased())->toThrow(BusinessException::class);
    expect($order->fresh()->status)->toBe(SalesOrderStatus::DRAFT);

    $order->forceFill(['status' => SalesOrderStatus::APPROVED])->saveQuietly();
    expect(fn () => $order->fresh()->postShipment())->toThrow(BusinessException::class);

    $order->forceFill(['status' => SalesOrderStatus::SHIPPED])->saveQuietly();
    expect(fn () => $order->fresh()->postInvoice())->toThrow(BusinessException::class);
});

// ---------------------------------------------------------------------------
// D. Explicit manual price clears the block
// ---------------------------------------------------------------------------

it('treats an explicitly entered foreign price as manual and lets the order progress', function (): void {
    $user = salesPricingStatusUser();
    $customer = Customer::factory()->create();
    $item = salesPricingStatusItem();
    $order = salesPricingStatusOrder($customer, 'USD', 1500);

    $order->lines()->create([
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'description' => $item->description,
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'unit_price' => 250,
        'pricing_status' => SalesLinePricingStatus::MANUAL,
    ]);

    $line = $order->fresh()->lines()->firstOrFail();

    expect($line->pricing_status)->toBe(SalesLinePricingStatus::MANUAL)
        ->and((float) $line->unit_price)->toBe(250.0);

    app(SalesOrderPricingGuard::class)->assertCanProgress($order->fresh());

    $order->fresh()->markAsReleased();

    expect($order->fresh()->status)->toBe(SalesOrderStatus::APPROVED);
});

// ---------------------------------------------------------------------------
// E. Resolved trusted price progresses normally
// ---------------------------------------------------------------------------

it('allows a sales order with a resolved trusted price to progress', function (): void {
    $user = salesPricingStatusUser();
    $customer = Customer::factory()->create();
    $item = salesPricingStatusItem();
    $order = salesPricingStatusOrder($customer, 'USD', 1500);
    $tag = salesPricingStatusTag($item, $customer, 'USD', 220, 'PCS');

    $order->lines()->create([
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'description' => $item->description,
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'unit_price' => 220,
        'price_source' => SalesPricingResolver::SOURCE_SALES_PRICE_CUSTOMER,
        'price_record_id' => $tag->id,
        'pricing_status' => SalesLinePricingStatus::RESOLVED,
    ]);

    app(SalesOrderPricingGuard::class)->assertCanProgress($order->fresh());

    expect($order->fresh()->lines()->first()->pricing_status)->toBe(SalesLinePricingStatus::RESOLVED);
});

// ---------------------------------------------------------------------------
// F. Legacy USD PricingMaster is not trusted for foreign pricing
// ---------------------------------------------------------------------------

it('does not let a legacy USD PricingMaster row drive foreign pricing', function (): void {
    $user = salesPricingStatusUser();
    $customer = Customer::factory()->create();
    $item = salesPricingStatusItem();

    PricingMaster::query()->create([
        'price_list_code' => 'LEG-USD',
        'price_list_type' => 'ALL_CUSTOMERS',
        'item_id' => $item->id,
        'currency_code' => 'USD',
        'price_type' => 'UNIT_PRICE',
        'unit_price' => 150,
        'status' => 'ACTIVE',
        'approved_by' => $user->id,
        'created_by' => (string) $user->id,
        'start_date' => now()->subDay()->toDateString(),
        'is_current_version' => true,
        'minimum_quantity' => 0,
    ]);

    $result = app(SalesPricingResolver::class)->resolve(
        item: $item,
        customer: $customer,
        quantity: 1.0,
        uom: 'PCS',
        documentCurrency: 'USD',
    );

    expect($result['unit_price'])->toBe(0.0)
        ->and($result['requires_manual_price'])->toBeTrue()
        ->and($result['pricing_status'])->toBe(SalesLinePricingStatus::UNRESOLVED->value)
        ->and($result['unit_price'])->not->toBe(150.0);
});

it('still allows an explicit NGN legacy price list for an NGN document', function (): void {
    $user = salesPricingStatusUser();
    $customer = Customer::factory()->create();
    $item = salesPricingStatusItem();

    PricingMaster::query()->create([
        'price_list_code' => 'LEG-NGN',
        'price_list_type' => 'ALL_CUSTOMERS',
        'item_id' => $item->id,
        'currency_code' => 'NGN',
        'price_type' => 'UNIT_PRICE',
        'unit_price' => 150,
        'status' => 'ACTIVE',
        'approved_by' => $user->id,
        'created_by' => (string) $user->id,
        'start_date' => now()->subDay()->toDateString(),
        'is_current_version' => true,
        'minimum_quantity' => 0,
    ]);

    $result = app(SalesPricingResolver::class)->resolve(
        item: $item,
        customer: $customer,
        quantity: 1.0,
        uom: 'PCS',
        documentCurrency: 'NGN',
    );

    expect($result['unit_price'])->toBe(150.0)
        ->and($result['price_source'])->toBe('LEG-NGN');
});

// ---------------------------------------------------------------------------
// G. customer_price_overrides is LCY-only
// ---------------------------------------------------------------------------

it('allows the legacy customer override only for LCY documents', function (): void {
    $customer = Customer::factory()->create();
    $item = salesPricingStatusItem();

    CustomerPriceOverride::query()->create([
        'customer_id' => $customer->id,
        'item_id' => $item->id,
        'override_price' => 100,
    ]);

    $ngn = app(SalesPricingResolver::class)->resolve($item, $customer, 1.0, documentCurrency: 'NGN');
    $usd = app(SalesPricingResolver::class)->resolve($item, $customer, 1.0, documentCurrency: 'USD');

    expect($ngn['unit_price'])->toBe(100.0)
        ->and($ngn['price_source'])->toBe(SalesPricingResolver::SOURCE_CUSTOMER_OVERRIDE);

    expect($usd['unit_price'])->toBe(0.0)
        ->and($usd['requires_manual_price'])->toBeTrue()
        ->and($usd['unit_price'])->not->toBe(100.0);
});

// ---------------------------------------------------------------------------
// H. Missing / blank / invalid document currency fails closed
// ---------------------------------------------------------------------------

it('fails closed when the document currency is missing, blank or invalid', function (string|int|null $currency): void {
    $customer = Customer::factory()->create();
    $item = salesPricingStatusItem();

    expect(fn () => app(SalesPricingResolver::class)->resolve(
        item: $item,
        customer: $customer,
        quantity: 1.0,
        documentCurrency: $currency,
    ))->toThrow(InvalidArgumentException::class);
})->with([
    'missing' => [null],
    'empty' => [''],
    'blank' => ['   '],
    'too short' => ['US'],
    'too long' => ['USDD'],
    'non alpha' => ['U1D'],
]);

// ---------------------------------------------------------------------------
// I. Explicit LCY caller keeps safe NGN pricing
// ---------------------------------------------------------------------------

it('continues to provide safe NGN pricing for an explicitly LCY caller', function (): void {
    $customer = Customer::factory()->create();
    $item = salesPricingStatusItem();

    $result = app(SalesPricingResolver::class)->resolve(
        item: $item,
        customer: $customer,
        quantity: 1.0,
        documentCurrency: 'NGN',
    );

    expect($result['unit_price'])->toBe(300000.0)
        ->and($result['currency'])->toBe('NGN')
        ->and($result['is_reference_derived'])->toBeTrue()
        ->and($result['pricing_status'])->toBe(SalesLinePricingStatus::RESOLVED->value);
});

// ---------------------------------------------------------------------------
// J & K. Historical hydration and manual price preservation
// ---------------------------------------------------------------------------

it('does not reprice a historical line on hydration or unrelated edits', function (): void {
    salesPricingStatusUser();
    $customer = Customer::factory()->create();
    $item = salesPricingStatusItem();
    $order = salesPricingStatusOrder($customer, 'NGN', 1);

    $line = $order->lines()->create([
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'description' => $item->description,
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'unit_price' => 111,
    ]);

    expect((float) SalesOrderLine::query()->findOrFail($line->id)->unit_price)->toBe(111.0);

    $line->update(['comment' => 'unrelated edit']);
    expect((float) $line->fresh()->unit_price)->toBe(111.0);

    $line->update(['quantity' => 5]);
    expect((float) $line->fresh()->unit_price)->toBe(111.0);
});

it('preserves an explicit manual price on unrelated edits', function (): void {
    salesPricingStatusUser();
    $customer = Customer::factory()->create();
    $item = salesPricingStatusItem();
    $order = salesPricingStatusOrder($customer, 'USD', 1500);

    $line = $order->lines()->create([
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'description' => $item->description,
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'unit_price' => 200,
        'pricing_status' => SalesLinePricingStatus::MANUAL,
    ]);

    $line->update(['comment' => 'note', 'quantity' => 3]);

    $fresh = $line->fresh();

    expect((float) $fresh->unit_price)->toBe(200.0)
        ->and($fresh->pricing_status)->toBe(SalesLinePricingStatus::MANUAL);
});

// ---------------------------------------------------------------------------
// L. UOM semantics: matching UOM used directly, base-UOM converted once
// ---------------------------------------------------------------------------

it('uses a matching sales-UOM SalesPrice directly and converts a base-UOM price exactly once', function (): void {
    $customer = Customer::factory()->create();
    $item = salesPricingStatusItem();

    $carton = UnitOfMeasure::query()->create([
        'uom_code' => 'CTN',
        'description' => 'Carton',
    ]);

    $item->uoms()->attach($carton->id, [
        'uom_type' => 'SALES',
        'conversion_factor' => 2,
        'is_default' => true,
    ]);

    // A price recorded in the requested sales UOM is used as-is.
    $matchingUom = salesPricingStatusTag($item, $customer, 'USD', 50, 'CTN');

    $matching = app(SalesPricingResolver::class)->resolve(
        item: $item,
        customer: $customer,
        quantity: 1.0,
        uom: 'CTN',
        documentCurrency: 'USD',
    );

    expect($matching['unit_price'])->toBe(50.0)
        ->and($matching['price_record_id'])->toBe($matchingUom->id);

    // A base-UOM price (no UOM recorded) is converted to the selected UOM once.
    $matchingUom->delete();
    salesPricingStatusTag($item, $customer, 'USD', 50, null);

    $base = app(SalesPricingResolver::class)->resolve(
        item: $item,
        customer: $customer->fresh(),
        quantity: 1.0,
        uom: 'CTN',
        documentCurrency: 'USD',
    );

    expect($base['unit_price'])->toBe(100.0);
});

it('routes a relation-manager UOM through the resolver without deriving from the item reference', function (): void {
    $user = salesPricingStatusUser();
    $customer = Customer::factory()->create();
    $item = salesPricingStatusItem();

    $carton = UnitOfMeasure::query()->create([
        'uom_code' => 'CTN',
        'description' => 'Carton',
    ]);

    $item->uoms()->attach($carton->id, [
        'uom_type' => 'SALES',
        'conversion_factor' => 2,
        'is_default' => true,
    ]);

    $order = salesPricingStatusOrder($customer, 'USD', 1500);
    salesPricingStatusTag($item, $customer, 'USD', 50, 'CTN');

    salesPricingStatusRelationManager($user, $order)
        ->callTableAction('create', data: [
            'item_id' => $item->id,
            'description' => $item->description,
            'quantity' => 1,
            'unit_of_measure_code' => 'CTN',
            'qty_per_unit_of_measure' => 2,
            'unit_price' => 0,
            'line_discount_percent' => 0,
        ])
        ->assertHasNoTableActionErrors();

    $line = $order->fresh()->lines()->firstOrFail();

    expect((float) $line->unit_price)->toBe(50.0)
        ->and((float) $line->unit_price)->not->toBe(100.0)
        ->and((float) $line->unit_price)->not->toBe(600000.0);
});

// ---------------------------------------------------------------------------
// HIGH 4. price_lists is legacy/superseded for runtime pricing
// ---------------------------------------------------------------------------

it('does not use the legacy price_lists table for commercial line pricing', function (): void {
    $customer = Customer::factory()->create();
    $item = salesPricingStatusItem();

    PriceList::query()->create([
        'item_id' => $item->id,
        'customer_id' => $customer->id,
        'price' => 12345,
        'currency' => 'NGN',
        'starting_date' => now()->subDay()->toDateString(),
        'ending_date' => now()->addDay()->toDateString(),
    ]);

    $result = app(SalesPricingResolver::class)->resolve(
        item: $item,
        customer: $customer,
        quantity: 1.0,
        documentCurrency: 'NGN',
    );

    expect($result['unit_price'])->not->toBe(12345.0)
        ->and($result['price_source'])->toBe(SalesPricingResolver::SOURCE_ITEM_CARD);
});
