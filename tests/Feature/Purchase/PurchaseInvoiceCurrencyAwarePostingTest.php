<?php

declare(strict_types=1);

use App\Enums\ApprovalStatus;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemLedgerEntryType;
use App\Enums\ItemType;
use App\Enums\PostingIntentLineType;
use App\Enums\PostingLcyOnlyReason;
use App\Enums\PurchaseOrderStatus;
use App\Enums\SourceType;
use App\Exceptions\BusinessException;
use App\Models\AccountingPeriod;
use App\Models\Business;
use App\Models\ChartOfAccount;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralLedgerSetup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\GlEntry;
use App\Models\InventoryPostingGroup;
use App\Models\InventoryPostingSetup;
use App\Models\Item;
use App\Models\ItemLedgerEntry;
use App\Models\Location;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\PostingTransaction;
use App\Models\PurchaseCreditMemo;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\ValueEntry;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPostingGroup;
use App\Services\Purchase\PurchaseInvoiceService;
use App\Services\Purchase\PurchaseOrderService;
use App\Services\Purchases\PurchaseCreditMemoService;
use App\Support\PurchasingCurrency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('liability posting for a foreign invoice carries an explicit document trace and balanced LCY', function (): void {
    $fixture = pcaFixture();
    pcaInvoiceNumberSeries();
    $this->actingAs($fixture['user']);

    $order = pcaOrder($fixture, ['currency_code' => 'USD', 'currency_factor' => 1500]);
    pcaLine($order, $fixture, ['quantity' => 1, 'received_quantity' => 1, 'unit_cost' => 180]);

    $invoice = app(PurchaseInvoiceService::class)->createFromOrder($order->fresh('lines'));
    app(PurchaseInvoiceService::class)->post($invoice);

    $clearing = pcaGlEntryForAccount($invoice, $fixture['clearingAccount']->id);
    $payable = pcaGlEntryForAccount($invoice, $fixture['payablesAccount']->id);

    expect($clearing->posting_line_type)->toBe(PostingIntentLineType::DOCUMENT_MONETARY->value)
        ->and($clearing->document_currency_code)->toBe('USD')
        ->and((float) $clearing->currency_factor)->toBe(1500.0)
        // Base debit/credit are LCY; the document-currency (FCY) amount is the trace.
        ->and((float) $clearing->debit_amount)->toBe(270000.0)
        ->and((float) $clearing->debit_amount_lcy)->toBe(270000.0)
        ->and((float) $clearing->document_debit_amount)->toBe(180.0)
        ->and($payable->posting_line_type)->toBe(PostingIntentLineType::DOCUMENT_MONETARY->value)
        ->and((float) $payable->credit_amount)->toBe(270000.0)
        ->and((float) $payable->credit_amount_lcy)->toBe(270000.0)
        ->and((float) $payable->document_credit_amount)->toBe(180.0);

    $invoiceGl = GlEntry::query()->where('document_number', $invoice->document_number);

    expect(round((float) $invoiceGl->sum('debit_amount_lcy'), 2))
        ->toBe(round((float) $invoiceGl->sum('credit_amount_lcy'), 2))
        ->and($invoiceGl->where('posting_line_type', PostingIntentLineType::LCY_ONLY->value)->count())->toBe(0)
        // Purchase clearing nets to zero between the liability debit and the
        // inventory valuation credit.
        ->and(round(pcaAccountNet($invoice->document_number, $fixture['clearingAccount']->id), 2))->toBe(0.0);
});

test('a receipt-backed foreign invoice values inventory at the LCY accounting value', function (): void {
    $fixture = pcaFixture();
    pcaInvoiceNumberSeries();
    $this->actingAs($fixture['user']);

    $order = pcaOrder($fixture, ['currency_code' => 'USD', 'currency_factor' => 1500]);
    pcaLine($order, $fixture, ['quantity' => 1, 'received_quantity' => 0, 'unit_cost' => 180]);

    app(PurchaseOrderService::class)->postReceipt($order->fresh());

    $receiptEntry = ItemLedgerEntry::query()
        ->where('document_type', 'PURCHASE_RECEIPT')
        ->where('document_number', $order->order_number)
        ->firstOrFail();

    $invoice = app(PurchaseInvoiceService::class)->createFromOrder($order->fresh('lines'));
    app(PurchaseInvoiceService::class)->post($invoice);

    $actualValueEntry = ValueEntry::query()
        ->where('document_no', $invoice->document_number)
        ->where('value_entry_state', 'actual')
        ->firstOrFail();

    expect((float) $actualValueEntry->cost_amount_actual)->toBe(270000.0)
        ->and((float) $receiptEntry->fresh()->cost_amount_actual)->toBe(270000.0)
        ->and((float) $receiptEntry->fresh()->purchase_amount_actual)->toBe(270000.0)
        // Inventory valuation is LCY: the inventory G/L leg is the LCY value...
        ->and(round(pcaAccountNet($invoice->document_number, $fixture['inventoryAccount']->id), 2))->toBe(270000.0)
        // ...and the clearing legs net to zero.
        ->and(round(pcaAccountNet($invoice->document_number, $fixture['clearingAccount']->id), 2))->toBe(0.0);
});

test('NGN purchase invoice posting stays balanced and economically unchanged', function (): void {
    $fixture = pcaFixture();
    pcaInvoiceNumberSeries();
    $this->actingAs($fixture['user']);

    $order = pcaOrder($fixture, ['currency_code' => 'NGN', 'currency_factor' => 1]);
    pcaLine($order, $fixture, ['quantity' => 3, 'received_quantity' => 3, 'unit_cost' => 100]);

    $invoice = app(PurchaseInvoiceService::class)->createFromOrder($order->fresh('lines'));
    app(PurchaseInvoiceService::class)->post($invoice);

    $clearing = pcaGlEntryForAccount($invoice, $fixture['clearingAccount']->id);
    $payable = pcaGlEntryForAccount($invoice, $fixture['payablesAccount']->id);

    expect($clearing->document_currency_code)->toBe('NGN')
        ->and((float) $clearing->debit_amount_lcy)->toBe(300.0)
        ->and((float) $payable->credit_amount_lcy)->toBe(300.0)
        ->and((float) $invoice->fresh()->grand_total_lcy)->toBe(300.0);

    $invoiceGl = GlEntry::query()->where('document_number', $invoice->document_number);

    expect(round((float) $invoiceGl->sum('debit_amount_lcy'), 2))
        ->toBe(round((float) $invoiceGl->sum('credit_amount_lcy'), 2))
        ->and($invoiceGl->where('posting_line_type', PostingIntentLineType::LCY_ONLY->value)->count())->toBe(0);
});

test('a foreign invoice with an unresolved factor fails closed before any side effect', function (): void {
    $fixture = pcaFixture();
    pcaInvoiceNumberSeries();
    $this->actingAs($fixture['user']);

    $order = pcaOrder($fixture, ['currency_code' => 'USD', 'currency_factor' => 1500]);
    pcaLine($order, $fixture, ['quantity' => 1, 'received_quantity' => 1, 'unit_cost' => 180]);

    $invoice = app(PurchaseInvoiceService::class)->createFromOrder($order->fresh('lines'));

    // Simulate a malformed historical/stored factor (non-positive).
    PurchaseInvoice::query()->whereKey($invoice->id)->update(['currency_factor' => 0]);

    expect(fn () => app(PurchaseInvoiceService::class)->post($invoice->fresh()))
        ->toThrow(BusinessException::class);

    expect(GlEntry::query()->where('document_number', $invoice->document_number)->count())->toBe(0)
        ->and(ValueEntry::query()->where('document_no', $invoice->document_number)->count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe(ApprovalStatus::APPROVED);
});

test('a residual LCY rounding difference is posted as an explicit ROUNDING line', function (): void {
    $fixture = pcaFixture();
    pcaInvoiceNumberSeries();
    $this->actingAs($fixture['user']);

    $order = pcaOrder($fixture, ['currency_code' => 'NGN', 'currency_factor' => 1]);
    pcaLine($order, $fixture, ['line_number' => 10000, 'quantity' => 1, 'received_quantity' => 1, 'unit_cost' => 1.005]);
    pcaLine($order, $fixture, ['line_number' => 20000, 'quantity' => 1, 'received_quantity' => 1, 'unit_cost' => 1.005]);

    $invoice = app(PurchaseInvoiceService::class)->createFromOrder($order->fresh('lines'));
    app(PurchaseInvoiceService::class)->post($invoice);

    $rounding = GlEntry::query()
        ->where('document_number', $invoice->document_number)
        ->where('posting_line_type', PostingIntentLineType::LCY_ONLY->value)
        ->where('lcy_only_reason', PostingLcyOnlyReason::ROUNDING->value)
        ->firstOrFail();

    $invoiceGl = GlEntry::query()->where('document_number', $invoice->document_number);
    $payable = pcaGlEntryForAccount($invoice, $fixture['payablesAccount']->id);

    // Per-line LCY rounding is 2.02 while the grand total rounds to 2.01.
    expect((float) $payable->credit_amount_lcy)->toBe(2.01)
        ->and((float) $rounding->credit_amount)->toBe(0.01)
        ->and((float) $rounding->debit_amount)->toBe(0.0)
        ->and(round((float) $invoiceGl->sum('debit_amount_lcy'), 2))
        ->toBe(round((float) $invoiceGl->sum('credit_amount_lcy'), 2));
});

test('the LCY posting uses the certified accounting rule rather than the wider-scale rounding', function (): void {
    $fixture = pcaFixture();
    pcaInvoiceNumberSeries();
    $this->actingAs($fixture['user']);

    $order = pcaOrder($fixture, ['currency_code' => 'EUR', 'currency_factor' => 0.5]);
    pcaLine($order, $fixture, ['quantity' => 1, 'received_quantity' => 1, 'unit_cost' => 2.0099]);

    $invoice = app(PurchaseInvoiceService::class)->createFromOrder($order->fresh('lines'));
    app(PurchaseInvoiceService::class)->post($invoice);

    $clearing = pcaGlEntryForAccount($invoice, $fixture['clearingAccount']->id);

    // 2.0099 x 0.5 = 1.00495: round-at-amount-scale-then-currency (1.01) must
    // not be what reaches the ledger; the single certified rule yields 1.00.
    expect(PurchasingCurrency::lcyFromFcy('2.0099', '0.5'))->toBe('1.01')
        ->and(PurchasingCurrency::accountingLcy('2.0099', '0.5'))->toBe('1.00')
        ->and((float) $invoice->fresh()->lines()->firstOrFail()->line_total_lcy)->toBe(1.0)
        ->and((float) $clearing->debit_amount_lcy)->toBe(1.0);
});

test('a receipt-backed foreign invoice is fenced when expected-cost G/L posting is enabled', function (): void {
    config(['accounts.post_expected_inventory_cost_to_gl' => true]);

    $fixture = pcaFixture();
    pcaInvoiceNumberSeries();
    $this->actingAs($fixture['user']);

    $order = pcaOrder($fixture, ['currency_code' => 'USD', 'currency_factor' => 1500]);
    pcaLine($order, $fixture, ['quantity' => 1, 'received_quantity' => 0, 'unit_cost' => 180]);

    app(PurchaseOrderService::class)->postReceipt($order->fresh());

    $invoice = app(PurchaseInvoiceService::class)->createFromOrder($order->fresh('lines'));

    expect(fn () => app(PurchaseInvoiceService::class)->post($invoice))
        ->toThrow(BusinessException::class, 'expected inventory cost G/L posting is enabled');

    expect(ValueEntry::query()
        ->where('document_no', $invoice->document_number)
        ->where('value_entry_state', 'actual')
        ->count())->toBe(0)
        ->and(GlEntry::query()->where('document_number', $invoice->document_number)->count())->toBe(0)
        ->and(ItemLedgerEntry::query()
            ->where('document_type', 'PURCHASE_INVOICE')
            ->where('document_number', $invoice->document_number)
            ->count())->toBe(0);
});

test('a foreign-currency purchase credit memo is fenced from posting', function (): void {
    $fixture = pcaFixture();
    pcaGrantCreditMemoPostPermission($fixture['user']);
    $this->actingAs($fixture['user']);

    $memo = PurchaseCreditMemo::query()->create([
        'document_number' => 'PCM-FCY-FENCE-001',
        'vendor_id' => $fixture['vendor']->id,
        'vendor_name' => $fixture['vendor']->vendor_name,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'location_id' => $fixture['location']->id,
        'status' => ApprovalStatus::APPROVED,
        'currency_code' => 'USD',
        'description' => 'Foreign return to vendor',
    ]);

    $memo->lines()->create([
        'line_number' => 10000,
        'item_id' => $fixture['item']->id,
        'item_code' => $fixture['item']->item_code,
        'description' => $fixture['item']->description,
        'quantity' => 1,
        'unit_cost' => 180,
        'tax_percent' => 0,
        'general_product_posting_group_id' => $fixture['item']->general_product_posting_group_id,
        'unit_of_measure_code' => 'PCS',
    ]);

    expect(fn () => app(PurchaseCreditMemoService::class)->post($memo))
        ->toThrow(BusinessException::class, 'Foreign-currency purchase credit memo');
});

test('the A/P control G/L and the vendor ledger recognise the same authoritative LCY amount', function (string $currency, float $factor, float $unitCost, float $expectedLcy): void {
    $fixture = pcaFixture();
    pcaInvoiceNumberSeries();
    $this->actingAs($fixture['user']);

    $order = pcaOrder($fixture, ['currency_code' => $currency, 'currency_factor' => $factor]);
    pcaLine($order, $fixture, ['quantity' => 1, 'received_quantity' => 1, 'unit_cost' => $unitCost]);

    $invoice = app(PurchaseInvoiceService::class)->createFromOrder($order->fresh('lines'));
    app(PurchaseInvoiceService::class)->post($invoice);

    $payableGl = pcaGlEntryForAccount($invoice, $fixture['payablesAccount']->id);
    $vendorEntry = pcaVendorLedgerEntry($invoice);

    expect((float) $payableGl->credit_amount_lcy)->toBe($expectedLcy)
        ->and((float) $vendorEntry->credit_amount)->toBe($expectedLcy)
        ->and((float) $vendorEntry->amount)->toBe($expectedLcy)
        ->and((float) $vendorEntry->remaining_amount)->toBe($expectedLcy)
        // Exact writer equality at the authoritative control-account scale; the
        // reconciler's tolerance is a detector, not the accounting contract.
        ->and(round((float) $vendorEntry->credit_amount - (float) $payableGl->credit_amount_lcy, 2))->toBe(0.0)
        ->and((float) $vendorEntry->original_credit_amount)->toBe(round($unitCost, 4));
})->with([
    'USD at 1500' => ['USD', 1500.0, 180.0, 270000.0],
    'factor below one' => ['EUR', 0.5, 200.0, 100.0],
    'six-decimal factor' => ['USD', 0.123456, 220.0, 27.16],
    'double-rounding edge' => ['USD', 0.5, 2.0099, 1.0],
]);

test('a foreign line received in two chunks leaves no rounding residual in purchase clearing', function (): void {
    $fixture = pcaFixture();
    pcaInvoiceNumberSeries();
    $this->actingAs($fixture['user']);

    $order = pcaOrder($fixture, ['currency_code' => 'USD', 'currency_factor' => 0.5]);
    $line = pcaLine($order, $fixture, ['quantity' => 2, 'received_quantity' => 1, 'unit_cost' => 0.01]);

    app(PurchaseOrderService::class)->postReceipt($order->fresh());
    $line->fresh()->update(['received_quantity' => 2]);
    app(PurchaseOrderService::class)->postReceipt($order->fresh());

    $invoice = app(PurchaseInvoiceService::class)->createFromOrder($order->fresh('lines'));
    app(PurchaseInvoiceService::class)->post($invoice);

    // Two independent receipt chunks were each rounded to 0.01 (0.02 total),
    // while the authoritative two-unit line LCY is 0.01. Cumulative allocation
    // must place the whole 0.01 on one chunk and 0.00 on the other.
    expect(PurchasingCurrency::accountingLcy('0.02', '0.5'))->toBe('0.01')
        ->and(round(pcaAccountNet($invoice->document_number, $fixture['clearingAccount']->id), 2))->toBe(0.0)
        ->and(round(pcaAccountNet($invoice->document_number, $fixture['inventoryAccount']->id), 2))->toBe(0.01)
        ->and((float) ValueEntry::query()
            ->where('document_no', $invoice->document_number)
            ->where('value_entry_state', 'actual')
            ->sum('cost_amount_actual'))->toBe(0.01)
        ->and((float) ItemLedgerEntry::query()
            ->where('entry_type', ItemLedgerEntryType::PURCHASE)
            ->where('document_type', 'PURCHASE_RECEIPT')
            ->where('document_number', $order->order_number)
            ->sum('cost_amount_actual'))->toBe(0.01);
});

test('a foreign line received in three chunks clears exactly', function (): void {
    $fixture = pcaFixture();
    pcaInvoiceNumberSeries();
    $this->actingAs($fixture['user']);

    $order = pcaOrder($fixture, ['currency_code' => 'USD', 'currency_factor' => 0.5]);
    $line = pcaLine($order, $fixture, ['quantity' => 3, 'received_quantity' => 1, 'unit_cost' => 0.01]);

    app(PurchaseOrderService::class)->postReceipt($order->fresh());
    $line->fresh()->update(['received_quantity' => 2]);
    app(PurchaseOrderService::class)->postReceipt($order->fresh());
    $line->fresh()->update(['received_quantity' => 3]);
    app(PurchaseOrderService::class)->postReceipt($order->fresh());

    $invoice = app(PurchaseInvoiceService::class)->createFromOrder($order->fresh('lines'));
    app(PurchaseInvoiceService::class)->post($invoice);

    expect(PurchasingCurrency::accountingLcy('0.03', '0.5'))->toBe('0.02')
        ->and(round(pcaAccountNet($invoice->document_number, $fixture['clearingAccount']->id), 2))->toBe(0.0)
        ->and(round(pcaAccountNet($invoice->document_number, $fixture['inventoryAccount']->id), 2))->toBe(0.02);
});

test('a six-decimal-factor foreign line received in two chunks clears exactly', function (): void {
    $fixture = pcaFixture();
    pcaInvoiceNumberSeries();
    $this->actingAs($fixture['user']);

    $order = pcaOrder($fixture, ['currency_code' => 'USD', 'currency_factor' => 0.123456]);
    $line = pcaLine($order, $fixture, ['quantity' => 2, 'received_quantity' => 1, 'unit_cost' => 100]);

    app(PurchaseOrderService::class)->postReceipt($order->fresh());
    $line->fresh()->update(['received_quantity' => 2]);
    app(PurchaseOrderService::class)->postReceipt($order->fresh());

    $invoice = app(PurchaseInvoiceService::class)->createFromOrder($order->fresh('lines'));
    app(PurchaseInvoiceService::class)->post($invoice);

    expect(PurchasingCurrency::accountingLcy('200', '0.123456'))->toBe('24.69')
        ->and(round(pcaAccountNet($invoice->document_number, $fixture['clearingAccount']->id), 2))->toBe(0.0)
        ->and(round(pcaAccountNet($invoice->document_number, $fixture['inventoryAccount']->id), 2))->toBe(24.69);
});

test('a partially invoiced foreign line stays exact across progressive invoices', function (): void {
    $fixture = pcaFixture();
    pcaInvoiceNumberSeries();
    $this->actingAs($fixture['user']);

    $order = pcaOrder($fixture, ['currency_code' => 'USD', 'currency_factor' => 0.5]);
    $line = pcaLine($order, $fixture, ['quantity' => 3, 'received_quantity' => 2, 'unit_cost' => 0.01]);

    app(PurchaseOrderService::class)->postReceipt($order->fresh());
    $line->fresh()->update(['received_quantity' => 3]);
    app(PurchaseOrderService::class)->postReceipt($order->fresh());

    // First invoice bills only the two units in the first receipt chunk.
    $line->fresh()->update(['received_quantity' => 2]);
    $firstInvoice = app(PurchaseInvoiceService::class)->createFromOrder($order->fresh('lines'));
    app(PurchaseInvoiceService::class)->post($firstInvoice);

    // Second invoice bills the remaining unit in the second chunk.
    $line->fresh()->update(['received_quantity' => 3]);
    $secondInvoice = app(PurchaseInvoiceService::class)->createFromOrder($order->fresh('lines'));
    app(PurchaseInvoiceService::class)->post($secondInvoice);

    expect(round(pcaAccountNet($firstInvoice->document_number, $fixture['clearingAccount']->id), 2))->toBe(0.0)
        ->and(round(pcaAccountNet($secondInvoice->document_number, $fixture['clearingAccount']->id), 2))->toBe(0.0)
        // Total inventory valuation equals the authoritative three-unit line LCY.
        ->and(round(
            pcaAccountNet($firstInvoice->document_number, $fixture['inventoryAccount']->id)
            + pcaAccountNet($secondInvoice->document_number, $fixture['inventoryAccount']->id),
            2,
        ))->toBe(0.02)
        ->and(PurchasingCurrency::accountingLcy('0.03', '0.5'))->toBe('0.02');
});

test('a conflicting idempotency fingerprint is rejected during preflight before any side effect', function (): void {
    $fixture = pcaFixture();
    pcaInvoiceNumberSeries();
    $this->actingAs($fixture['user']);

    $order = pcaOrder($fixture, ['currency_code' => 'USD', 'currency_factor' => 1500]);
    pcaLine($order, $fixture, ['quantity' => 1, 'received_quantity' => 1, 'unit_cost' => 180]);

    $invoice = app(PurchaseInvoiceService::class)->createFromOrder($order->fresh('lines'));

    PostingTransaction::query()->create([
        'business_id' => $fixture['business']->id,
        'source_module' => 'purchases',
        'source_type' => SourceType::VENDOR->value,
        'source_id' => $invoice->id,
        'source_number' => $invoice->document_number,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => $invoice->document_number,
        'transaction_key' => "PURCHASE_INVOICE:{$invoice->document_number}:LIABILITY",
        'idempotency_key' => hash('sha256', "purchase-invoice-liability|{$invoice->id}|{$invoice->document_number}"),
        'transaction_number' => 999999,
        'posting_date' => $invoice->posting_date,
        'document_date' => $invoice->document_date,
        'currency_code' => 'USD',
        'exchange_rate' => '1500.000000',
        'economic_fingerprint' => str_repeat('a', 64),
        'status' => 'completed',
        'description' => 'Conflicting pre-existing posting',
    ]);

    expect(fn () => app(PurchaseInvoiceService::class)->post($invoice))
        ->toThrow(ValidationException::class);

    // The conflict is discovered during preflight, before any inventory value,
    // posted snapshot or subledger write.
    expect(ItemLedgerEntry::query()
        ->where('document_type', 'PURCHASE_INVOICE')
        ->where('document_number', $invoice->document_number)
        ->count())->toBe(0)
        ->and(ValueEntry::query()->where('document_no', $invoice->document_number)->count())->toBe(0)
        ->and(GlEntry::query()->where('document_number', $invoice->document_number)->count())->toBe(0)
        ->and(VendorLedgerEntry::query()->where('document_number', $invoice->document_number)->count())->toBe(0)
        ->and($invoice->fresh()->status)->toBe(ApprovalStatus::APPROVED);
});

test('the posted liability transaction carries the frozen pre-side-effect identity', function (): void {
    $fixture = pcaFixture();
    pcaInvoiceNumberSeries();
    $this->actingAs($fixture['user']);

    $order = pcaOrder($fixture, ['currency_code' => 'USD', 'currency_factor' => 1500]);
    pcaLine($order, $fixture, ['quantity' => 1, 'received_quantity' => 1, 'unit_cost' => 180]);

    $invoice = app(PurchaseInvoiceService::class)->createFromOrder($order->fresh('lines'));
    app(PurchaseInvoiceService::class)->post($invoice);

    $payableGl = pcaGlEntryForAccount($invoice, $fixture['payablesAccount']->id);
    $transaction = PostingTransaction::query()
        ->where('idempotency_key', hash('sha256', "purchase-invoice-liability|{$invoice->id}|{$invoice->document_number}"))
        ->firstOrFail();

    expect($transaction->source_id)->toBe($invoice->id)
        ->and($transaction->source_number)->toBe($invoice->document_number)
        ->and($transaction->economic_fingerprint)->not->toBeNull()
        // The generated item-ledger reference is not part of the liability
        // intent, so it cannot mutate the economic fingerprint after preflight.
        ->and($payableGl->item_ledger_entry_id)->toBeNull();
});

function pcaVendorLedgerEntry(PurchaseInvoice $invoice): VendorLedgerEntry
{
    return VendorLedgerEntry::query()
        ->where('document_type', 'PURCHASE_INVOICE')
        ->where('document_number', $invoice->document_number)
        ->where('vendor_id', $invoice->vendor_id)
        ->firstOrFail();
}

function pcaFixture(): array
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
    $business = Business::query()->create(['code' => 'BUS-PCA', 'name' => 'PCA Business', 'is_active' => true]);
    session(['active_business_id' => $business->id]);

    $payablesAccount = pcaAccount('2100', 'Accounts Payable', 'payable', IncomeBalanceType::BALANCE_SHEET);
    $inventoryAccount = pcaAccount('1200', 'Inventory', 'inventory', IncomeBalanceType::BALANCE_SHEET);
    $clearingAccount = pcaAccount('5100', 'Purchase Clearing', 'direct_expense', IncomeBalanceType::INCOME_STATEMENT);
    $roundingAccount = pcaAccount('9999', 'Invoice Rounding', 'direct_expense', IncomeBalanceType::INCOME_STATEMENT);

    $businessGroup = GeneralBusinessPostingGroup::query()->create(['code' => 'DOMESTIC', 'description' => 'Domestic', 'blocked' => false]);
    $productGroup = GeneralProductPostingGroup::query()->create(['code' => 'FINISHED', 'description' => 'Finished Goods', 'blocked' => false]);
    $inventoryGroup = InventoryPostingGroup::query()->create(['code' => 'FINISHED', 'description' => 'Finished Goods', 'blocked' => false]);

    $vendorPostingGroup = VendorPostingGroup::query()->create([
        'code' => 'DOMESTIC',
        'description' => 'Domestic Vendors',
        'payables_account_id' => $payablesAccount->id,
        'invoice_rounding_account_id' => $roundingAccount->id,
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
        'purchase_account_id' => $clearingAccount->id,
        'blocked' => false,
    ]);

    $uom = UnitOfMeasure::query()->create(['uom_code' => 'PCS', 'description' => 'Pieces', 'is_base_uom' => true]);

    $item = Item::query()->create([
        'item_code' => 'RM-PCA',
        'description' => 'PCA Item',
        'item_type' => ItemType::RAW_MATERIAL,
        'base_uom_id' => $uom->id,
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

    return compact(
        'user', 'vendor', 'item', 'location', 'business',
        'payablesAccount', 'inventoryAccount', 'clearingAccount', 'roundingAccount',
        'businessGroup', 'productGroup', 'inventoryGroup', 'vendorPostingGroup',
    );
}

function pcaAccount(string $number, string $name, string $category, IncomeBalanceType $incomeBalance): ChartOfAccount
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

function pcaOrder(array $fixture, array $overrides): PurchaseOrder
{
    return PurchaseOrder::query()->create(array_merge([
        'order_number' => 'PO-PCA-'.substr(uniqid(), -8),
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

function pcaLine(PurchaseOrder $order, array $fixture, array $overrides): PurchaseOrderLine
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

function pcaInvoiceNumberSeries(): NumberSeries
{
    $series = NumberSeries::query()->updateOrCreate(
        ['code' => 'P-INV'],
        [
            'description' => 'PCA purchase invoice series',
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

function pcaGrantCreditMemoPostPermission(User $user): void
{
    Permission::query()->firstOrCreate([
        'name' => 'purchase.credit_memo.post',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo('purchase.credit_memo.post');
}

function pcaGlEntryForAccount(PurchaseInvoice $invoice, int $accountId): GlEntry
{
    $transaction = PostingTransaction::query()
        ->where('idempotency_key', hash('sha256', "purchase-invoice-liability|{$invoice->id}|{$invoice->document_number}"))
        ->firstOrFail();

    return $transaction->glEntries()
        ->where('chart_of_account_id', $accountId)
        ->orderBy('id')
        ->firstOrFail();
}

function pcaAccountNet(string $documentNumber, int $accountId): float
{
    return (float) GlEntry::query()
        ->where('document_number', $documentNumber)
        ->where('chart_of_account_id', $accountId)
        ->sum(DB::raw('debit_amount_lcy - credit_amount_lcy'));
}
