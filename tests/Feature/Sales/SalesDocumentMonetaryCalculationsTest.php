<?php

declare(strict_types=1);

use App\Data\Sales\SalesCreditMemoData;
use App\Data\Sales\SalesInvoiceData;
use App\Enums\ApprovalStatus;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemType;
use App\Enums\SalesLinePricingStatus;
use App\Enums\SalesOrderStatus;
use App\Enums\SalesPriceSource;
use App\Exceptions\BusinessException;
use App\Filament\Resources\SalesCreditMemos\Pages\CreateSalesCreditMemo;
use App\Models\AccountingPeriod;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerPostingGroup;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralLedgerSetup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemUomAssignment;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\PostedSalesCreditMemo;
use App\Models\PostedSalesInvoice;
use App\Models\SalesCreditMemo;
use App\Models\SalesCreditMemoLine;
use App\Models\SalesInvoice;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;
use App\Models\SalesPrice;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Services\Sales\SalesCreditMemoService;
use App\Services\Sales\SalesDocumentMonetaryCalculator;
use App\Services\Sales\SalesInvoicePricingGuard;
use App\Services\Sales\SalesInvoiceService;
use App\Services\Sales\SalesPricingResolver;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function salesMonetaryUser(): User
{
    $user = User::factory()->create();
    Role::findOrCreate('super_admin', 'web');
    $user->assignRole('super_admin');
    auth()->login($user);

    return $user;
}

function salesMonetaryNumberSeries(string $code, string $prefix, string $module = 'sales'): void
{
    $series = NumberSeries::query()->create([
        'code' => $code,
        'description' => $code.' monetary test series',
        'prefix' => $prefix,
        'starting_number' => 1,
        'current_number' => 0,
        'year' => 2026,
        'is_active' => true,
        'allow_manual' => false,
        'module' => $module,
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

function salesMonetaryItem(float $referencePrice = 300000): Item
{
    return Item::factory()->create([
        'unit_price' => $referencePrice,
        'item_type' => ItemType::FINISHED_GOOD,
    ]);
}

function salesMonetaryOrder(Customer $customer, string $currency, mixed $factor): SalesOrder
{
    return SalesOrder::query()->create([
        'order_number' => 'SO-MON-'.strtoupper(substr(uniqid(), -8)),
        'customer_id' => $customer->id,
        'order_date' => now()->toDateString(),
        'status' => SalesOrderStatus::DRAFT,
        'currency_code' => $currency,
        'currency_factor' => $factor,
    ]);
}

function salesMonetaryTag(Item $item, ?Customer $customer, string $currency, float $price, ?string $uom = null): SalesPrice
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

// ---------------------------------------------------------------------------
// Schema: gross line LCY column and its migration lifecycle
// ---------------------------------------------------------------------------

it('adds a nullable amount_including_vat_lcy column to the sales document lines', function (): void {
    foreach (['sales_order_lines', 'posted_sales_invoice_lines', 'posted_sales_credit_memo_lines'] as $table) {
        expect(Schema::hasColumn($table, 'amount_including_vat_lcy'))
            ->toBeTrue("Expected {$table}.amount_including_vat_lcy to exist.");

        $column = collect(Schema::getColumns($table))->firstWhere('name', 'amount_including_vat_lcy');

        expect($column['nullable'])
            ->toBeTrue("Expected {$table}.amount_including_vat_lcy to be nullable so historical rows stay valid.");
    }
});

it('can roll the gross line LCY column migration down and back up', function (): void {
    /** @var Migration $migration */
    $migration = require database_path('migrations/2026_09_14_210000_add_amount_including_vat_lcy_to_sales_document_lines.php');

    $migration->down();

    foreach (['sales_order_lines', 'posted_sales_invoice_lines', 'posted_sales_credit_memo_lines'] as $table) {
        expect(Schema::hasColumn($table, 'amount_including_vat_lcy'))->toBeFalse();
    }

    $migration->up();

    foreach (['sales_order_lines', 'posted_sales_invoice_lines', 'posted_sales_credit_memo_lines'] as $table) {
        expect(Schema::hasColumn($table, 'amount_including_vat_lcy'))->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// Contract helper: canonical factor, fail-closed and rounding
// ---------------------------------------------------------------------------

it('derives LCY as FCY multiplied by the factor exactly once', function (): void {
    $calculator = app(SalesDocumentMonetaryCalculator::class);

    expect($calculator->deriveLcy('USD', '1500', '220', 4))->toBe('330000.0000')
        ->and($calculator->deriveLcy('NGN', null, '220', 4))->toBe('220.0000')
        ->and($calculator->deriveLcy('USD', '1', '220', 4))->toBe('220.0000');
});

it('fails closed instead of fabricating an LCY value', function (): void {
    $calculator = app(SalesDocumentMonetaryCalculator::class);

    expect($calculator->deriveLcy('USD', null, '220'))->toBeNull()
        ->and($calculator->deriveLcy('USD', '0', '220'))->toBeNull()
        ->and($calculator->deriveLcy('USD', '-1', '220'))->toBeNull()
        ->and($calculator->deriveLcy(null, '1500', '220'))->toBeNull()
        ->and($calculator->deriveLcy('', '1500', '220'))->toBeNull();
});

it('rounds the LCY conversion half-up at the requested document scale', function (): void {
    $calculator = app(SalesDocumentMonetaryCalculator::class);

    expect($calculator->deriveLcy('USD', '1500', '220.1111', 4))->toBe('330166.6500')
        ->and($calculator->deriveLcy('USD', '1500.5', '220.1111', 4))->toBe('330276.7056')
        ->and($calculator->deriveLcy('USD', '1500', '220', 2))->toBe('330000.00');
});

it('returns null for an LCY total only when every contributing line is unresolved', function (): void {
    $calculator = app(SalesDocumentMonetaryCalculator::class);

    expect($calculator->total([null, null], 4))->toBeNull()
        ->and($calculator->total(['330000.0000', null, '1000.0000'], 4))->toBe('331000.0000');
});

// ---------------------------------------------------------------------------
// A. NGN sales order
// ---------------------------------------------------------------------------

it('keeps document and LCY amounts identical for an NGN sales order', function (): void {
    salesMonetaryUser();
    $customer = Customer::factory()->create();
    $item = salesMonetaryItem();
    $order = salesMonetaryOrder($customer, 'NGN', '1');

    $line = $order->lines()->create([
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'description' => $item->description,
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'unit_price' => 300000,
        'line_discount_percent' => 0,
        'vat_percentage' => 0,
    ]);

    $order->refresh()->load('lines');

    expect((float) $line->unit_price_lcy)->toBe(300000.0)
        ->and((float) $line->line_total_lcy)->toBe((float) $line->line_total)
        ->and((float) $line->amount_including_vat_lcy)->toBe((float) $line->amount_including_vat)
        ->and((float) $order->grand_total_lcy)->toBe((float) $order->grand_total)
        ->and((float) $order->total_amount_lcy)->toBe((float) $order->total_amount);
});

// ---------------------------------------------------------------------------
// B. Negotiated USD price with an NGN reference (must not be relabelled)
// ---------------------------------------------------------------------------

it('derives the LCY equivalent of a negotiated USD price without relabelling the NGN reference', function (): void {
    salesMonetaryUser();
    $customer = Customer::factory()->create();
    $item = salesMonetaryItem(300000); // NGN reference: 300,000
    $order = salesMonetaryOrder($customer, 'USD', '1500');

    salesMonetaryTag($item, $customer, 'USD', 220, 'PCS');

    $pricing = app(SalesPricingResolver::class)->resolve(
        item: $item,
        customer: $customer,
        quantity: 1.0,
        uom: 'PCS',
        documentCurrency: 'USD',
    );

    $line = $order->lines()->create([
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'description' => $item->description,
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'unit_price' => $pricing['unit_price'],
        'price_source' => $pricing['price_source'],
        'price_record_id' => $pricing['price_record_id'],
        'pricing_status' => $pricing['pricing_status'],
    ]);

    expect((float) $line->unit_price)->toBe(220.0)
        ->and((float) $line->unit_price_lcy)->toBe(330000.0)
        ->and((float) $line->unit_price)->not->toBe(300000.0)
        ->and((float) $line->unit_price)->not->toBe(200.0)
        ->and((float) $line->unit_price_lcy)->not->toBe(300000.0);
});

// ---------------------------------------------------------------------------
// C. FX factor change: LCY changes, commercial FCY price does not
// ---------------------------------------------------------------------------

it('recomputes LCY equivalents on a rate change without repricing the commercial price', function (): void {
    salesMonetaryUser();
    $customer = Customer::factory()->create();
    $item = salesMonetaryItem();
    $order = salesMonetaryOrder($customer, 'USD', '1500');
    $tag = salesMonetaryTag($item, $customer, 'USD', 220, 'PCS');

    $line = $order->lines()->create([
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

    expect((float) $line->unit_price_lcy)->toBe(330000.0);

    $order->update(['currency_factor' => '1550']);

    $freshLine = $line->fresh();
    $freshOrder = $order->fresh();

    expect((float) $freshLine->unit_price)->toBe(220.0)
        ->and((float) $freshLine->unit_price_lcy)->toBe(341000.0)
        ->and((float) $freshLine->line_amount_lcy)->toBe(341000.0)
        ->and($freshLine->price_source)->toBe(SalesPricingResolver::SOURCE_SALES_PRICE_CUSTOMER)
        ->and($freshLine->price_record_id)->toBe($tag->id)
        ->and($freshLine->pricing_status)->toBe(SalesLinePricingStatus::RESOLVED)
        ->and((float) $freshOrder->grand_total_lcy)->toBe(341000.0);

    // Reclassifying back to local currency must move LCY to the same number,
    // still without touching the commercial price.
    $order->update(['currency_code' => 'NGN']);

    expect((float) $line->fresh()->unit_price)->toBe(220.0)
        ->and((float) $line->fresh()->unit_price_lcy)->toBe(220.0);
});

// ---------------------------------------------------------------------------
// D. Missing foreign factor
// ---------------------------------------------------------------------------

it('leaves LCY unresolved for a legacy foreign document with no factor and never fabricates it', function (): void {
    salesMonetaryUser();
    $customer = Customer::factory()->create();
    $user = User::factory()->create();

    $orderId = (int) DB::table('sales_orders')->insertGetId([
        'order_number' => 'SO-MON-LEGACY',
        'order_type' => 'SALES_ORDER',
        'status' => 'DRAFT',
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'order_date' => now()->toDateString(),
        'currency_code' => 'USD',
        'currency_factor' => null,
        'created_by' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $lineId = (int) DB::table('sales_order_lines')->insertGetId([
        'sales_order_id' => $orderId,
        'line_number' => 10,
        'description' => 'Legacy line',
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => 1,
        'unit_price' => 220,
        'line_total' => 220,
        'line_amount' => 220,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $line = SalesOrderLine::query()->findOrFail($lineId);

    expect($line->unit_price_lcy)->toBeNull()
        ->and($line->line_amount_lcy)->toBeNull();

    $line->update(['comment' => 'legacy untouched']);

    expect($line->fresh()->unit_price_lcy)->toBeNull()
        ->and($line->fresh()->comment)->toBe('legacy untouched')
        ->and((float) $line->fresh()->unit_price)->toBe(220.0);
});

it('fails closed when a new foreign sales order has no factor', function (): void {
    salesMonetaryUser();
    $customer = Customer::factory()->create();

    expect(fn () => salesMonetaryOrder($customer, 'USD', null))
        ->toThrow(BusinessException::class, 'factor');
});

// ---------------------------------------------------------------------------
// E. Header totals
// ---------------------------------------------------------------------------

it('derives header LCY totals as the sum of the rounded line LCY values', function (): void {
    salesMonetaryUser();
    $customer = Customer::factory()->create();
    $item = salesMonetaryItem();
    $order = salesMonetaryOrder($customer, 'USD', '1500');

    $order->lines()->create([
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'description' => 'Line one',
        'quantity' => 2,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'unit_price' => 100,
        'line_discount_percent' => 10,
        'vat_percentage' => 5,
    ]);

    $order->lines()->create([
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'description' => 'Line two',
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'unit_price' => 50.5,
        'line_discount_percent' => 0,
        'vat_percentage' => 5,
    ]);

    $order = $order->fresh()->load('lines');

    $expectedAmountLcy = $order->lines->sum(fn ($line): float => (float) $line->line_amount_lcy);
    $expectedVatLcy = $order->lines->sum(fn ($line): float => (float) $line->vat_amount_lcy);
    $expectedNetLcy = $order->lines->sum(fn ($line): float => (float) $line->amount_including_vat_lcy);

    expect(round((float) $order->total_amount_lcy, 4))->toBe(round($expectedAmountLcy, 4))
        ->and(round((float) $order->total_vat_lcy, 4))->toBe(round($expectedVatLcy, 4))
        ->and(round((float) $order->grand_total_lcy, 4))->toBe(round($expectedNetLcy, 4))
        ->and(round((float) $order->grand_total_lcy, 4))->toBe(round((float) $order->grand_total * 1500, 4));
});

// ---------------------------------------------------------------------------
// F. Order -> Invoice preserves the originating commercial economics
// ---------------------------------------------------------------------------

it('preserves the order currency, factor, FCY price and LCY equivalent on an order-linked invoice', function (): void {
    salesMonetaryUser();
    salesMonetaryNumberSeries('S-INV', 'SINV-');

    $customer = Customer::factory()->create();
    $item = salesMonetaryItem();
    $order = salesMonetaryOrder($customer, 'USD', '1500');

    $order->lines()->create([
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'description' => $item->description,
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'unit_price' => 220,
        'pricing_status' => SalesLinePricingStatus::MANUAL,
    ]);

    // A later, different current price must not be re-resolved for the invoice.
    salesMonetaryTag($item, $customer, 'USD', 999, 'PCS');

    $invoice = app(SalesInvoiceService::class)->create(new SalesInvoiceData(
        customer_id: $customer->id,
        sales_order_id: $order->id,
        invoice_date: now()->toDateString(),
        due_date: now()->addDays(30)->toDateString(),
        currency_code: 'USD',
        currency_factor: '1500',
        lines: [],
    ));

    $invoice->refresh();
    $line = $invoice->lines()->firstOrFail();

    expect($invoice->currency_code)->toBe('USD')
        ->and((float) $invoice->currency_factor)->toBe(1500.0)
        ->and((float) $line->unit_price)->toBe(220.0)
        ->and((float) $line->unit_price)->not->toBe(999.0)
        ->and((float) $line->unit_price_lcy)->toBe(330000.0)
        ->and((float) $invoice->total_amount)->toBe(220.0)
        ->and((float) $invoice->total_amount_lcy)->toBe(330000.0);
});

// ---------------------------------------------------------------------------
// G. Direct sales invoice
// ---------------------------------------------------------------------------

it('converts a direct USD invoice once and keeps unresolved FCY pricing blocked', function (): void {
    salesMonetaryUser();
    salesMonetaryNumberSeries('S-INV', 'SINV-');

    $customer = Customer::factory()->create();
    $item = salesMonetaryItem();

    $invoice = app(SalesInvoiceService::class)->create(new SalesInvoiceData(
        customer_id: $customer->id,
        sales_order_id: null,
        invoice_date: now()->toDateString(),
        due_date: now()->addDays(30)->toDateString(),
        currency_code: 'USD',
        currency_factor: '1500',
        lines: [[
            'item_id' => $item->id,
            'description' => $item->description,
            'quantity' => 1,
            'unit_of_measure' => 'PCS',
            'unit_price' => 220,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'vat_percent' => 0,
        ]],
    ));

    $line = $invoice->lines()->firstOrFail();

    expect((float) $line->unit_price)->toBe(220.0)
        ->and((float) $line->unit_price_lcy)->toBe(330000.0)
        ->and((float) $line->line_total_lcy)->toBe(330000.0)
        ->and((float) $invoice->total_amount_lcy)->toBe(330000.0);

    // An unresolved foreign line cannot post.
    $blocked = SalesInvoice::query()->create([
        'invoice_number' => 'SI-MON-UNRESOLVED',
        'customer_id' => $customer->id,
        'status' => ApprovalStatus::APPROVED,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(30)->toDateString(),
        'currency_code' => 'USD',
        'currency_factor' => '1500',
        'total_amount' => 0,
    ]);

    $blocked->lines()->create([
        'item_id' => $item->id,
        'description' => $item->description,
        'quantity' => 1,
        'unit_of_measure' => 'PCS',
        'unit_price' => 0,
        'pricing_status' => SalesLinePricingStatus::UNRESOLVED,
    ]);

    expect(fn () => app(SalesInvoicePricingGuard::class)->assertCanPost($blocked->fresh('lines')))
        ->toThrow(BusinessException::class);
});

// ---------------------------------------------------------------------------
// Posted snapshot fixture (self-contained)
// ---------------------------------------------------------------------------

/**
 * @return array{user: User, customer: Customer, item: Item}
 */
function salesMonetaryPostingFixture(): array
{
    GeneralLedgerSetup::query()->updateOrCreate(
        ['company_name' => 'Default Company'],
        ['allow_posting_from' => '2026-01-01', 'allow_posting_to' => '2026-12-31'],
    );

    AccountingPeriod::query()->firstOrCreate(
        ['name' => 'FY2026'],
        ['start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_closed' => false],
    );

    $user = User::factory()->create();

    $receivables = salesMonetaryAccount('1100', 'Accounts Receivable', 'receivable', IncomeBalanceType::BALANCE_SHEET);
    $inventory = salesMonetaryAccount('1200', 'Inventory', 'inventory', IncomeBalanceType::BALANCE_SHEET);
    $revenue = salesMonetaryAccount('4000', 'Sales Revenue', 'revenue', IncomeBalanceType::INCOME_STATEMENT);
    $cogs = salesMonetaryAccount('5000', 'Cost of Goods Sold', 'cogs', IncomeBalanceType::INCOME_STATEMENT);

    $businessGroup = GeneralBusinessPostingGroup::query()->create(['code' => 'MON-DOM', 'description' => 'Domestic', 'blocked' => false]);
    $productGroup = GeneralProductPostingGroup::query()->create(['code' => 'MON-FIN', 'description' => 'Finished', 'blocked' => false]);
    $inventoryGroup = InventoryPostingGroup::query()->create(['code' => 'MON-INV', 'description' => 'Inventory', 'blocked' => false]);
    $customerGroup = CustomerPostingGroup::query()->create([
        'code' => 'MON-CUST',
        'description' => 'Domestic Customers',
        'receivables_account_id' => $receivables->id,
        'blocked' => false,
    ]);

    InventoryPostingSetup::query()->create([
        'inventory_posting_group_id' => $inventoryGroup->id,
        'location_id' => null,
        'inventory_account_id' => $inventory->id,
    ]);

    GeneralPostingSetup::query()->create([
        'general_business_posting_group_id' => $businessGroup->id,
        'general_product_posting_group_id' => $productGroup->id,
        'sales_account_id' => $revenue->id,
        'cogs_account_id' => $cogs->id,
        'blocked' => false,
    ]);

    $baseUom = UnitOfMeasure::query()->create(['uom_code' => 'PCS', 'description' => 'Pieces', 'is_base_uom' => true]);
    $cartonUom = UnitOfMeasure::query()->create(['uom_code' => 'CT', 'description' => 'Carton', 'is_base_uom' => false]);

    $item = Item::query()->create([
        'item_code' => 'MON-CT',
        'description' => 'Monetary carton item',
        'item_type' => ItemType::FINISHED_GOOD,
        'base_uom_id' => $baseUom->id,
        'unit_cost' => 10,
        'inventory' => 288,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
    ]);

    ItemUomAssignment::query()->create([
        'item_id' => $item->id,
        'uom_id' => $cartonUom->id,
        'uom_type' => 'SALES',
        'conversion_factor' => 288,
        'is_default' => true,
    ]);

    $customer = Customer::factory()->create([
        'general_business_posting_group_id' => $businessGroup->id,
        'customer_posting_group_id' => $customerGroup->id,
        'vat_bus_posting_group' => null,
    ]);

    return compact('user', 'customer', 'item');
}

function salesMonetaryAccount(string $number, string $name, string $category, IncomeBalanceType $incomeBalance): ChartOfAccount
{
    return ChartOfAccount::query()->create([
        'account_number' => $number,
        'name' => $name,
        'account_category' => $category,
        'income_balance' => $incomeBalance,
        'direct_posting' => true,
        'blocked' => false,
    ]);
}

// ---------------------------------------------------------------------------
// H. Posted sales invoice snapshot
// ---------------------------------------------------------------------------

it('snapshots currency, factor, FCY and LCY amounts on posting and keeps them immutable', function (): void {
    $fixture = salesMonetaryPostingFixture();
    $this->actingAs($fixture['user']);

    $invoice = SalesInvoice::query()->create([
        'invoice_number' => 'SI-MON-USD-001',
        'customer_id' => $fixture['customer']->id,
        'status' => ApprovalStatus::APPROVED,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'currency_code' => 'USD',
        'currency_factor' => '1500',
        'approved_by' => $fixture['user']->id,
        'approved_at' => now(),
    ]);

    $invoice->lines()->create([
        'item_id' => $fixture['item']->id,
        'description' => 'One carton sale',
        'quantity' => 1,
        'unit_of_measure' => 'CT',
        'unit_price' => 220,
    ]);

    app(SalesInvoiceService::class)->post($invoice);

    $posted = PostedSalesInvoice::query()->where('document_number', 'SI-MON-USD-001')->firstOrFail();
    $postedLine = $posted->lines()->firstOrFail();

    expect($posted->currency_code)->toBe('USD')
        ->and((float) $posted->currency_factor)->toBe(1500.0)
        ->and((float) $posted->grand_total)->toBe(220.0)
        ->and((float) $posted->grand_total_lcy)->toBe(330000.0)
        ->and((float) $posted->remaining_amount_lcy)->toBe(330000.0)
        ->and((float) $postedLine->unit_price)->toBe(220.0)
        ->and((float) $postedLine->unit_price_lcy)->toBe(330000.0)
        ->and((float) $postedLine->line_total_lcy)->toBe(330000.0);

    // A later price/rate change must not rewrite the posted snapshot.
    $fixture['item']->update(['unit_price' => 999999]);
    salesMonetaryTag($fixture['item'], null, 'USD', 888, 'CT');

    $posted->refresh();
    $postedLine->refresh();

    expect((float) $posted->grand_total)->toBe(220.0)
        ->and((float) $posted->grand_total_lcy)->toBe(330000.0)
        ->and((float) $posted->currency_factor)->toBe(1500.0)
        ->and((float) $postedLine->unit_price_lcy)->toBe(330000.0);
});

// ---------------------------------------------------------------------------
// I. Linked sales credit memo preserves posted invoice economics
// ---------------------------------------------------------------------------

function salesMonetaryPostedInvoice(array $fixture, string $currency, mixed $factor, float $unitPrice = 220): PostedSalesInvoice
{
    $posted = PostedSalesInvoice::query()->create([
        'document_number' => 'PSI-'.strtoupper(substr(uniqid(), -8)),
        'customer_id' => $fixture['customer']->id,
        'customer_name' => $fixture['customer']->name,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'due_date' => now()->toDateString(),
        'currency_code' => $currency,
        'currency_factor' => $factor,
        'grand_total' => $unitPrice,
        'remaining_amount' => $unitPrice,
        'posted_by' => $fixture['user']->id,
        'posted_at' => now(),
        'cancelled' => false,
    ]);

    $posted->lines()->create([
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'item_description' => $fixture['item']->description,
        'posting_date' => now()->toDateString(),
        'quantity' => 1,
        'unit_of_measure_code' => 'CT',
        'qty_per_unit_of_measure' => 288,
        'quantity_base' => 288,
        'unit_price' => $unitPrice,
        'unit_cost' => 10,
        'unit_cost_lcy' => 10,
        'line_total' => $unitPrice,
        'line_amount' => $unitPrice,
        'vat_percentage' => 0,
        'vat_amount' => 0,
        'amount_including_vat' => $unitPrice,
        'line_number' => 10000,
    ]);

    return $posted;
}

it('preserves posted invoice economics on a linked credit memo and inherits its currency context', function (): void {
    $fixture = salesMonetaryPostingFixture();
    $this->actingAs($fixture['user']);

    $posted = salesMonetaryPostedInvoice($fixture, 'USD', '1500');
    $postedLine = $posted->lines()->firstOrFail();

    // A current negotiated price that must not be used for the linked return.
    salesMonetaryTag($fixture['item'], $fixture['customer'], 'USD', 999, 'CT');

    $memo = app(SalesCreditMemoService::class)->create(SalesCreditMemoData::from([
        'customer_id' => $fixture['customer']->id,
        'posted_sales_invoice_id' => $posted->id,
        'memo_number' => 'SCM-MON-LINKED',
        'effective_date' => now()->toDateString(),
        'reason' => 'Customer return',
        'items' => [[
            'item_id' => $fixture['item']->id,
            'quantity' => 1,
            'unit_price' => 999,
            'posted_sales_invoice_line_id' => $postedLine->id,
            'unit_of_measure_code' => 'CT',
        ]],
    ]));

    $line = $memo->fresh()->items()->firstOrFail();

    expect($memo->currency_code)->toBe('USD')
        ->and((float) $memo->currency_factor)->toBe(1500.0)
        ->and((float) $line->unit_price)->toBe(220.0)
        ->and((float) $line->unit_price)->not->toBe(999.0)
        ->and((float) $line->unit_price_lcy)->toBe(330000.0)
        ->and((float) $line->amount_lcy)->toBe(330000.0);
});

// ---------------------------------------------------------------------------
// J. Direct credit memo
// ---------------------------------------------------------------------------

it('converts a direct USD credit memo once and fails closed without a factor', function (): void {
    $fixture = salesMonetaryPostingFixture();
    $this->actingAs($fixture['user']);

    $memo = app(SalesCreditMemoService::class)->create(SalesCreditMemoData::from([
        'customer_id' => $fixture['customer']->id,
        'memo_number' => 'SCM-MON-DIRECT',
        'effective_date' => now()->toDateString(),
        'currency_code' => 'USD',
        'currency_factor' => '1500',
        'reason' => 'Allowance',
        'items' => [[
            'item_id' => $fixture['item']->id,
            'quantity' => 1,
            'unit_price' => 220,
            'vat_percent' => 0,
            'unit_of_measure_code' => 'CT',
        ]],
    ]));

    $line = $memo->fresh()->items()->firstOrFail();

    expect((float) $line->unit_price_lcy)->toBe(330000.0)
        ->and((float) $line->amount_lcy)->toBe(330000.0)
        ->and((float) $line->amount_including_vat_lcy)->toBe(330000.0)
        ->and((float) $memo->total_amount_lcy)->toBe(330000.0);

    expect(fn () => app(SalesCreditMemoService::class)->create(SalesCreditMemoData::from([
        'customer_id' => $fixture['customer']->id,
        'memo_number' => 'SCM-MON-NOFACTOR',
        'effective_date' => now()->toDateString(),
        'currency_code' => 'USD',
        'currency_factor' => null,
        'reason' => 'No rate',
        'items' => [[
            'item_id' => $fixture['item']->id,
            'quantity' => 1,
            'unit_price' => 220,
            'unit_of_measure_code' => 'CT',
        ]],
    ])))->toThrow(BusinessException::class, 'factor');

    expect(PostedSalesCreditMemo::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// K. NGN reference is never relabelled as FCY
// ---------------------------------------------------------------------------

it('never relabels an NGN item reference as a foreign price', function (): void {
    $customer = Customer::factory()->create();
    $item = salesMonetaryItem(300000);

    $result = app(SalesPricingResolver::class)->resolve(
        item: $item,
        customer: $customer,
        quantity: 1.0,
        uom: 'PCS',
        documentCurrency: 'USD',
    );

    expect($result['unit_price'])->toBe(0.0)
        ->and($result['pricing_status'])->toBe(SalesLinePricingStatus::UNRESOLVED->value)
        ->and($result['unit_price'])->not->toBe(300000.0)
        ->and($result['unit_price'])->not->toBe(200.0);
});

// ---------------------------------------------------------------------------
// M. UOM conversion and FX conversion do not compound
// ---------------------------------------------------------------------------

it('applies UOM conversion and FX conversion exactly once each', function (): void {
    $customer = Customer::factory()->create();
    $item = salesMonetaryItem();
    // A price recorded without a UOM is a base-UOM USD price.
    $tag = salesMonetaryTag($item, $customer, 'USD', 50, null);

    $carton = UnitOfMeasure::query()->create(['uom_code' => 'CTN', 'description' => 'Carton']);
    $item->uoms()->attach($carton->id, [
        'uom_type' => 'SALES',
        'conversion_factor' => 2,
        'is_default' => true,
    ]);

    $pricing = app(SalesPricingResolver::class)->resolve(
        item: $item,
        customer: $customer,
        quantity: 1.0,
        uom: 'CTN',
        documentCurrency: 'USD',
    );

    expect($pricing['unit_price'])->toBe(100.0)
        ->and($pricing['price_record_id'])->toBe($tag->id);

    $lineLcy = app(SalesDocumentMonetaryCalculator::class)->deriveLcy('USD', '1500', $pricing['unit_price'], 4);

    // 100 (UOM-converted once) x 1500 (FX once) = 150000, not 300000.
    expect((float) $lineLcy)->toBe(150000.0)
        ->and((float) $lineLcy)->not->toBe(300000.0)
        ->and((float) $lineLcy)->not->toBe(600000.0);
});

// ---------------------------------------------------------------------------
// Phase 3B2-B2 — linked credit memo currency authority
// ---------------------------------------------------------------------------

function salesMonetaryBusinessException(callable $callback): BusinessException
{
    try {
        $callback();
    } catch (BusinessException $exception) {
        return $exception;
    }

    throw new RuntimeException('Expected a BusinessException to be thrown.');
}

/**
 * @return array<string, mixed>
 */
function salesMonetaryLinkedMemoPayload(array $fixture, PostedSalesInvoice $posted, int $postedLineId, array $overrides = []): array
{
    return array_merge([
        'customer_id' => $fixture['customer']->id,
        'posted_sales_invoice_id' => $posted->id,
        'effective_date' => now()->toDateString(),
        'reason' => 'Customer return',
        'items' => [[
            'item_id' => $fixture['item']->id,
            'quantity' => 1,
            'unit_price' => 999,
            'posted_sales_invoice_line_id' => $postedLineId,
            'unit_of_measure_code' => 'CT',
        ]],
    ], $overrides);
}

it('rejects a linked credit memo whose caller currency differs from the posted invoice (Scenario A)', function (): void {
    $fixture = salesMonetaryPostingFixture();
    $this->actingAs($fixture['user']);

    $posted = salesMonetaryPostedInvoice($fixture, 'USD', '1500');
    $postedLine = $posted->lines()->firstOrFail();

    $exception = salesMonetaryBusinessException(fn () => app(SalesCreditMemoService::class)->create(SalesCreditMemoData::from(
        salesMonetaryLinkedMemoPayload($fixture, $posted, $postedLine->id, [
            'memo_number' => 'SCM-MON-CONFLICT-A',
            'currency_code' => 'NGN',
            'currency_factor' => '1',
        ])
    )));

    expect($exception->codeIdentifier())->toBe('sales_credit_memo_currency_conflict')
        ->and(SalesCreditMemo::query()->count())->toBe(0)
        ->and(SalesCreditMemoLine::query()->count())->toBe(0)
        ->and(PostedSalesCreditMemo::query()->count())->toBe(0);
});

it('rejects a linked credit memo that would relabel an NGN invoice as foreign (Scenario B)', function (): void {
    $fixture = salesMonetaryPostingFixture();
    $this->actingAs($fixture['user']);

    $posted = salesMonetaryPostedInvoice($fixture, 'NGN', '1', 330000);
    $postedLine = $posted->lines()->firstOrFail();

    $exception = salesMonetaryBusinessException(fn () => app(SalesCreditMemoService::class)->create(SalesCreditMemoData::from(
        salesMonetaryLinkedMemoPayload($fixture, $posted, $postedLine->id, [
            'memo_number' => 'SCM-MON-CONFLICT-B',
            'currency_code' => 'USD',
            'currency_factor' => '1500',
        ])
    )));

    expect($exception->codeIdentifier())->toBe('sales_credit_memo_currency_conflict')
        ->and(SalesCreditMemo::query()->count())->toBe(0)
        ->and(SalesCreditMemoLine::query()->count())->toBe(0);
});

it('rejects a linked credit memo whose caller factor differs from the posted invoice (Scenario C)', function (): void {
    $fixture = salesMonetaryPostingFixture();
    $this->actingAs($fixture['user']);

    $posted = salesMonetaryPostedInvoice($fixture, 'USD', '1500');
    $postedLine = $posted->lines()->firstOrFail();

    $exception = salesMonetaryBusinessException(fn () => app(SalesCreditMemoService::class)->create(SalesCreditMemoData::from(
        salesMonetaryLinkedMemoPayload($fixture, $posted, $postedLine->id, [
            'memo_number' => 'SCM-MON-CONFLICT-C',
            'currency_code' => 'USD',
            'currency_factor' => '1550',
        ])
    )));

    expect($exception->codeIdentifier())->toBe('sales_credit_memo_currency_factor_conflict')
        ->and(SalesCreditMemo::query()->count())->toBe(0);

    // The historical recognition factor is never replaced: LCY 341000 must not exist.
    expect(SalesCreditMemoLine::query()->where('unit_price_lcy', 341000)->exists())->toBeFalse();

    // A caller that matches the source is accepted and preserves 220 x 1500.
    $valid = app(SalesCreditMemoService::class)->create(SalesCreditMemoData::from(
        salesMonetaryLinkedMemoPayload($fixture, $posted, $postedLine->id, [
            'memo_number' => 'SCM-MON-CONFLICT-C-OK',
            'currency_code' => 'USD',
            'currency_factor' => '1500',
        ])
    ));

    $validLine = $valid->fresh()->items()->firstOrFail();

    expect($valid->currency_code)->toBe('USD')
        ->and((float) $valid->currency_factor)->toBe(1500.0)
        ->and((float) $validLine->unit_price)->toBe(220.0)
        ->and((float) $validLine->unit_price_lcy)->toBe(330000.0);
});

it('inherits the source posted invoice currency when the caller states no currency', function (): void {
    $fixture = salesMonetaryPostingFixture();
    $this->actingAs($fixture['user']);

    $posted = salesMonetaryPostedInvoice($fixture, 'USD', '1500');
    $postedLine = $posted->lines()->firstOrFail();

    $memo = app(SalesCreditMemoService::class)->create(SalesCreditMemoData::from(
        salesMonetaryLinkedMemoPayload($fixture, $posted, $postedLine->id, [
            'memo_number' => 'SCM-MON-INHERIT',
        ])
    ));

    $line = $memo->fresh()->items()->firstOrFail();

    expect($memo->currency_code)->toBe('USD')
        ->and((float) $memo->currency_factor)->toBe(1500.0)
        ->and((float) $line->unit_price)->toBe(220.0)
        ->and((float) $line->unit_price_lcy)->toBe(330000.0);
});

it('rejects reclassifying an existing linked credit memo away from the source currency', function (): void {
    $fixture = salesMonetaryPostingFixture();
    $this->actingAs($fixture['user']);

    $posted = salesMonetaryPostedInvoice($fixture, 'USD', '1500');
    $postedLine = $posted->lines()->firstOrFail();

    $memo = app(SalesCreditMemoService::class)->create(SalesCreditMemoData::from(
        salesMonetaryLinkedMemoPayload($fixture, $posted, $postedLine->id, [
            'memo_number' => 'SCM-MON-UPDATE-GUARD',
        ])
    ));

    expect($memo->currency_code)->toBe('USD');

    $exception = salesMonetaryBusinessException(fn () => app(SalesCreditMemoService::class)->update($memo, SalesCreditMemoData::from(
        salesMonetaryLinkedMemoPayload($fixture, $posted, $postedLine->id, [
            'memo_number' => 'SCM-MON-UPDATE-GUARD',
            'currency_code' => 'NGN',
            'currency_factor' => '1',
        ])
    )));

    expect($exception->codeIdentifier())->toBe('sales_credit_memo_currency_conflict')
        ->and($memo->fresh()->currency_code)->toBe('USD')
        ->and((float) $memo->fresh()->currency_factor)->toBe(1500.0);
});

it('fails closed before posting side effects when a persisted draft contradicts its source invoice', function (): void {
    $fixture = salesMonetaryPostingFixture();
    salesMonetaryGrantCreditMemoPostPermission($fixture['user']);
    $this->actingAs($fixture['user']);

    $posted = salesMonetaryPostedInvoice($fixture, 'USD', '1500');
    $postedLine = $posted->lines()->firstOrFail();

    // Simulate a legacy/corrupted draft whose persisted context contradicts the
    // source: bypass the service guard by writing the row directly.
    $memo = new SalesCreditMemo([
        'customer_id' => $fixture['customer']->id,
        'posted_sales_invoice_id' => $posted->id,
        'memo_number' => 'SCM-MON-POST-GUARD',
        'status' => ApprovalStatus::APPROVED,
        'effective_date' => now()->toDateString(),
        'reason' => 'Corrupted draft',
        'currency_code' => 'NGN',
        'currency_factor' => '1',
        'total_amount' => 220,
    ]);
    $memo->saveQuietly();

    $memo->items()->create([
        'item_id' => $fixture['item']->id,
        'quantity' => 1,
        'unit_price' => 220,
        'posted_sales_invoice_line_id' => $postedLine->id,
        'unit_of_measure_code' => 'CT',
    ]);

    $exception = salesMonetaryBusinessException(fn () => app(SalesCreditMemoService::class)->post($memo->fresh()));

    expect($exception->codeIdentifier())->toBe('sales_credit_memo_currency_conflict')
        ->and(PostedSalesCreditMemo::query()->where('document_number', 'SCM-MON-POST-GUARD')->exists())->toBeFalse();
});

// ---------------------------------------------------------------------------
// Phase 3B2-B2 — model-level currency safety and stale LCY
// ---------------------------------------------------------------------------

it('rejects a malformed factor change on a USD invoice and keeps the valid LCY intact', function (): void {
    salesMonetaryUser();
    salesMonetaryNumberSeries('S-INV', 'SINV-');

    $customer = Customer::factory()->create();
    $item = salesMonetaryItem();

    $invoice = app(SalesInvoiceService::class)->create(new SalesInvoiceData(
        customer_id: $customer->id,
        sales_order_id: null,
        invoice_date: now()->toDateString(),
        due_date: now()->addDays(30)->toDateString(),
        currency_code: 'USD',
        currency_factor: '1500',
        lines: [[
            'item_id' => $item->id,
            'description' => $item->description,
            'quantity' => 1,
            'unit_of_measure' => 'PCS',
            'unit_price' => 220,
            'discount_percent' => 0,
            'discount_amount' => 0,
            'vat_percent' => 0,
        ]],
    ));

    expect((float) $invoice->lines()->firstOrFail()->unit_price_lcy)->toBe(330000.0);

    expect(fn () => $invoice->update(['currency_factor' => null]))
        ->toThrow(BusinessException::class, 'factor');

    $invoice->refresh();

    expect((float) $invoice->currency_factor)->toBe(1500.0)
        ->and((float) $invoice->lines()->firstOrFail()->unit_price_lcy)->toBe(330000.0);
});

it('rejects a malformed factor change on a USD credit memo and keeps the valid LCY intact', function (): void {
    $fixture = salesMonetaryPostingFixture();
    $this->actingAs($fixture['user']);

    $memo = app(SalesCreditMemoService::class)->create(SalesCreditMemoData::from([
        'customer_id' => $fixture['customer']->id,
        'memo_number' => 'SCM-MON-STALE',
        'effective_date' => now()->toDateString(),
        'currency_code' => 'USD',
        'currency_factor' => '1500',
        'reason' => 'Stale LCY',
        'items' => [[
            'item_id' => $fixture['item']->id,
            'quantity' => 1,
            'unit_price' => 220,
            'unit_of_measure_code' => 'CT',
        ]],
    ]));

    expect((float) $memo->items()->firstOrFail()->amount_lcy)->toBe(330000.0);

    expect(fn () => $memo->update(['currency_factor' => null]))
        ->toThrow(BusinessException::class, 'factor');

    $memo->refresh();

    expect((float) $memo->currency_factor)->toBe(1500.0)
        ->and((float) $memo->items()->firstOrFail()->amount_lcy)->toBe(330000.0);
});

it('leaves a historical malformed sales document editable without normalising its currency', function (): void {
    $customer = Customer::factory()->create();

    $invoice = SalesInvoice::query()->create([
        'invoice_number' => 'SI-MON-LEGACY-MALFORMED',
        'customer_id' => $customer->id,
        'status' => ApprovalStatus::DRAFT,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'currency_code' => 'USD',
        'currency_factor' => null,
        'total_amount' => 0,
    ]);

    $memo = SalesCreditMemo::query()->create([
        'memo_number' => 'SCM-MON-LEGACY-MALFORMED',
        'customer_id' => $customer->id,
        'status' => ApprovalStatus::DRAFT,
        'effective_date' => now()->toDateString(),
        'currency_code' => 'USD',
        'currency_factor' => null,
        'total_amount' => 0,
    ]);

    $invoice->update(['total_amount' => 10]);
    $memo->update(['total_amount' => 10]);

    expect($invoice->fresh()->currency_code)->toBe('USD')
        ->and($invoice->fresh()->currency_factor)->toBeNull()
        ->and((float) $invoice->fresh()->total_amount)->toBe(10.0)
        ->and($memo->fresh()->currency_code)->toBe('USD')
        ->and($memo->fresh()->currency_factor)->toBeNull()
        ->and((float) $memo->fresh()->total_amount)->toBe(10.0);
});

// ---------------------------------------------------------------------------
// Phase 3B2-B2 — direct invoice discount consistency
// ---------------------------------------------------------------------------

it('persists one discount truth on a model-created foreign invoice line', function (): void {
    $fixture = salesMonetaryPostingFixture();
    $this->actingAs($fixture['user']);

    $invoice = SalesInvoice::query()->create([
        'invoice_number' => 'SI-MON-DISCOUNT',
        'customer_id' => $fixture['customer']->id,
        'status' => ApprovalStatus::DRAFT,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'currency_code' => 'USD',
        'currency_factor' => '1500',
    ]);

    $line = $invoice->lines()->create([
        'item_id' => $fixture['item']->id,
        'description' => 'Discounted line',
        'quantity' => 2,
        'unit_of_measure' => 'CT',
        'unit_price' => 100,
        'discount_percent' => 10,
        'vat_percent' => 0,
    ]);

    expect((float) $line->discount_amount)->toBe(20.0)
        ->and((float) $line->discount_amount_lcy)->toBe(30000.0)
        ->and((float) $line->line_total)->toBe(180.0);
});

it('keeps the service-created invoice discount consistent between FCY and LCY', function (): void {
    salesMonetaryUser();
    salesMonetaryNumberSeries('S-INV', 'SINV-');

    $customer = Customer::factory()->create();
    $item = salesMonetaryItem();

    $invoice = app(SalesInvoiceService::class)->create(new SalesInvoiceData(
        customer_id: $customer->id,
        sales_order_id: null,
        invoice_date: now()->toDateString(),
        due_date: now()->addDays(30)->toDateString(),
        currency_code: 'USD',
        currency_factor: '1500',
        lines: [[
            'item_id' => $item->id,
            'description' => $item->description,
            'quantity' => 2,
            'unit_of_measure' => 'PCS',
            'unit_price' => 100,
            'discount_percent' => 10,
            'discount_amount' => 0,
            'vat_percent' => 0,
        ]],
    ));

    $line = $invoice->lines()->firstOrFail();

    expect((float) $line->discount_amount)->toBe(20.0)
        ->and((float) $line->discount_amount_lcy)->toBe(30000.0);
});

// ---------------------------------------------------------------------------
// Phase 3B2-B2 — posted credit memo snapshot and signed rounding
// ---------------------------------------------------------------------------

function salesMonetaryGrantCreditMemoPostPermission(User $user): void
{
    $permission = Permission::query()->firstOrCreate([
        'name' => 'sales.credit_memo.post',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo($permission);
}

it('snapshots the linked USD credit memo currency, FCY and LCY amounts on posting', function (): void {
    $fixture = salesMonetaryPostingFixture();
    salesMonetaryGrantCreditMemoPostPermission($fixture['user']);
    $this->actingAs($fixture['user']);

    // Build the source posted invoice through the real posting flow so the
    // original outbound item ledger entry exists for the return cost.
    $invoice = SalesInvoice::query()->create([
        'invoice_number' => 'SI-MON-CM-SNAPSHOT',
        'customer_id' => $fixture['customer']->id,
        'status' => ApprovalStatus::APPROVED,
        'invoice_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'currency_code' => 'USD',
        'currency_factor' => '1500',
        'approved_by' => $fixture['user']->id,
        'approved_at' => now(),
    ]);

    $invoice->lines()->create([
        'item_id' => $fixture['item']->id,
        'description' => 'Carton sale',
        'quantity' => 1,
        'unit_of_measure' => 'CT',
        'unit_price' => 220,
    ]);

    app(SalesInvoiceService::class)->post($invoice);

    $postedInvoice = PostedSalesInvoice::query()->where('document_number', 'SI-MON-CM-SNAPSHOT')->firstOrFail();
    $postedInvoiceLine = $postedInvoice->lines()->firstOrFail();

    $memo = app(SalesCreditMemoService::class)->create(SalesCreditMemoData::from(
        salesMonetaryLinkedMemoPayload($fixture, $postedInvoice, $postedInvoiceLine->id, [
            'memo_number' => 'SCM-MON-SNAPSHOT',
        ])
    ));

    $memo->update(['status' => ApprovalStatus::APPROVED]);

    expect($memo->currency_code)->toBe('USD')
        ->and((float) $memo->currency_factor)->toBe(1500.0);

    app(SalesCreditMemoService::class)->post($memo->fresh());

    $postedMemo = PostedSalesCreditMemo::query()->where('document_number', 'SCM-MON-SNAPSHOT')->firstOrFail();
    $postedMemoLine = $postedMemo->lines()->firstOrFail();

    expect($postedMemo->currency_code)->toBe('USD')
        ->and((float) $postedMemo->currency_factor)->toBe(1500.0)
        ->and((float) $postedMemoLine->unit_price)->toBe(220.0)
        ->and((float) $postedMemoLine->unit_price_lcy)->toBe(330000.0)
        ->and((float) $postedMemoLine->line_amount)->toBe(-220.0)
        ->and((float) $postedMemoLine->line_amount_lcy)->toBe(-330000.0)
        ->and((float) $postedMemo->total_amount)->toBe(-220.0)
        ->and((float) $postedMemo->total_amount_lcy)->toBe(-330000.0)
        ->and((float) $postedMemo->grand_total)->toBe(-220.0)
        ->and((float) $postedMemo->grand_total_lcy)->toBe(-330000.0)
        ->and((float) $postedMemo->remaining_amount_lcy)->toBe(330000.0);

    // A later price/reference change must not rewrite the posted snapshot.
    $fixture['item']->update(['unit_price' => 999999]);
    salesMonetaryTag($fixture['item'], null, 'USD', 888, 'CT');

    expect((float) $postedMemo->fresh()->grand_total_lcy)->toBe(-330000.0)
        ->and((float) $postedMemoLine->fresh()->unit_price_lcy)->toBe(330000.0)
        ->and((float) $postedMemo->fresh()->currency_factor)->toBe(1500.0);
});

it('rounds signed LCY equivalents half-up symmetrically', function (): void {
    $calculator = app(SalesDocumentMonetaryCalculator::class);

    // A 0.5 tie rounds away from zero for both signs, not toward zero.
    expect($calculator->deriveLcy('USD', '1', '1.005', 2))->toBe('1.01')
        ->and($calculator->deriveLcy('USD', '1', '-1.005', 2))->toBe('-1.01');

    // A fractional rate mirrors the positive result on a signed amount.
    $positive = $calculator->deriveLcy('USD', '1500.5', '220.1111', 4);
    $negative = $calculator->deriveLcy('USD', '1500.5', '-220.1111', 4);

    expect($positive)->toBe('330276.7056')
        ->and($negative)->toBe('-330276.7056')
        ->and($calculator->total(['-330276.7056', null, '-1000.0000'], 4))->toBe('-331276.7056');
});

it('derives a fractional LCY equivalent for a credit memo line and rounds half-up', function (): void {
    $fixture = salesMonetaryPostingFixture();
    $this->actingAs($fixture['user']);

    $memo = app(SalesCreditMemoService::class)->create(SalesCreditMemoData::from([
        'customer_id' => $fixture['customer']->id,
        'memo_number' => 'SCM-MON-FRACTION',
        'effective_date' => now()->toDateString(),
        'currency_code' => 'USD',
        'currency_factor' => '1500.5',
        'reason' => 'Fractional rate',
        'items' => [[
            'item_id' => $fixture['item']->id,
            'quantity' => 1,
            'unit_price' => 220.11,
            'unit_of_measure_code' => 'CT',
            'vat_percent' => 0,
        ]],
    ]));

    $line = $memo->fresh()->items()->firstOrFail();

    // 220.11 x 1500.5 = 330275.055 exactly; the 2dp amount ties half-up to .06.
    expect((float) $line->amount_lcy)->toBe(330275.06)
        ->and((float) $line->amount_lcy)->not->toBe(330275.05);
});

// ---------------------------------------------------------------------------
// Phase 3B2-B2 — Filament linked memo currency synchronisation
// ---------------------------------------------------------------------------

function salesMonetarySuperAdmin(): User
{
    $user = User::factory()->create([
        'two_factor_secret' => 'TESTSECRET',
        'two_factor_confirmed_at' => now(),
    ]);

    Role::findOrCreate('super_admin', 'web');
    $user->assignRole('super_admin');

    return $user;
}

function salesMonetaryPostedInvoiceForForm(Customer $customer, Item $item, string $currency, mixed $factor): PostedSalesInvoice
{
    $posted = PostedSalesInvoice::query()->create([
        'document_number' => 'PSI-FORM-'.strtoupper(substr(uniqid(), -8)),
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'due_date' => now()->toDateString(),
        'currency_code' => $currency,
        'currency_factor' => $factor,
        'grand_total' => 220,
        'remaining_amount' => 220,
        'posted_by' => User::factory()->create()->id,
        'posted_at' => now(),
        'cancelled' => false,
    ]);

    $posted->lines()->create([
        'item_id' => $item->id,
        'item_code' => $item->item_code,
        'item_description' => $item->description,
        'posting_date' => now()->toDateString(),
        'quantity' => 5,
        'unit_of_measure_code' => 'CT',
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => 5,
        'unit_price' => 220,
        'unit_cost' => 10,
        'unit_cost_lcy' => 10,
        'line_total' => 220,
        'line_amount' => 220,
        'vat_percentage' => 0,
        'vat_amount' => 0,
        'amount_including_vat' => 220,
        'line_number' => 10000,
    ]);

    return $posted;
}

it('synchronises the memo currency from a selected USD posted invoice and blocks a conflicting submission', function (): void {
    $user = salesMonetarySuperAdmin();
    salesMonetaryNumberSeries('S-CM', 'SCM-');

    $customer = Customer::factory()->create();
    $item = Item::factory()->create([
        'item_type' => ItemType::FINISHED_GOOD,
        'unit_price' => 150,
    ]);
    $posted = salesMonetaryPostedInvoiceForForm($customer, $item, 'USD', '1500');
    $postedLine = $posted->lines()->firstOrFail();

    // Selecting the posted invoice must not leave the memo at the NGN default.
    Livewire::actingAs($user)
        ->test(CreateSalesCreditMemo::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'posted_sales_invoice_id' => $posted->id,
            'effective_date' => now()->toDateString(),
            'reason' => 'Return',
        ])
        ->assertFormSet(['currency_code' => 'USD']);

    // The normal UI path commits the source currency, not the NGN default.
    Livewire::actingAs($user)
        ->test(CreateSalesCreditMemo::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'posted_sales_invoice_id' => $posted->id,
            'effective_date' => now()->toDateString(),
            'reason' => 'Return',
            'items' => [[
                'posted_sales_invoice_line_id' => $postedLine->id,
                'item_id' => $item->id,
                'description' => $item->description,
                'quantity' => 1,
                'unit_price' => 220,
                'vat_percent' => 0,
                'unit_of_measure_code' => $item->base_unit_of_measure,
                'qty_per_unit_of_measure' => 1,
            ]],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $memo = SalesCreditMemo::query()->where('memo_number', 'SCM-000001')->firstOrFail();

    expect($memo->currency_code)->toBe('USD')
        ->and((float) $memo->currency_factor)->toBe(1500.0)
        ->and((float) $memo->items()->firstOrFail()->unit_price_lcy)->toBe(330000.0);

    // A conflicting currency can never be persisted through the form either.
    Livewire::actingAs($user)
        ->test(CreateSalesCreditMemo::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'posted_sales_invoice_id' => $posted->id,
            'effective_date' => now()->toDateString(),
            'reason' => 'Conflict',
            'currency_code' => 'NGN',
            'currency_factor' => '1',
            'items' => [[
                'posted_sales_invoice_line_id' => $postedLine->id,
                'item_id' => $item->id,
                'description' => $item->description,
                'quantity' => 1,
                'unit_price' => 220,
                'vat_percent' => 0,
                'unit_of_measure_code' => $item->base_unit_of_measure,
                'qty_per_unit_of_measure' => 1,
            ]],
        ])
        ->call('create');

    // Whether the locked field is dropped (service inherits the source) or
    // re-validated (service rejects the conflict), the normal UI path can never
    // persist a conflicting linked currency.
    $memos = SalesCreditMemo::query()->orderBy('id')->get();

    expect($memos->where('currency_code', 'NGN')->count())->toBe(0)
        ->and($memos)->not->toBeEmpty();

    foreach ($memos as $persisted) {
        expect($persisted->currency_code)->toBe('USD')
            ->and((float) $persisted->currency_factor)->toBe(1500.0);
    }
});
