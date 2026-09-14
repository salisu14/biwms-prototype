<?php

declare(strict_types=1);

use App\Data\Sales\SalesCreditMemoData;
use App\Data\Sales\SalesInvoiceData;
use App\Enums\ApprovalStatus;
use App\Enums\QuoteStatus;
use App\Enums\SalesOrderType;
use App\Exceptions\BusinessException;
use App\Models\BlanketOrder;
use App\Models\Customer;
use App\Models\Item;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\PostedSalesCreditMemo;
use App\Models\PostedSalesInvoice;
use App\Models\SalesCreditMemo;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\SalesQuote;
use App\Models\User;
use App\Services\Sales\SalesCreditMemoService;
use App\Services\Sales\SalesDocumentCurrencyService;
use App\Services\Sales\SalesInvoiceService;
use App\Services\Sales\SalesPricingResolver;
use App\Services\Sales\SalesQuoteService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function salesCurrencyContextNumberSeries(string $code, string $prefix): void
{
    $series = NumberSeries::query()->create([
        'code' => $code,
        'description' => $code.' currency-context test series',
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

function salesCurrencyContextOrder(array $attributes = []): SalesOrder
{
    auth()->login(User::factory()->create());

    return SalesOrder::query()->create(array_merge([
        'order_number' => 'SO-CC-'.uniqid(),
        'order_type' => SalesOrderType::SalesOrder,
        'customer_id' => Customer::factory()->create()->id,
        'order_date' => now()->toDateString(),
        'status' => 'DRAFT',
    ], $attributes));
}

/**
 * Simulate a malformed/legacy row that bypasses the model event, returning its id.
 */
function salesCurrencyContextLegacyOrderId(string $currencyCode, ?string $currencyFactor): int
{
    $user = User::factory()->create();
    $customer = Customer::factory()->create();

    return (int) DB::table('sales_orders')->insertGetId([
        'order_number' => 'SO-L-'.substr(uniqid(), -8),
        'order_type' => 'SALES_ORDER',
        'status' => 'DRAFT',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'order_date' => now()->toDateString(),
        'currency_code' => $currencyCode,
        'currency_factor' => $currencyFactor,
        'created_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function salesCurrencyContextInvoiceData(string $currencyCode, ?string $currencyFactor = null): SalesInvoiceData
{
    $customer = Customer::factory()->create();
    $item = Item::factory()->create();

    return new SalesInvoiceData(
        customer_id: $customer->id,
        sales_order_id: null,
        invoice_date: now()->toDateString(),
        due_date: now()->addDays(30)->toDateString(),
        currency_code: $currencyCode,
        lines: [[
            'item_id' => $item->id,
            'description' => $item->description,
            'quantity' => 1,
            'unit_of_measure' => 'PCS',
            'unit_price' => 100,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'vat_percent' => 0,
        ]],
        currency_factor: $currencyFactor,
    );
}

function salesCurrencyContextMemoData(string $currencyCode, ?string $currencyFactor = null): SalesCreditMemoData
{
    $customer = Customer::factory()->create();
    $item = Item::factory()->create();

    return SalesCreditMemoData::from([
        'customer_id' => $customer->id,
        'sales_invoice_id' => null,
        'posted_sales_invoice_id' => null,
        'memo_number' => 'SCM-CC-'.uniqid(),
        'effective_date' => now()->toDateString(),
        'currency_code' => $currencyCode,
        'currency_factor' => $currencyFactor,
        'reason' => 'Currency context test',
        'items' => [[
            'item_id' => $item->id,
            'quantity' => 1,
            'unit_price' => 100,
            'vat_percent' => 0,
        ]],
    ]);
}

// =========================================================================
// Centralized currency-context helper — new vs existing semantics
// =========================================================================

it('resolves a NEW document missing currency to NGN and requires an explicit foreign factor', function (): void {
    $service = app(SalesDocumentCurrencyService::class);

    expect($service->resolveForNewDocument(null, null))->toBe(['currency_code' => 'NGN', 'currency_factor' => '1.000000'])
        ->and($service->resolveForNewDocument('ngn', 999))->toBe(['currency_code' => 'NGN', 'currency_factor' => '1.000000'])
        ->and($service->resolveForNewDocument('usd', '1500'))->toBe(['currency_code' => 'USD', 'currency_factor' => '1500.000000'])
        // An explicitly supplied parity factor of 1 for a foreign currency is VALID.
        ->and($service->resolveForNewDocument('USD', '1'))->toBe(['currency_code' => 'USD', 'currency_factor' => '1.000000'])
        ->and($service->resolveForNewDocument('USD', 1))->toBe(['currency_code' => 'USD', 'currency_factor' => '1.000000']);

    expect(fn () => $service->resolveForNewDocument('USD', null))->toThrow(BusinessException::class, 'factor');
    expect(fn () => $service->resolveForNewDocument('USD', '0'))->toThrow(BusinessException::class, 'factor');
    expect(fn () => $service->resolveForNewDocument('USD', '-1'))->toThrow(BusinessException::class, 'factor');
});

it('fails closed on an EXISTING document with a missing currency instead of reinterpreting it as NGN', function (): void {
    $service = app(SalesDocumentCurrencyService::class);

    expect(fn () => $service->resolveForExistingDocument(null, null))
        ->toThrow(BusinessException::class, 'authoritative currency');
    expect(fn () => $service->resolveForExistingDocument('  ', null))
        ->toThrow(BusinessException::class, 'authoritative currency');

    // A missing currency must never silently become the local currency.
    expect($service->resolveForExistingDocument('USD', '1'))->toBe(['currency_code' => 'USD', 'currency_factor' => '1.000000']);
    expect(fn () => $service->resolveForExistingDocument('USD', null))->toThrow(BusinessException::class, 'factor');
});

// =========================================================================
// A. Sales Order — new documents
// =========================================================================

it('resolves a local sales order to NGN factor 1', function (): void {
    $order = salesCurrencyContextOrder(['currency_code' => 'NGN']);

    expect($order->currency_code)->toBe('NGN')
        ->and((float) $order->currency_factor)->toBe(1.0);
});

it('never turns a missing sales order currency into USD', function (): void {
    $order = salesCurrencyContextOrder([]);

    expect($order->currency_code)->toBe('NGN')
        ->and($order->currency_code)->not->toBe('USD')
        ->and((float) $order->currency_factor)->toBe(1.0);
});

it('persists an explicit foreign sales order currency and factor', function (): void {
    $order = salesCurrencyContextOrder(['currency_code' => 'USD', 'currency_factor' => '1500']);

    expect($order->currency_code)->toBe('USD')
        ->and((float) $order->currency_factor)->toBe(1500.0);
});

it('allows an explicitly supplied parity factor of 1 on a foreign sales order', function (): void {
    $order = salesCurrencyContextOrder(['currency_code' => 'USD', 'currency_factor' => '1']);

    expect($order->currency_code)->toBe('USD')
        ->and((float) $order->currency_factor)->toBe(1.0);
});

it('fails closed when a foreign sales order has no factor', function (): void {
    expect(fn () => salesCurrencyContextOrder(['currency_code' => 'USD']))
        ->toThrow(BusinessException::class, 'factor');
});

it('fails closed when a foreign sales order factor is zero or negative', function (string $factor): void {
    expect(fn () => salesCurrencyContextOrder(['currency_code' => 'USD', 'currency_factor' => $factor]))
        ->toThrow(BusinessException::class, 'factor');
})->with(['zero' => ['0'], 'negative' => ['-1']]);

// =========================================================================
// A. Sales Order — existing documents and legacy safety
// =========================================================================

it('fails closed when changing a local sales order to foreign currency without a factor', function (): void {
    $order = salesCurrencyContextOrder(['currency_code' => 'NGN']);

    expect(fn () => $order->update(['currency_code' => 'USD', 'currency_factor' => null]))
        ->toThrow(BusinessException::class, 'factor');

    expect($order->fresh()->currency_code)->toBe('NGN');
});

it('does not carry a local factor of 1 onto a newly foreign sales order', function (): void {
    $order = salesCurrencyContextOrder(['currency_code' => 'NGN']);

    // Factor is not part of the change, so the previous LCY factor must not be
    // silently blessed as foreign parity.
    expect(fn () => $order->update(['currency_code' => 'USD']))
        ->toThrow(BusinessException::class, 'factor');

    expect($order->fresh()->currency_code)->toBe('NGN');
});

it('resolves factor 1 when changing a foreign sales order back to local currency', function (): void {
    $order = salesCurrencyContextOrder(['currency_code' => 'USD', 'currency_factor' => '1500']);

    $order->update(['currency_code' => 'NGN']);

    expect($order->fresh()->currency_code)->toBe('NGN')
        ->and((float) $order->fresh()->currency_factor)->toBe(1.0);
});

it('does not reinterpret or block an unrelated edit of a legacy foreign order with a NULL factor', function (): void {
    $orderId = salesCurrencyContextLegacyOrderId('USD', null);
    $order = SalesOrder::query()->findOrFail($orderId);

    $order->update(['internal_comment' => 'legacy untouched']);

    $fresh = SalesOrder::query()->findOrFail($orderId);
    expect($fresh->currency_code)->toBe('USD')
        ->and($fresh->currency_factor)->toBeNull()
        ->and($fresh->internal_comment)->toBe('legacy untouched');
});

it('does not reinterpret or block an unrelated edit of a legacy order with blank currency', function (): void {
    $orderId = salesCurrencyContextLegacyOrderId('', null);
    $order = SalesOrder::query()->findOrFail($orderId);

    $order->update(['internal_comment' => 'still blank']);

    $fresh = SalesOrder::query()->findOrFail($orderId);
    expect($fresh->currency_code)->toBe('')
        ->and($fresh->currency_code)->not->toBe('NGN')
        ->and($fresh->internal_comment)->toBe('still blank');
});

// =========================================================================
// A. Sales Order — raw / database default behavior
// =========================================================================

it('fails a raw sales order insert that omits currency_code instead of becoming USD', function (): void {
    $user = User::factory()->create();
    $customer = Customer::factory()->create();

    $insert = fn (): bool => DB::transaction(fn (): bool => DB::table('sales_orders')->insert([
        'order_number' => 'SO-NO-CURRENCY',
        'order_type' => 'SALES_ORDER',
        'status' => 'DRAFT',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'order_date' => now()->toDateString(),
        'created_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]));

    expect($insert)->toThrow(QueryException::class)
        ->and(DB::table('sales_orders')->where('order_number', 'SO-NO-CURRENCY')->exists())->toBeFalse();
});

it('does not fabricate a factor for a raw foreign sales order insert', function (): void {
    $orderId = salesCurrencyContextLegacyOrderId('USD', null);

    expect(DB::table('sales_orders')->where('id', $orderId)->value('currency_factor'))->toBeNull();
});

// =========================================================================
// B. Quote conversion
// =========================================================================

function salesCurrencyContextQuote(): SalesQuote
{
    $customer = Customer::factory()->create();
    $item = Item::factory()->create();

    $quote = SalesQuote::query()->create([
        'quote_no' => 'SQ-CC-'.uniqid(),
        'customer_id' => $customer->id,
        'quote_date' => now()->toDateString(),
        'valid_until' => now()->addDays(30)->toDateString(),
        'status' => QuoteStatus::ACCEPTED,
        'approval_status' => 'approved',
        'total_amount' => 0,
    ]);

    $quote->items()->create([
        'item_id' => $item->id,
        'quantity' => 2,
        'unit_price' => 100,
        'discount' => 0,
    ]);

    return $quote;
}

it('converts a quote to a safe NGN order instead of defaulting to USD', function (): void {
    auth()->login(User::factory()->create());
    salesCurrencyContextNumberSeries('S-ORD', 'SO-');
    $order = app(SalesQuoteService::class)->convertToOrder(salesCurrencyContextQuote());

    expect($order->currency_code)->toBe('NGN')
        ->and($order->currency_code)->not->toBe('USD')
        ->and((float) $order->currency_factor)->toBe(1.0);
});

// =========================================================================
// C. Blanket order conversion
// =========================================================================

function salesCurrencyContextBlanketOrder(array $attributes = []): BlanketOrder
{
    $user = User::factory()->create();
    auth()->login($user);

    return BlanketOrder::query()->create(array_merge([
        'document_number' => 'BSO-'.uniqid(),
        'order_type' => 'Sales',
        'status' => 'ACTIVE',
        'released' => true,
        'order_date' => '2026-09-13',
        'location_code' => null,
        'created_by' => $user->id,
        'currency_code' => null,
        'exchange_rate' => null,
        'customer_id' => Customer::factory()->create()->id,
    ], $attributes));
}

it('converts a local sales blanket order to an NGN order with factor 1', function (): void {
    salesCurrencyContextNumberSeries('S-ORD', 'SO-');
    $blanket = salesCurrencyContextBlanketOrder(['currency_code' => null]);

    $order = $blanket->createSalesOrder();

    expect($order->currency_code)->toBe('NGN')
        ->and((float) $order->currency_factor)->toBe(1.0);
});

it('inherits an explicit foreign blanket rate without fabricating one', function (): void {
    salesCurrencyContextNumberSeries('S-ORD', 'SO-');
    $blanket = salesCurrencyContextBlanketOrder(['currency_code' => 'USD', 'exchange_rate' => 1500]);

    $order = $blanket->createSalesOrder();

    expect($order->currency_code)->toBe('USD')
        ->and((float) $order->currency_factor)->toBe(1500.0);
});

it('fails closed when a foreign sales blanket order has no valid rate', function (): void {
    salesCurrencyContextNumberSeries('S-ORD', 'SO-');
    $blanket = salesCurrencyContextBlanketOrder(['currency_code' => 'USD', 'exchange_rate' => null]);
    $before = SalesOrder::query()->count();

    expect(fn () => $blanket->createSalesOrder())->toThrow(BusinessException::class, 'factor');
    expect(SalesOrder::query()->count())->toBe($before);
});

// =========================================================================
// D. Sales Invoice
// =========================================================================

it('creates a local sales invoice draft with factor 1', function (): void {
    $this->actingAs(User::factory()->create());
    salesCurrencyContextNumberSeries('S-INV', 'SINV-');

    $invoice = app(SalesInvoiceService::class)->create(salesCurrencyContextInvoiceData('NGN'));

    expect($invoice->currency_code)->toBe('NGN')
        ->and((float) $invoice->currency_factor)->toBe(1.0);
});

it('creates a foreign sales invoice draft preserving the explicit factor', function (): void {
    $this->actingAs(User::factory()->create());
    salesCurrencyContextNumberSeries('S-INV', 'SINV-');

    $invoice = app(SalesInvoiceService::class)->create(salesCurrencyContextInvoiceData('USD', '1500'));

    expect($invoice->currency_code)->toBe('USD')
        ->and((float) $invoice->currency_factor)->toBe(1500.0);
});

it('fails closed when creating a foreign sales invoice without a factor', function (): void {
    $this->actingAs(User::factory()->create());
    salesCurrencyContextNumberSeries('S-INV', 'SINV-');

    expect(fn () => app(SalesInvoiceService::class)->create(salesCurrencyContextInvoiceData('USD', null)))
        ->toThrow(BusinessException::class, 'factor');

    expect(SalesInvoice::query()->count())->toBe(0);
});

it('fails closed when posting a foreign sales invoice without a factor', function (): void {
    $customer = Customer::factory()->create();
    $item = Item::factory()->create();

    $invoice = SalesInvoice::query()->create([
        'invoice_number' => 'SI-CC-USD-NOF',
        'customer_id' => $customer->id,
        'status' => ApprovalStatus::APPROVED,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'currency_code' => 'USD',
        'currency_factor' => null,
        'total_amount' => 100,
    ]);

    $invoice->lines()->create([
        'item_id' => $item->id,
        'description' => $item->description,
        'quantity' => 1,
        'unit_of_measure' => 'PCS',
        'unit_price' => 100,
        'discount_percent' => 0,
        'discount_amount' => 0,
        'vat_percent' => 0,
        'vat_amount' => 0,
        'line_total' => 100,
    ]);

    expect(fn () => app(SalesInvoiceService::class)->post($invoice))
        ->toThrow(BusinessException::class, 'factor');

    expect(PostedSalesInvoice::query()->count())->toBe(0);
});

// =========================================================================
// E. Sales Credit Memo
// =========================================================================

it('creates a local sales credit memo with factor 1', function (): void {
    $this->actingAs(User::factory()->create());

    $memo = app(SalesCreditMemoService::class)->create(salesCurrencyContextMemoData('NGN'));

    expect($memo->currency_code)->toBe('NGN')
        ->and((float) $memo->currency_factor)->toBe(1.0);
});

it('creates a foreign sales credit memo preserving the explicit factor', function (): void {
    $this->actingAs(User::factory()->create());

    $memo = app(SalesCreditMemoService::class)->create(salesCurrencyContextMemoData('USD', '1500'));

    expect($memo->currency_code)->toBe('USD')
        ->and((float) $memo->currency_factor)->toBe(1500.0);
});

it('fails closed when creating a foreign sales credit memo without a factor', function (): void {
    $this->actingAs(User::factory()->create());

    expect(fn () => app(SalesCreditMemoService::class)->create(salesCurrencyContextMemoData('USD', null)))
        ->toThrow(BusinessException::class, 'factor');

    expect(SalesCreditMemo::query()->count())->toBe(0);
});

it('fails closed when posting a foreign sales credit memo without a factor', function (): void {
    $user = User::factory()->create();
    Role::findOrCreate('super_admin', 'web');
    $user->assignRole('super_admin');
    $this->actingAs($user);

    $customer = Customer::factory()->create();
    $item = Item::factory()->create();

    $memo = SalesCreditMemo::query()->create([
        'memo_number' => 'SCM-CC-USD-NOF',
        'customer_id' => $customer->id,
        'status' => ApprovalStatus::APPROVED,
        'effective_date' => now()->toDateString(),
        'currency_code' => 'USD',
        'currency_factor' => null,
        'total_amount' => 100,
    ]);

    $memo->items()->create([
        'item_id' => $item->id,
        'quantity' => 1,
        'unit_price' => 100,
        'vat_percent' => 0,
        'unit_of_measure_code' => $item->base_unit_of_measure,
    ]);

    expect(fn () => app(SalesCreditMemoService::class)->post($memo))
        ->toThrow(BusinessException::class, 'factor');

    expect(PostedSalesCreditMemo::query()->count())->toBe(0);
});

// =========================================================================
// F. Pricing currency label
// =========================================================================

it('labels item-card sales prices with the configured company currency, not USD', function (): void {
    $item = Item::factory()->create(['unit_price' => 300000]);
    $customer = Customer::factory()->create();

    $pricing = app(SalesPricingResolver::class)->resolve(
        item: $item,
        customer: $customer,
        quantity: 1,
        documentCurrency: 'NGN',
    );

    expect($pricing['currency'])->toBe('NGN')
        ->and($pricing['currency'])->not->toBe('USD');
});
