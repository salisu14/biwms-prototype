<?php

declare(strict_types=1);

use App\Data\Purchase\CreatePurchaseOrderData;
use App\Enums\ApprovalStatus;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemType;
use App\Enums\PurchaseOrderStatus;
use App\Enums\PurchaseOrderType;
use App\Models\AccountingPeriod;
use App\Models\Business;
use App\Models\ChartOfAccount;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralLedgerSetup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\Location;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\PostedPurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PurchasePrice;
use App\Models\PurchaseReceipt;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPostingGroup;
use App\Services\Purchase\PurchaseInvoiceService;
use App\Services\Purchase\PurchaseOrderService;
use App\Services\Purchase\PurchasePriceCalculationService;
use App\Services\Purchase\PurchaseReceiptLinePrefillService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('NGN purchase order keeps document and LCY values identical at factor 1', function (): void {
    $fixture = phase2PurchasingFixture();

    $order = phase2Order($fixture, ['currency_code' => 'NGN', 'currency_factor' => null]);
    $line = phase2Line($order, $fixture, ['quantity' => 2, 'unit_cost' => 100]);

    $order->recalculateTotals();
    $order->refresh();
    $line->refresh();

    expect($order->resolvedCurrencyFactor())->toBe('1.000000')
        ->and((float) $line->line_total)->toBe(200.0)
        ->and((float) $line->unit_cost_lcy)->toBe(100.0)
        ->and((float) $line->line_total_lcy)->toBe(200.0)
        ->and((float) $order->total_amount)->toBe(200.0)
        ->and((float) $order->total_amount_lcy)->toBe(200.0)
        ->and((float) $order->grand_total_lcy)->toBe(200.0);
});

test('USD purchase order derives LCY from FCY at the document rate', function (): void {
    $fixture = phase2PurchasingFixture();

    $order = phase2Order($fixture, ['currency_code' => 'USD', 'currency_factor' => 1500]);
    $line = phase2Line($order, $fixture, ['quantity' => 1, 'unit_cost' => 180]);

    $order->recalculateTotals();
    $order->refresh();
    $line->refresh();

    expect($order->resolvedCurrencyFactor())->toBe('1500.000000')
        ->and((float) $line->unit_cost)->toBe(180.0)
        ->and((float) $line->unit_cost_lcy)->toBe(270000.0)
        ->and((float) $line->line_total)->toBe(180.0)
        ->and((float) $line->line_total_lcy)->toBe(270000.0)
        ->and((float) $order->grand_total)->toBe(180.0)
        ->and((float) $order->grand_total_lcy)->toBe(270000.0);
});

test('item reference cost is a converted suggestion and never overrides the negotiated vendor price', function (): void {
    $fixture = phase2PurchasingFixture();

    // Reference cost NGN 262,866.20 => USD 175.2441 at 1,500.
    $fixture['item']->update(['standard_cost' => 262866.20]);
    $item = $fixture['item']->fresh();

    $reference = app(PurchasePriceCalculationService::class)->getUnitCost(
        $fixture['vendor'],
        $item,
        1,
        'PCS',
        null,
        null,
        'USD',
        1500
    );

    expect($reference['price_source'])->toBe('standard_cost')
        ->and($reference['reference_derived'])->toBeTrue()
        ->and((float) $reference['direct_unit_cost'])->toBe(175.24);

    // A negotiated vendor price is authoritative even though the converted
    // reference cost is numerically lower.
    PurchasePrice::query()->create([
        'vendor_id' => $fixture['vendor']->id,
        'item_id' => $item->id,
        'minimum_quantity' => 1,
        'direct_unit_cost' => 180,
        'currency_code' => 'USD',
        'unit_of_measure_code' => 'PCS',
    ]);

    $negotiated = app(PurchasePriceCalculationService::class)->getUnitCost(
        $fixture['vendor'],
        $item,
        1,
        'PCS',
        null,
        null,
        'USD',
        1500
    );

    expect($negotiated['price_source'])->toBe('purchase_price')
        ->and($negotiated['reference_derived'])->toBeFalse()
        ->and((float) $negotiated['direct_unit_cost'])->toBe(180.0);

    $order = phase2Order($fixture, ['currency_code' => 'USD', 'currency_factor' => 1500]);
    $line = phase2Line($order, $fixture, ['quantity' => 1, 'unit_cost' => 180]);

    expect((float) $line->unit_cost)->toBe(180.0)
        ->and((float) $line->unit_cost_lcy)->toBe(270000.0);
});

test('foreign-currency purchase order without a rate fails closed at document calculation', function (): void {
    $fixture = phase2PurchasingFixture();

    $order = phase2Order($fixture, ['currency_code' => 'USD', 'currency_factor' => null]);
    $line = phase2Line($order, $fixture, ['quantity' => 1, 'unit_cost' => 180, 'received_quantity' => 1]);

    // The line could not be converted, so LCY remains unset...
    expect($line->unit_cost_lcy)->toBeNull();

    // ...and document-level calculation refuses to guess.
    expect(fn () => $order->recalculateTotals())->toThrow(InvalidArgumentException::class);
    expect(fn () => app(PurchaseInvoiceService::class)->createFromOrder($order))
        ->toThrow(InvalidArgumentException::class);
});

test('purchase receipt inherits the PO rate and preserves both FCY and LCY line values', function (): void {
    $fixture = phase2PurchasingFixture();

    $order = phase2Order($fixture, ['currency_code' => 'USD', 'currency_factor' => 1500]);
    phase2Line($order, $fixture, ['quantity' => 1, 'unit_cost' => 180, 'received_quantity' => 0]);

    $receipt = PurchaseReceipt::query()->create([
        'document_number' => 'PR-PHASE2-001',
        'vendor_id' => $fixture['vendor']->id,
        'purchase_order_id' => $order->id,
        'purchase_order_no' => $order->order_number,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'currency_code' => $order->currency_code,
        'exchange_rate' => $order->resolvedCurrencyFactor(),
    ]);

    $created = app(PurchaseReceiptLinePrefillService::class)->prefillFromPurchaseOrder($receipt);

    $receiptLine = $receipt->fresh()->lines()->firstOrFail();

    expect($created)->toBe(1)
        ->and((string) $receipt->fresh()->currency_code)->toBe('USD')
        ->and((float) $receiptLine->direct_unit_cost)->toBe(180.0)
        ->and((float) $receiptLine->unit_cost_lcy)->toBe(270000.0)
        ->and((float) $receiptLine->line_amount)->toBe(180.0)
        ->and((float) $receiptLine->line_amount_lcy)->toBe(270000.0);
});

test('purchase invoice from a USD order inherits the rate and populates FCY and LCY', function (): void {
    $fixture = phase2PurchasingFixture();
    phase2PurchaseInvoiceNumberSeries();

    $order = phase2Order($fixture, ['currency_code' => 'USD', 'currency_factor' => 1500]);
    phase2Line($order, $fixture, ['quantity' => 1, 'unit_cost' => 180, 'received_quantity' => 1]);

    $invoice = app(PurchaseInvoiceService::class)->createFromOrder($order->fresh('lines'));
    $invoiceLine = $invoice->lines()->firstOrFail();

    expect((string) $invoice->currency_code)->toBe('USD')
        ->and((float) $invoice->currency_factor)->toBe(1500.0)
        ->and((float) $invoiceLine->unit_cost)->toBe(180.0)
        ->and((float) $invoiceLine->unit_cost_lcy)->toBe(270000.0)
        ->and((float) $invoiceLine->line_total)->toBe(180.0)
        ->and((float) $invoiceLine->line_total_lcy)->toBe(270000.0)
        ->and((float) $invoice->total_amount_lcy)->toBe(270000.0)
        ->and((float) $invoice->grand_total_lcy)->toBe(270000.0)
        ->and((float) $invoice->remaining_amount_lcy)->toBe(270000.0);
});

test('posted purchase invoice snapshot copies both the FCY and LCY value sets', function (): void {
    $fixture = phase2PurchasingFixture();
    phase2PurchaseInvoiceNumberSeries();
    $this->actingAs($fixture['user']);

    $order = phase2Order($fixture, ['currency_code' => 'USD', 'currency_factor' => 1500]);
    phase2Line($order, $fixture, ['quantity' => 1, 'unit_cost' => 180, 'received_quantity' => 1]);

    $invoice = app(PurchaseInvoiceService::class)->createFromOrder($order->fresh('lines'));
    $posted = app(PurchaseInvoiceService::class)->post($invoice->fresh());

    $postedLine = $posted->lines()->firstOrFail();

    expect($posted)->toBeInstanceOf(PostedPurchaseInvoice::class)
        ->and((float) $posted->currency_factor)->toBe(1500.0)
        ->and((float) $posted->total_amount_lcy)->toBe(270000.0)
        ->and((float) $posted->grand_total_lcy)->toBe(270000.0)
        ->and((float) $posted->remaining_amount_lcy)->toBe(270000.0)
        ->and((float) $postedLine->unit_cost)->toBe(180.0)
        ->and((float) $postedLine->unit_cost_lcy)->toBe(270000.0)
        ->and((float) $postedLine->line_total)->toBe(180.0)
        ->and((float) $postedLine->line_total_lcy)->toBe(270000.0)
        ->and($invoice->fresh()->status)->toBe(ApprovalStatus::POSTED);
});

test('NGN purchasing regression keeps document and LCY equal through invoice creation', function (): void {
    $fixture = phase2PurchasingFixture();
    phase2PurchaseInvoiceNumberSeries();

    $order = phase2Order($fixture, ['currency_code' => 'NGN', 'currency_factor' => 1]);
    phase2Line($order, $fixture, ['quantity' => 3, 'unit_cost' => 100, 'received_quantity' => 3]);

    $invoice = app(PurchaseInvoiceService::class)->createFromOrder($order->fresh('lines'));
    $invoiceLine = $invoice->lines()->firstOrFail();

    expect((string) $invoice->currency_code)->toBe('NGN')
        ->and((float) $invoice->currency_factor)->toBe(1.0)
        ->and((float) $invoiceLine->unit_cost)->toBe(100.0)
        ->and((float) $invoiceLine->line_total)->toBe(300.0)
        ->and((float) $invoiceLine->line_total_lcy)->toBe(300.0)
        ->and((float) $invoice->grand_total)->toBe(300.0)
        ->and((float) $invoice->grand_total_lcy)->toBe(300.0);
});

test('negotiated vendor price with a matching known currency is used as the document price', function (): void {
    $fixture = phase2PurchasingFixture();

    PurchasePrice::query()->create([
        'vendor_id' => $fixture['vendor']->id,
        'item_id' => $fixture['item']->id,
        'minimum_quantity' => 1,
        'direct_unit_cost' => 250,
        'currency_code' => 'USD',
        'unit_of_measure_code' => 'PCS',
    ]);

    $price = app(PurchasePriceCalculationService::class)->getUnitCost(
        $fixture['vendor'],
        $fixture['item'],
        1,
        'PCS',
        null,
        null,
        'USD',
        1500
    );

    expect($price['price_source'])->toBe('purchase_price')
        ->and($price['price_provenance'])->toBe('known_same_currency')
        ->and($price['negotiated_price_usable'])->toBeTrue()
        ->and((float) $price['direct_unit_cost'])->toBe(250.0);
});

test('negotiated vendor price in a different known currency is not silently converted', function (): void {
    $fixture = phase2PurchasingFixture();
    $fixture['item']->update(['standard_cost' => 300000]);

    PurchasePrice::query()->create([
        'vendor_id' => $fixture['vendor']->id,
        'item_id' => $fixture['item']->id,
        'minimum_quantity' => 1,
        'direct_unit_cost' => 200,
        'currency_code' => 'EUR',
        'unit_of_measure_code' => 'PCS',
    ]);

    $price = app(PurchasePriceCalculationService::class)->getUnitCost(
        $fixture['vendor'],
        $fixture['item']->fresh(),
        1,
        'PCS',
        null,
        null,
        'USD',
        1500
    );

    expect($price['price_provenance'])->toBe('foreign_currency_skipped')
        ->and($price['negotiated_price_usable'])->toBeFalse()
        ->and($price['price_source'])->toBe('standard_cost')
        ->and((float) $price['direct_unit_cost'])->toBe(200.0);
});

test('negotiated vendor price without a currency is never treated as the document currency', function (): void {
    $fixture = phase2PurchasingFixture();

    PurchasePrice::query()->create([
        'vendor_id' => $fixture['vendor']->id,
        'item_id' => $fixture['item']->id,
        'minimum_quantity' => 1,
        'direct_unit_cost' => 200,
        'currency_code' => null,
        'unit_of_measure_code' => 'PCS',
    ]);

    $price = app(PurchasePriceCalculationService::class)->getUnitCost(
        $fixture['vendor'],
        $fixture['item'],
        1,
        'PCS',
        null,
        null,
        'USD',
        1500
    );

    expect($price['price_provenance'])->toBe('unknown_currency_skipped')
        ->and($price['negotiated_price_usable'])->toBeFalse()
        ->and($price['price_source'])->not->toBe('purchase_price');
});

test('a malformed historical foreign invoice with factor one is not trusted as a last price', function (): void {
    $fixture = phase2PurchasingFixture();
    $fixture['item']->update(['standard_cost' => 300000]);

    // Known defect signature: USD labelled, factor 1, mirrored LCY value.
    phase2PostedInvoiceLine($fixture, 'PI-MALFORMED-USD', 'USD', '1', 262866.20, 262866.20);

    $price = app(PurchasePriceCalculationService::class)->getUnitCost(
        $fixture['vendor'],
        $fixture['item']->fresh(),
        1,
        'PCS',
        null,
        null,
        'USD',
        1500
    );

    expect($price['price_source'])->toBe('standard_cost')
        ->and((float) $price['direct_unit_cost'])->toBe(200.0);
});

test('an internally consistent historical dual-currency invoice is reused as a last price', function (): void {
    $fixture = phase2PurchasingFixture();

    // USD 180 at 1,500 => LCY 270,000 (internally consistent).
    phase2PostedInvoiceLine($fixture, 'PI-GOOD-USD', 'USD', '1500', 180, 270000);

    $price = app(PurchasePriceCalculationService::class)->getUnitCost(
        $fixture['vendor'],
        $fixture['item'],
        1,
        'PCS',
        null,
        null,
        'USD',
        1500
    );

    expect($price['price_source'])->toBe('last_direct_cost')
        ->and((float) $price['direct_unit_cost'])->toBe(180.0);
});

test('a mathematically valid foreign rate below one is trusted when internally consistent', function (): void {
    $fixture = phase2PurchasingFixture();

    // A foreign currency worth less than one LCY unit is unusual but valid:
    // LCY = FCY x rate, so 200 FCY at 0.5 => 100 LCY (internally consistent).
    phase2PostedInvoiceLine($fixture, 'PI-LOW-RATE', 'EUR', '0.5', 200, 100);

    $price = app(PurchasePriceCalculationService::class)->getUnitCost(
        $fixture['vendor'],
        $fixture['item'],
        1,
        'PCS',
        null,
        null,
        'EUR',
        0.5
    );

    expect($price['price_source'])->toBe('last_direct_cost')
        ->and((float) $price['direct_unit_cost'])->toBe(200.0);
});

test('a purchase order for a USD vendor defaults to USD when no explicit currency is chosen', function (): void {
    $fixture = phase2PurchasingFixture();
    phase2PurchaseOrderNumberSeries();
    $fixture['vendor']->update(['currency' => 'USD']);

    $order = app(PurchaseOrderService::class)->create(new CreatePurchaseOrderData(
        businessId: null,
        orderType: PurchaseOrderType::PURCHASE_ORDER,
        vendorId: $fixture['vendor']->id,
        orderDate: now(),
        locationId: $fixture['location']->id,
        postingDate: now(),
        dueDate: null,
        deliveryDate: null,
        paymentTerms: null,
        comment: null,
        createdBy: $fixture['user']->id,
        lines: [],
        currencyCode: null,
        currencyFactor: 1500,
    ));

    expect((string) $order->currency_code)->toBe('USD')
        ->and((float) $order->currency_factor)->toBe(1500.0);
});

test('a vendor with no authoritative currency falls back to LCY', function (): void {
    $fixture = phase2PurchasingFixture();
    phase2PurchaseOrderNumberSeries();
    $fixture['vendor']->update(['currency' => '']);

    $order = app(PurchaseOrderService::class)->create(new CreatePurchaseOrderData(
        businessId: null,
        orderType: PurchaseOrderType::PURCHASE_ORDER,
        vendorId: $fixture['vendor']->id,
        orderDate: now(),
        locationId: $fixture['location']->id,
        postingDate: now(),
        dueDate: null,
        deliveryDate: null,
        paymentTerms: null,
        comment: null,
        createdBy: $fixture['user']->id,
        lines: [],
        currencyCode: null,
        currencyFactor: null,
    ));

    expect((string) $order->currency_code)->toBe('NGN')
        ->and((float) $order->currency_factor)->toBe(1.0);
});

test('an explicit document currency overrides the vendor default', function (): void {
    $fixture = phase2PurchasingFixture();
    phase2PurchaseOrderNumberSeries();
    $fixture['vendor']->update(['currency' => 'USD']);

    $order = app(PurchaseOrderService::class)->create(new CreatePurchaseOrderData(
        businessId: null,
        orderType: PurchaseOrderType::PURCHASE_ORDER,
        vendorId: $fixture['vendor']->id,
        orderDate: now(),
        locationId: $fixture['location']->id,
        postingDate: now(),
        dueDate: null,
        deliveryDate: null,
        paymentTerms: null,
        comment: null,
        createdBy: $fixture['user']->id,
        lines: [],
        currencyCode: 'NGN',
        currencyFactor: null,
    ));

    expect((string) $order->currency_code)->toBe('NGN')
        ->and((float) $order->currency_factor)->toBe(1.0);
});

test('changing the exchange rate recalculates LCY values but never the negotiated FCY price', function (): void {
    $fixture = phase2PurchasingFixture();

    $order = phase2Order($fixture, ['currency_code' => 'USD', 'currency_factor' => 1500]);
    $line = phase2Line($order, $fixture, ['quantity' => 1, 'unit_cost' => 180]);

    $order->recalculateTotals();

    $order->update(['currency_factor' => 1600]);
    $order->refresh();
    $line->refresh();

    expect((float) $line->unit_cost)->toBe(180.0)
        ->and((float) $line->line_total)->toBe(180.0)
        ->and((float) $line->unit_cost_lcy)->toBe(288000.0)
        ->and((float) $line->line_total_lcy)->toBe(288000.0)
        ->and((float) $order->grand_total)->toBe(180.0)
        ->and((float) $order->grand_total_lcy)->toBe(288000.0);
});

function phase2PostedInvoiceLine(
    array $fixture,
    string $documentNumber,
    string $currencyCode,
    string $factor,
    float $unitCost,
    float $unitCostLcy
): PostedPurchaseInvoice {
    $invoice = PostedPurchaseInvoice::query()->create([
        'business_id' => $fixture['business']->id,
        'document_number' => $documentNumber,
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'general_business_posting_group_id' => $fixture['vendor']->general_business_posting_group_id,
        'vendor_posting_group_id' => $fixture['vendor']->vendor_posting_group_id,
        'location_id' => null,
        'posting_date' => '2026-06-01',
        'document_date' => '2026-06-01',
        'due_date' => '2026-06-01',
        'total_amount' => $unitCost,
        'total_vat' => 0,
        'grand_total' => $unitCost,
        'currency_code' => $currencyCode,
        'currency_factor' => $factor,
        'amount_paid' => 0,
        'remaining_amount' => $unitCost,
        'paid_in_full' => false,
        'posted_by' => null,
        'posted_at' => now(),
        'cancelled' => false,
    ]);

    $invoice->lines()->create([
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'item_description' => $fixture['item']->description,
        'general_product_posting_group_id' => $fixture['item']->general_product_posting_group_id,
        'inventory_posting_group_id' => $fixture['item']->inventory_posting_group_id,
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => 1,
        'unit_cost' => $unitCost,
        'unit_cost_lcy' => $unitCostLcy,
        'line_total' => $unitCost,
        'line_discount_amount' => 0,
        'line_discount_percent' => 0,
        'vat_code' => null,
        'vat_percentage' => 0,
        'vat_amount' => 0,
        'vat_amount_lcy' => 0,
        'amount_including_vat' => $unitCost,
        'amount_including_vat_lcy' => $unitCostLcy,
        'line_number' => 10000,
        'posting_date' => '2026-06-01',
    ]);

    return $invoice;
}

function phase2PurchasingFixture(): array
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
    $location = Location::factory()->create(['code' => 'MAIN']);
    $business = Business::query()->create(['code' => 'BUS-P2', 'name' => 'Phase 2 Business', 'is_active' => true]);

    $payablesAccount = phase2Account('2100', 'Accounts Payable', 'payable', IncomeBalanceType::BALANCE_SHEET);
    $inventoryAccount = phase2Account('1200', 'Inventory', 'inventory', IncomeBalanceType::BALANCE_SHEET);
    $purchaseAccount = phase2Account('5100', 'Purchases', 'direct_expense', IncomeBalanceType::INCOME_STATEMENT);

    $businessGroup = GeneralBusinessPostingGroup::query()->create(['code' => 'DOMESTIC', 'description' => 'Domestic', 'blocked' => false]);
    $productGroup = GeneralProductPostingGroup::query()->create(['code' => 'FINISHED', 'description' => 'Finished Goods', 'blocked' => false]);
    $inventoryGroup = InventoryPostingGroup::query()->create(['code' => 'FINISHED', 'description' => 'Finished Goods', 'blocked' => false]);

    $vendorPostingGroup = VendorPostingGroup::query()->create([
        'code' => 'DOMESTIC',
        'description' => 'Domestic Vendors',
        'payables_account_id' => $payablesAccount->id,
        'blocked' => false,
    ]);

    InventoryPostingSetup::query()->create([
        'inventory_posting_group_id' => $inventoryGroup->id,
        'location_id' => null,
        'inventory_account_id' => $inventoryAccount->id,
    ]);

    GeneralPostingSetup::query()->create([
        'general_business_posting_group_id' => $businessGroup->id,
        'general_product_posting_group_id' => $productGroup->id,
        'purchase_account_id' => $purchaseAccount->id,
        'blocked' => false,
    ]);

    $baseUom = UnitOfMeasure::query()->create(['uom_code' => 'PCS', 'description' => 'Pieces', 'is_base_uom' => true]);

    $item = Item::query()->create([
        'item_code' => 'RM-PHASE2',
        'description' => 'Phase 2 Item',
        'item_type' => ItemType::RAW_MATERIAL,
        'base_uom_id' => $baseUom->id,
        'unit_cost' => 10,
        'inventory' => 0,
        'location_id' => $location->id,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
    ]);

    $vendor = Vendor::factory()->create([
        'general_business_posting_group_id' => $businessGroup->id,
        'vendor_posting_group_id' => $vendorPostingGroup->id,
        'vat_bus_posting_group' => null,
    ]);

    return compact('user', 'vendor', 'item', 'location', 'business');
}

function phase2Account(string $number, string $name, string $category, IncomeBalanceType $incomeBalance): ChartOfAccount
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

function phase2Order(array $fixture, array $overrides): PurchaseOrder
{
    return PurchaseOrder::query()->create(array_merge([
        'order_number' => 'PO-P2-'.substr(uniqid(), -8),
        'status' => PurchaseOrderStatus::APPROVED,
        'business_id' => $fixture['business']->id,
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'order_date' => now()->toDateString(),
        'posting_date' => now()->toDateString(),
        'location_id' => $fixture['location']->id,
        'payment_terms' => 30,
        'general_business_posting_group_id' => $fixture['vendor']->general_business_posting_group_id,
        'vendor_posting_group_id' => $fixture['vendor']->vendor_posting_group_id,
        'created_by' => $fixture['user']->id,
    ], $overrides));
}

function phase2Line(PurchaseOrder $order, array $fixture, array $overrides): PurchaseOrderLine
{
    return $order->lines()->create(array_merge([
        'line_number' => 10000,
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'description' => $fixture['item']->description,
        'quantity' => 1,
        'received_quantity' => 0,
        'invoiced_quantity' => 0,
        'unit_of_measure' => 'PCS',
        'unit_cost' => 100,
        'general_product_posting_group_id' => $fixture['item']->general_product_posting_group_id,
    ], $overrides));
}

function phase2PurchaseInvoiceNumberSeries(): NumberSeries
{
    $series = NumberSeries::query()->updateOrCreate(
        ['code' => 'P-INV'],
        [
            'description' => 'Phase 2 purchase invoice series',
            'prefix' => 'PINV-',
            'starting_number' => 1,
            'ending_number' => null,
            'current_number' => 0,
            'year' => 2026,
            'is_active' => true,
            'allow_manual' => false,
            'module' => 'purchase',
        ],
    );

    $series->lines()->delete();

    NumberSeriesLine::query()->create([
        'number_series_id' => $series->id,
        'starting_date' => '2026-01-01',
        'starting_no' => 0,
        'ending_no' => null,
        'increment_by' => 1,
        'last_no_used' => 0,
        'no_of_digits' => 6,
        'prefix' => 'PINV-',
        'suffix' => '',
        'blocked' => false,
    ]);

    return $series->fresh('lines');
}

function phase2PurchaseOrderNumberSeries(): NumberSeries
{
    $series = NumberSeries::query()->updateOrCreate(
        ['code' => 'PURCHASE'],
        [
            'description' => 'Phase 2 purchase order series',
            'prefix' => 'PO-',
            'starting_number' => 1,
            'ending_number' => null,
            'current_number' => 0,
            'year' => 2026,
            'is_active' => true,
            'allow_manual' => false,
            'module' => 'purchase',
        ],
    );

    $series->lines()->delete();

    NumberSeriesLine::query()->create([
        'number_series_id' => $series->id,
        'starting_date' => '2026-01-01',
        'starting_no' => 0,
        'ending_no' => null,
        'increment_by' => 1,
        'last_no_used' => 0,
        'no_of_digits' => 6,
        'prefix' => 'PO-',
        'suffix' => '',
        'blocked' => false,
    ]);

    return $series->fresh('lines');
}
