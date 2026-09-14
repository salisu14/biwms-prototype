<?php

declare(strict_types=1);

use App\Enums\SalesPriceSource;
use App\Models\Customer;
use App\Models\CustomerGroup;
use App\Models\CustomerPriceOverride;
use App\Models\Item;
use App\Models\SalesOrder;
use App\Models\SalesPrice;
use App\Models\User;
use App\Services\Sales\SalesPricingResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function spProvenanceItem(array $overrides = []): Item
{
    return Item::factory()->create(array_merge(['unit_price' => 300000], $overrides));
}

function spProvenanceTag(array $attributes): SalesPrice
{
    return SalesPrice::query()->create(array_merge([
        'currency_code' => 'USD',
        'price' => 220,
        'source' => SalesPriceSource::NEGOTIATED,
        'is_active' => true,
    ], $attributes));
}

// ---------------------------------------------------------------------------
// A. Negotiated price is authoritative over the LCY reference
// ---------------------------------------------------------------------------

it('selects the negotiated USD price over the NGN item reference on a USD document', function (): void {
    $item = spProvenanceItem(); // NGN 300,000 reference
    $customer = Customer::factory()->create();

    spProvenanceTag([
        'item_id' => $item->id,
        'customer_id' => $customer->id,
        'price' => 220,
    ]);

    $result = app(SalesPricingResolver::class)->resolve(
        item: $item,
        customer: $customer,
        quantity: 1.0,
        documentCurrency: 'USD',
    );

    expect($result['unit_price'])->toBe(220.0)
        ->and($result['currency'])->toBe('USD')
        ->and($result['price_source'])->toBe(SalesPricingResolver::SOURCE_SALES_PRICE_CUSTOMER)
        ->and($result['is_reference_derived'])->toBeFalse()
        ->and($result['requires_manual_price'])->toBeFalse()
        // Not the converted NGN reference (300,000 / 1,500 = 200)...
        ->and($result['unit_price'])->not->toBe(200.0)
        // ...and not the raw NGN reference number relabelled as USD.
        ->and($result['unit_price'])->not->toBe(300000.0);
});

// ---------------------------------------------------------------------------
// B & C. Scope hierarchy
// ---------------------------------------------------------------------------

it('prefers a customer-specific price over a customer-group price', function (): void {
    $group = CustomerGroup::query()->create(['code' => 'SP-G1', 'name' => 'Provenance Group']);
    $customer = Customer::factory()->create();
    $customer->forceFill(['customer_group_id' => $group->id])->save();

    $item = spProvenanceItem();

    spProvenanceTag([
        'item_id' => $item->id,
        'customer_id' => $customer->id,
        'price' => 100,
    ]);

    spProvenanceTag([
        'item_id' => $item->id,
        'customer_id' => null,
        'customer_group_id' => $group->id,
        'price' => 150,
    ]);

    $result = app(SalesPricingResolver::class)->resolve($item, $customer->fresh(), 1.0, documentCurrency: 'USD');

    expect($result['unit_price'])->toBe(100.0)
        ->and($result['price_source'])->toBe(SalesPricingResolver::SOURCE_SALES_PRICE_CUSTOMER);
});

it('prefers a customer-group price over a general price', function (): void {
    $group = CustomerGroup::query()->create(['code' => 'SP-G2', 'name' => 'Provenance Group 2']);
    $customer = Customer::factory()->create();
    $customer->forceFill(['customer_group_id' => $group->id])->save();

    $item = spProvenanceItem();

    spProvenanceTag([
        'item_id' => $item->id,
        'customer_id' => null,
        'customer_group_id' => $group->id,
        'price' => 150,
    ]);

    spProvenanceTag([
        'item_id' => $item->id,
        'customer_id' => null,
        'customer_group_id' => null,
        'price' => 200,
    ]);

    $result = app(SalesPricingResolver::class)->resolve($item, $customer->fresh(), 1.0, documentCurrency: 'USD');

    expect($result['unit_price'])->toBe(150.0)
        ->and($result['price_source'])->toBe(SalesPricingResolver::SOURCE_SALES_PRICE_GROUP);
});

// ---------------------------------------------------------------------------
// D, E, F. Active / effective-date rules
// ---------------------------------------------------------------------------

it('ignores an inactive price', function (): void {
    $item = spProvenanceItem();
    $customer = Customer::factory()->create();

    spProvenanceTag([
        'item_id' => $item->id,
        'customer_id' => null,
        'customer_group_id' => null,
        'price' => 50,
        'is_active' => false,
    ]);

    spProvenanceTag([
        'item_id' => $item->id,
        'customer_id' => null,
        'customer_group_id' => null,
        'price' => 200,
    ]);

    $result = app(SalesPricingResolver::class)->resolve($item, $customer, 1.0, documentCurrency: 'USD');

    expect($result['unit_price'])->toBe(200.0);
});

it('ignores a price whose effective date is in the future', function (): void {
    $item = spProvenanceItem();
    $customer = Customer::factory()->create();

    spProvenanceTag([
        'item_id' => $item->id,
        'customer_id' => null,
        'customer_group_id' => null,
        'price' => 50,
        'effective_from' => now()->addDay()->toDateString(),
    ]);

    spProvenanceTag([
        'item_id' => $item->id,
        'customer_id' => null,
        'customer_group_id' => null,
        'price' => 200,
        'effective_from' => now()->subDay()->toDateString(),
    ]);

    $result = app(SalesPricingResolver::class)->resolve($item, $customer, 1.0, documentCurrency: 'USD');

    expect($result['unit_price'])->toBe(200.0);
});

it('ignores an expired price', function (): void {
    $item = spProvenanceItem();
    $customer = Customer::factory()->create();

    spProvenanceTag([
        'item_id' => $item->id,
        'customer_id' => null,
        'customer_group_id' => null,
        'price' => 50,
        'effective_to' => now()->subDay()->toDateString(),
    ]);

    spProvenanceTag([
        'item_id' => $item->id,
        'customer_id' => null,
        'customer_group_id' => null,
        'price' => 200,
    ]);

    $result = app(SalesPricingResolver::class)->resolve($item, $customer, 1.0, documentCurrency: 'USD');

    expect($result['unit_price'])->toBe(200.0);
});

// ---------------------------------------------------------------------------
// G. Wrong-currency explicit price is never relabelled or converted
// ---------------------------------------------------------------------------

it('does not relabel or convert a different-currency explicit price', function (): void {
    $item = spProvenanceItem(); // NGN 300,000 reference
    $customer = Customer::factory()->create();

    spProvenanceTag([
        'item_id' => $item->id,
        'customer_id' => $customer->id,
        'currency_code' => 'EUR',
        'price' => 200,
    ]);

    $result = app(SalesPricingResolver::class)->resolve($item, $customer, 1.0, documentCurrency: 'USD');

    expect($result['currency'])->toBe('USD')
        ->and($result['unit_price'])->toBe(0.0)
        ->and($result['requires_manual_price'])->toBeTrue()
        ->and($result['is_reference_derived'])->toBeFalse()
        ->and($result['price_source'])->toBe(SalesPricingResolver::SOURCE_MANUAL_REQUIRED)
        ->and($result['unit_price'])->not->toBe(200.0)
        ->and($result['unit_price'])->not->toBe(300000.0);
});

// ---------------------------------------------------------------------------
// H. Ambiguous legacy price: excluded for FCY, preserved for LCY
// ---------------------------------------------------------------------------

it('does not trust the ambiguous legacy customer override for a foreign document but preserves it for NGN', function (): void {
    $item = spProvenanceItem();
    $customer = Customer::factory()->create();

    CustomerPriceOverride::query()->create([
        'customer_id' => $customer->id,
        'item_id' => $item->id,
        'override_price' => 100,
    ]);

    $usd = app(SalesPricingResolver::class)->resolve($item, $customer, 1.0, documentCurrency: 'USD');

    expect($usd['unit_price'])->toBe(0.0)
        ->and($usd['requires_manual_price'])->toBeTrue()
        ->and($usd['price_source'])->toBe(SalesPricingResolver::SOURCE_MANUAL_REQUIRED);

    $ngn = app(SalesPricingResolver::class)->resolve($item, $customer, 1.0, documentCurrency: 'NGN');

    expect($ngn['unit_price'])->toBe(100.0)
        ->and($ngn['currency'])->toBe('NGN')
        ->and($ngn['price_source'])->toBe(SalesPricingResolver::SOURCE_CUSTOMER_OVERRIDE);
});

// ---------------------------------------------------------------------------
// I. NGN documents keep safe NGN reference pricing
// ---------------------------------------------------------------------------

it('still offers the NGN item reference as a reference-derived price for an NGN document', function (): void {
    $item = spProvenanceItem(); // NGN 300,000
    $customer = Customer::factory()->create();

    $result = app(SalesPricingResolver::class)->resolve($item, $customer, 1.0, documentCurrency: 'NGN');

    expect($result['unit_price'])->toBe(300000.0)
        ->and($result['currency'])->toBe('NGN')
        ->and($result['price_source'])->toBe(SalesPricingResolver::SOURCE_ITEM_CARD)
        ->and($result['is_reference_derived'])->toBeTrue()
        ->and($result['requires_manual_price'])->toBeFalse();
});

// ---------------------------------------------------------------------------
// J. Explicit foreign factor 1 stays valid when the price provenance matches
// ---------------------------------------------------------------------------

it('uses an explicit matching-currency price on a USD order with an explicit factor of one and populates no LCY amounts', function (): void {
    auth()->login(User::factory()->create());

    $item = spProvenanceItem(); // NGN 300,000 reference
    $customer = Customer::factory()->create();

    spProvenanceTag([
        'item_id' => $item->id,
        'customer_id' => $customer->id,
        'price' => 220,
        'unit_of_measure_code' => 'PCS',
    ]);

    $order = SalesOrder::query()->create([
        'order_number' => 'SO-SPP-'.substr(uniqid(), -8),
        'customer_id' => $customer->id,
        'order_date' => now()->toDateString(),
        'status' => 'DRAFT',
        'currency_code' => 'USD',
        'currency_factor' => 1,
    ]);

    $order->refresh();

    expect((string) $order->currency_code)->toBe('USD')
        ->and((float) $order->currency_factor)->toBe(1.0);

    $pricing = app(SalesPricingResolver::class)->resolve(
        item: $item,
        customer: $customer,
        quantity: 1.0,
        uom: 'PCS',
        documentCurrency: $order->currency_code,
    );

    expect($pricing['unit_price'])->toBe(220.0)
        ->and($pricing['price_source'])->toBe(SalesPricingResolver::SOURCE_SALES_PRICE_CUSTOMER);

    $line = $order->lines()->create([
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'description' => $item->description,
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'unit_price' => $pricing['unit_price'],
        'price_source' => $pricing['price_source'],
    ]);

    expect((float) $line->unit_price)->toBe(220.0)
        ->and($line->price_source)->toBe(SalesPricingResolver::SOURCE_SALES_PRICE_CUSTOMER)
        // This phase deliberately does not populate Sales LCY monetary fields.
        ->and($line->unit_price_lcy)->toBeNull()
        ->and($line->line_total_lcy)->toBeNull()
        ->and($line->line_amount_lcy)->toBeNull();
});

it('never auto-derives a foreign sales line price from the LCY item reference', function (): void {
    auth()->login(User::factory()->create());

    $item = spProvenanceItem(); // NGN 300,000 item-card reference
    $customer = Customer::factory()->create();

    $usdOrder = SalesOrder::query()->create([
        'order_number' => 'SO-SPP-USD-'.substr(uniqid(), -8),
        'customer_id' => $customer->id,
        'order_date' => now()->toDateString(),
        'status' => 'DRAFT',
        'currency_code' => 'USD',
        'currency_factor' => 1500,
    ]);

    $usdLine = $usdOrder->lines()->create([
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'description' => $item->description,
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'unit_price' => 0,
    ]);

    // The NGN reference must not be fabricated into a USD line price.
    expect((float) $usdLine->unit_price)->toBe(0.0);

    $ngnOrder = SalesOrder::query()->create([
        'order_number' => 'SO-SPP-NGN-'.substr(uniqid(), -8),
        'customer_id' => $customer->id,
        'order_date' => now()->toDateString(),
        'status' => 'DRAFT',
        'currency_code' => 'NGN',
        'currency_factor' => 1,
    ]);

    $ngnLine = $ngnOrder->lines()->create([
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'description' => $item->description,
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'unit_price' => 0,
    ]);

    // LCY reference auto-pricing is preserved for NGN documents.
    expect((float) $ngnLine->unit_price)->toBe(300000.0);
});
