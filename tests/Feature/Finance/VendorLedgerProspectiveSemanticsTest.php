<?php

declare(strict_types=1);

use App\Enums\AccountCategory;
use App\Enums\ApprovalStatus;
use App\Enums\IncomeBalanceType;
use App\Enums\ItemType;
use App\Exceptions\BusinessException;
use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Models\Business;
use App\Models\ChartOfAccount;
use App\Models\Contact;
use App\Models\Currency;
use App\Models\CurrencyAdjustmentLedger;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\CustomerPostingGroup;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralLedgerSetup;
use App\Models\GeneralPostingSetup;
use App\Models\GeneralProductPostingGroup;
use App\Models\GlEntry;
use App\Models\InventoryPostingGroup;
use App\Models\Item;
use App\Models\Location;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\Permission;
use App\Models\PostedPurchaseCreditMemo;
use App\Models\PostedPurchaseInvoice;
use App\Models\PostedSalesInvoice;
use App\Models\PurchaseInvoice;
use App\Models\SubledgerOpeningBalance;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPostingGroup;
use App\Services\BankAccountLedgerService;
use App\Services\CurrencyAdjustmentService;
use App\Services\Dashboard\PurchaseDashboardService;
use App\Services\Finance\PaymentService;
use App\Services\Finance\SubledgerOpeningBalanceService;
use App\Services\Purchase\PurchaseInvoiceService;
use App\Support\LedgerSemantics;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    AccountingPeriod::query()->updateOrCreate(
        ['start_date' => '2026-01-01', 'end_date' => '2026-12-31'],
        ['name' => 'FY2026', 'is_closed' => false],
    );
});

// ---------------------------------------------------------------------------
// Fixtures
// ---------------------------------------------------------------------------

function phase3d1User(array $permissions = []): User
{
    $user = User::factory()->create();

    $permissions = collect(array_merge([
        'finance.payment.apply',
        'finance.payment.unapply',
    ], $permissions))->map(fn (string $name): Permission => Permission::query()->firstOrCreate([
        'name' => $name,
        'guard_name' => 'web',
    ]));

    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->givePermissionTo($permissions->all());
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $user->refresh();

    return $user;
}

function phase3d1Currencies(): array
{
    $gain = ChartOfAccount::factory()->create(['account_number' => '8100', 'name' => 'Realized Gain']);
    $loss = ChartOfAccount::factory()->create(['account_number' => '8200', 'name' => 'Realized Loss']);

    $ngn = Currency::query()->updateOrCreate(['code' => 'NGN'], [
        'description' => 'Nigerian Naira',
        'symbol' => '₦',
        'decimal_places' => 2,
        'is_active' => true,
        'is_lcy' => true,
        'exchange_rate' => 1,
    ]);

    $usd = Currency::query()->updateOrCreate(['code' => 'USD'], [
        'description' => 'US Dollar',
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'is_lcy' => false,
        'exchange_rate' => 1500,
        'realized_gains_account_id' => $gain->id,
        'realized_losses_account_id' => $loss->id,
    ]);

    return compact('ngn', 'usd', 'gain', 'loss');
}

function phase3d1VendorContext(): array
{
    $apAccount = ChartOfAccount::factory()->create([
        'account_number' => '2100',
        'name' => 'Accounts Payable',
        'account_category' => AccountCategory::LIABILITY,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);

    $business = Business::query()->create(['code' => 'B-3D1', 'name' => 'Phase 3D-1 Business']);

    $gbpg = GeneralBusinessPostingGroup::query()->firstOrCreate(
        ['code' => 'DOMESTIC'],
        ['description' => 'Domestic Business'],
    );

    $vpg = VendorPostingGroup::query()->create([
        'code' => 'DOMESTIC',
        'description' => 'Domestic Vendors',
        'payables_account_id' => $apAccount->id,
    ]);

    $contact = Contact::factory()->create();

    $vendor = Vendor::factory()->create([
        'vendor_posting_group_id' => $vpg->id,
        'general_business_posting_group_id' => $gbpg->id,
        'contact_id' => $contact->id,
    ]);

    return compact('apAccount', 'business', 'gbpg', 'vpg', 'vendor');
}

function phase3d1Invoice(int $vendorId, string $number, float $grandTotal, ?int $businessId = null, string $currencyCode = 'USD', ?float $factor = 1500.0, ?int $postedBy = null): PostedPurchaseInvoice
{
    $vendor = Vendor::query()->find($vendorId);

    return PostedPurchaseInvoice::query()->create([
        'business_id' => $businessId,
        'document_number' => $number,
        'vendor_id' => $vendorId,
        'vendor_name' => 'Fixture Vendor',
        'vendor_posting_group_id' => $vendor?->vendor_posting_group_id,
        'general_business_posting_group_id' => $vendor?->general_business_posting_group_id,
        'posting_date' => now()->subDays(5),
        'document_date' => now()->subDays(5),
        'due_date' => now()->addDays(20),
        'currency_code' => $currencyCode,
        'currency_factor' => $factor,
        'grand_total' => $grandTotal,
        'total_amount' => $grandTotal,
        'remaining_amount' => $grandTotal,
        'paid_in_full' => false,
        'posted_by' => $postedBy,
        'posted_at' => now()->subDays(5),
    ]);
}

function phase3d1PostedPayment(array $vendorContext, array $currencies, float $amount, float $factor, int $userId, string $number = 'PAY-3D1'): Payment
{
    $payment = new Payment;
    $payment->payment_number = $number;
    $payment->payment_date = now();
    $payment->posting_date = now();
    $payment->party_type = 'VENDOR';
    $payment->party_id = $vendorContext['vendor']->id;
    $payment->party_name = $vendorContext['vendor']->vendor_name;
    $payment->business_id = $vendorContext['business']->id;
    $payment->payment_method = 'BANK_TRANSFER';
    $payment->payment_amount = $amount;
    $payment->payment_amount_lcy = $amount * $factor;
    $payment->currency_code = $factor === 1.0 ? 'NGN' : 'USD';
    $payment->currency_factor = $factor;
    $payment->currency_id = $factor === 1.0 ? $currencies['ngn']->id : $currencies['usd']->id;
    $payment->status = 'POSTED';
    $payment->payment_direction = 'DISBURSEMENT';
    $payment->unapplied_amount = $amount;
    $payment->created_by = $userId;
    $payment->save();

    return $payment;
}

function phase3d1VendorPaymentLedger(PaymentService $service, Payment $payment, int $userId): VendorLedgerEntry
{
    $method = new ReflectionMethod($service, 'postVendorPayment');
    $method->setAccessible(true);

    /** @var VendorLedgerEntry $entry */
    $entry = $method->invoke($service, $payment, $userId);

    return $entry;
}

function phase3d1ReconcileReport(): array
{
    Artisan::call('biwms:finance-reconcile', ['--json' => true]);

    return json_decode(Artisan::output(), true) ?? [];
}

// ---------------------------------------------------------------------------
// 1. Additive schema: nullable, no default, no backfill
// ---------------------------------------------------------------------------

test('3d1 migration adds nullable no-default ledger semantics columns with no backfill', function (): void {
    foreach (['customer_ledger_entries', 'vendor_ledger_entries'] as $table) {
        expect(Schema::hasColumn($table, 'ledger_semantics_version'))->toBeTrue()
            ->and(Schema::hasColumn($table, 'original_remaining_amount'))->toBeTrue();
    }

    expect(Schema::hasColumn('payment_applications', 'document_amount_applied_lcy'))->toBeTrue();

    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    // A row created without the new columns must stay NULL (no default, no backfill).
    $legacy = VendorLedgerEntry::query()->create([
        'entry_number' => 1,
        'vendor_id' => $vendorContext['vendor']->id,
        'business_id' => $vendorContext['business']->id,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'LEGACY-1',
        'description' => 'legacy row',
        'posting_date' => now(),
        'document_date' => now(),
        'debit_amount' => 0,
        'credit_amount' => 110.0,
        'amount' => 110.0,
        'running_balance' => 110.0,
        'remaining_amount' => 110.0,
        'open' => true,
        'currency_id' => $currencies['usd']->id,
        'currency_code' => 'USD',
        'currency_factor' => 1100.0,
        'original_debit_amount' => 0,
        'original_credit_amount' => 0.1,
        'created_by' => $user->id,
    ]);

    $legacy->refresh();

    expect($legacy->ledger_semantics_version)->toBeNull()
        ->and($legacy->original_remaining_amount)->toBeNull()
        ->and($legacy->is_version_two)->toBeFalse();
});

test('3d1 legacy vendor rows keep their existing base-column interpretation', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    $legacy = VendorLedgerEntry::query()->create([
        'entry_number' => 1,
        'vendor_id' => $vendorContext['vendor']->id,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'LEGACY-2',
        'description' => 'legacy row',
        'posting_date' => now(),
        'document_date' => now(),
        'debit_amount' => 0,
        'credit_amount' => 220.0,   // document currency
        'amount' => 220.0,
        'running_balance' => 220.0,
        'remaining_amount' => 220.0,
        'open' => true,
        'currency_id' => $currencies['usd']->id,
        'currency_code' => 'USD',
        'currency_factor' => 1500.0,
        'original_debit_amount' => 0,
        'original_credit_amount' => 220.0,
        'created_by' => $user->id,
    ]);

    // Base columns are untouched document-currency values.
    expect((float) $legacy->remaining_amount)->toBe(220.0)
        ->and($legacy->ledger_semantics_version)->toBeNull()
        // Legacy conversion to LCY is explicit and trusted (220 x 1500).
        ->and($legacy->lcy_remaining_amount)->toBe(330000.0)
        ->and($legacy->document_remaining_amount)->toBe(220.0);
});

// ---------------------------------------------------------------------------
// 2. Version-2 vendor purchase invoice
// ---------------------------------------------------------------------------

test('3d1 NGN purchase invoice writes a version-2 LCY vendor ledger entry with factor 1', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    $invoice = phase3d1Invoice(
        $vendorContext['vendor']->id,
        'PI-NGN-1',
        250_000.0,
        $vendorContext['business']->id,
        'NGN',
        1.0,
        $user->id,
    );

    $entry = VendorLedgerEntry::createFromInvoice($invoice);

    expect($entry->ledger_semantics_version)->toBe(2)
        ->and($entry->is_version_two)->toBeTrue()
        ->and((float) $entry->credit_amount)->toBe(250000.0)
        ->and((float) $entry->debit_amount)->toBe(0.0)
        ->and((float) $entry->amount)->toBe(250000.0)
        ->and((float) $entry->remaining_amount)->toBe(250000.0)
        ->and((float) $entry->original_credit_amount)->toBe(250000.0)
        ->and((float) $entry->original_remaining_amount)->toBe(250000.0)
        ->and((float) $entry->currency_factor)->toBe(1.0)
        ->and($entry->currency_code)->toBe('NGN');
});

test('3d1 USD purchase invoice of FCY 220 at 1500 writes LCY 330000 and FCY originals', function (): void {
    $vendorContext = phase3d1VendorContext();
    $user = phase3d1User();

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-USD-1500', 220.0, $vendorContext['business']->id, 'USD', 1500.0, $user->id);

    $entry = VendorLedgerEntry::createFromInvoice($invoice);

    expect($entry->ledger_semantics_version)->toBe(2)
        ->and((float) $entry->credit_amount)->toBe(330000.0)
        ->and((float) $entry->amount)->toBe(330000.0)
        ->and((float) $entry->remaining_amount)->toBe(330000.0)
        ->and((float) $entry->original_credit_amount)->toBe(220.0)
        ->and((float) $entry->original_remaining_amount)->toBe(220.0);
});

test('3d1 a foreign factor below 1 is valid and converts correctly', function (): void {
    $vendorContext = phase3d1VendorContext();
    $user = phase3d1User();

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-EUR-HALF', 200.0, $vendorContext['business']->id, 'EUR', 0.5, $user->id);

    $entry = VendorLedgerEntry::createFromInvoice($invoice);

    expect($entry->ledger_semantics_version)->toBe(2)
        ->and((float) $entry->currency_factor)->toBe(0.5)
        ->and((float) $entry->credit_amount)->toBe(100.0)
        ->and((float) $entry->remaining_amount)->toBe(100.0)
        ->and((float) $entry->original_credit_amount)->toBe(200.0)
        ->and((float) $entry->original_remaining_amount)->toBe(200.0);
});

test('3d1 a six-decimal fractional factor is persisted at factor scale and converts reproducibly', function (): void {
    $vendorContext = phase3d1VendorContext();
    $user = phase3d1User();

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-FRACTION', 220.0, $vendorContext['business']->id, 'USD', 12.345678, $user->id);

    $entry = VendorLedgerEntry::createFromInvoice($invoice);

    $expected = (float) LedgerSemantics::lcyFromDocument(220.0, '12.345678');

    expect($entry->ledger_semantics_version)->toBe(2)
        ->and((float) $entry->currency_factor)->toBe(12.345678)
        ->and((float) $entry->credit_amount)->toBe($expected)
        ->and((float) $entry->remaining_amount)->toBe($expected)
        ->and((float) $entry->original_credit_amount)->toBe(220.0);
});

test('3d1 original columns hold the document amount, never amount divided by factor', function (): void {
    $vendorContext = phase3d1VendorContext();
    $user = phase3d1User();

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-ORIG', 220.0, $vendorContext['business']->id, 'USD', 1500.0, $user->id);

    $entry = VendorLedgerEntry::createFromInvoice($invoice);

    // The legacy defect stored 220 / 1500 = 0.1467.
    expect((float) $entry->original_credit_amount)->toBe(220.0)
        ->and((float) $entry->original_credit_amount)->not->toBe(round(220.0 / 1500.0, 4))
        ->and((float) $entry->original_debit_amount)->toBe(0.0)
        ->and($entry->currency_code)->toBe('USD');
});

// ---------------------------------------------------------------------------
// 3. Version-2 vendor credit memo
// ---------------------------------------------------------------------------

test('3d1 a posted vendor credit memo writes a version-2 debit-side LCY entry with FCY originals', function (): void {
    $vendorContext = phase3d1VendorContext();
    $user = phase3d1User();
    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-CM-BASE', 220.0, $vendorContext['business']->id, 'USD', 1500.0, $user->id);
    VendorLedgerEntry::createFromInvoice($invoice);

    $memo = PostedPurchaseCreditMemo::query()->create([
        'business_id' => $vendorContext['business']->id,
        'document_number' => 'PCM-3D1',
        'vendor_id' => $vendorContext['vendor']->id,
        'vendor_name' => 'Fixture Vendor',
        'posting_date' => now(),
        'document_date' => now(),
        'currency_code' => 'USD',
        'currency_factor' => 1500.0,
        'grand_total' => 110.0,
        'posted' => true,
        'posted_at' => now(),
        'posted_by' => $user->id,
    ]);

    $entry = VendorLedgerEntry::createFromCreditMemo($memo);

    expect($entry->ledger_semantics_version)->toBe(2)
        ->and((float) $entry->debit_amount)->toBe(165000.0)
        ->and((float) $entry->credit_amount)->toBe(0.0)
        ->and((float) $entry->amount)->toBe(-165000.0)
        ->and((float) $entry->remaining_amount)->toBe(165000.0)
        ->and((float) $entry->original_debit_amount)->toBe(110.0)
        ->and((float) $entry->original_remaining_amount)->toBe(110.0);
});

// ---------------------------------------------------------------------------
// 4. Version-2 vendor payment ledger write
// ---------------------------------------------------------------------------

test('3d1 a USD vendor payment at 1550 writes an LCY debit entry with FCY originals and dual remaining', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    $payment = phase3d1PostedPayment($vendorContext, $currencies, 100.0, 1550.0, $user->id, 'PAY-1550');

    $entry = phase3d1VendorPaymentLedger(app(PaymentService::class), $payment, $user->id);

    expect($entry->ledger_semantics_version)->toBe(2)
        ->and((float) $entry->debit_amount)->toBe(155000.0)
        ->and((float) $entry->credit_amount)->toBe(0.0)
        ->and((float) $entry->amount)->toBe(-155000.0)
        ->and((float) $entry->remaining_amount)->toBe(155000.0)
        ->and((float) $entry->original_debit_amount)->toBe(100.0)
        ->and((float) $entry->original_remaining_amount)->toBe(100.0)
        ->and((float) $entry->currency_factor)->toBe(1550.0)
        ->and((float) $entry->signed_lcy_remaining_amount)->toBe(-155000.0);
});

test('3d1 a partially applied vendor payment keeps both remaining representations synchronized', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    $payment = phase3d1PostedPayment($vendorContext, $currencies, 100.0, 1550.0, $user->id, 'PAY-PARTIAL');
    $entry = phase3d1VendorPaymentLedger(app(PaymentService::class), $payment, $user->id);

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-SYNC', 60.0, $vendorContext['business']->id, 'USD', 1550.0, $user->id);

    app(PaymentService::class)->applyToDocument($payment, [
        'document_type' => 'PURCHASE_INVOICE',
        'document_id' => $invoice->id,
        'amount' => 60.0,
    ], $user->id);

    $entry->refresh();

    expect((float) $entry->original_remaining_amount)->toBe(40.0)
        ->and((float) $entry->remaining_amount)->toBe(62000.0)
        ->and((bool) $entry->open)->toBeTrue();
});

// ---------------------------------------------------------------------------
// 5. Vendor payment application: dual-LCY semantics
// ---------------------------------------------------------------------------

test('3d1 a partial USD vendor application records both recognition and settlement LCY', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-APPLY', 220.0, $vendorContext['business']->id, 'USD', 1500.0, $user->id);
    $invoiceEntry = VendorLedgerEntry::createFromInvoice($invoice);

    $payment = phase3d1PostedPayment($vendorContext, $currencies, 100.0, 1550.0, $user->id, 'PAY-APPLY');
    $paymentEntry = phase3d1VendorPaymentLedger(app(PaymentService::class), $payment, $user->id);

    $application = app(PaymentService::class)->applyToDocument($payment, [
        'document_type' => 'PURCHASE_INVOICE',
        'document_id' => $invoice->id,
        'amount' => 100.0,
    ], $user->id);

    // Application record: FCY applied, settlement LCY, recognition LCY.
    expect((float) $application->amount_applied)->toBe(100.0)
        ->and((float) $application->amount_applied_lcy)->toBe(155000.0)
        ->and((float) $application->document_amount_applied_lcy)->toBe(150000.0)
        // Existing realized FX semantics preserved.
        ->and((float) $application->gain_loss_amount)->toBe(5000.0);

    $invoiceEntry->refresh();
    $paymentEntry->refresh();

    // Invoice LCY carrying released at the invoice recognition factor.
    expect((float) $invoiceEntry->remaining_amount)->toBe(180000.0)
        ->and((float) $invoiceEntry->original_remaining_amount)->toBe(120.0)
        // Payment LCY carried at the payment factor.
        ->and((float) $paymentEntry->remaining_amount)->toBe(0.0)
        ->and((float) $paymentEntry->original_remaining_amount)->toBe(0.0)
        ->and((bool) $paymentEntry->open)->toBeFalse()
        ->and((bool) $paymentEntry->fully_applied)->toBeTrue()
        // Signed control exposure after partial settlement.
        ->and((float) $invoiceEntry->signed_lcy_remaining_amount)->toBe(180000.0);
});

test('3d1 a full vendor application closes both remaining representations', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-FULL', 100.0, $vendorContext['business']->id, 'USD', 1500.0, $user->id);
    $invoiceEntry = VendorLedgerEntry::createFromInvoice($invoice);

    $payment = phase3d1PostedPayment($vendorContext, $currencies, 100.0, 1550.0, $user->id, 'PAY-FULL');
    $paymentEntry = phase3d1VendorPaymentLedger(app(PaymentService::class), $payment, $user->id);

    app(PaymentService::class)->applyToDocument($payment, [
        'document_type' => 'PURCHASE_INVOICE',
        'document_id' => $invoice->id,
        'amount' => 100.0,
    ], $user->id);

    $invoiceEntry->refresh();
    $paymentEntry->refresh();

    expect((float) $invoiceEntry->remaining_amount)->toBe(0.0)
        ->and((float) $invoiceEntry->original_remaining_amount)->toBe(0.0)
        ->and((bool) $invoiceEntry->open)->toBeFalse()
        ->and((bool) $invoiceEntry->fully_applied)->toBeTrue()
        ->and((float) $paymentEntry->remaining_amount)->toBe(0.0)
        ->and((float) $paymentEntry->original_remaining_amount)->toBe(0.0);
});

test('3d1 unapplying a vendor payment restores both representations', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-UNAPPLY', 220.0, $vendorContext['business']->id, 'USD', 1500.0, $user->id);
    $invoiceEntry = VendorLedgerEntry::createFromInvoice($invoice);

    $payment = phase3d1PostedPayment($vendorContext, $currencies, 100.0, 1550.0, $user->id, 'PAY-UNAPPLY');
    $paymentEntry = phase3d1VendorPaymentLedger(app(PaymentService::class), $payment, $user->id);

    $application = app(PaymentService::class)->applyToDocument($payment, [
        'document_type' => 'PURCHASE_INVOICE',
        'document_id' => $invoice->id,
        'amount' => 100.0,
    ], $user->id);

    app(PaymentService::class)->unapply($application, $user->id);

    $invoiceEntry->refresh();
    $paymentEntry->refresh();

    expect((float) $invoiceEntry->remaining_amount)->toBe(330000.0)
        ->and((float) $invoiceEntry->original_remaining_amount)->toBe(220.0)
        ->and((bool) $invoiceEntry->open)->toBeTrue()
        ->and((float) $paymentEntry->remaining_amount)->toBe(155000.0)
        ->and((float) $paymentEntry->original_remaining_amount)->toBe(100.0)
        ->and((bool) $paymentEntry->open)->toBeTrue();
});

// ---------------------------------------------------------------------------
// 6. Credit memo application arithmetic and reversal safety
// ---------------------------------------------------------------------------

test('3d1 a partial version-2 credit memo application maintains both representations', function (): void {
    $vendorContext = phase3d1VendorContext();
    $user = phase3d1User();

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-CM-APPLY', 220.0, $vendorContext['business']->id, 'USD', 1500.0, $user->id);
    $invoiceEntry = VendorLedgerEntry::createFromInvoice($invoice);

    $memo = PostedPurchaseCreditMemo::query()->create([
        'business_id' => $vendorContext['business']->id,
        'document_number' => 'PCM-APPLY',
        'vendor_id' => $vendorContext['vendor']->id,
        'vendor_name' => 'Fixture Vendor',
        'posting_date' => now(),
        'document_date' => now(),
        'currency_code' => 'USD',
        'currency_factor' => 1500.0,
        'grand_total' => 20.0,
        'posted' => true,
        'posted_at' => now(),
        'posted_by' => $user->id,
    ]);
    $memoEntry = VendorLedgerEntry::createFromCreditMemo($memo);

    $applied = $memoEntry->applyToEntries([['entry_id' => $invoiceEntry->id, 'amount' => 20.0]]);

    expect($applied)->toBe(20.0);

    $invoiceEntry->refresh();
    $memoEntry->refresh();

    expect((float) $invoiceEntry->original_remaining_amount)->toBe(200.0)
        ->and((float) $invoiceEntry->remaining_amount)->toBe(300000.0)
        ->and((float) $memoEntry->original_remaining_amount)->toBe(0.0)
        ->and((float) $memoEntry->remaining_amount)->toBe(0.0)
        ->and((bool) $memoEntry->fully_applied)->toBeTrue();
});

test('3d1 unapplyAll restores both invoice representations after a version-2 credit memo application', function (): void {
    $vendorContext = phase3d1VendorContext();
    $user = phase3d1User();

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-CM-REV', 220.0, $vendorContext['business']->id, 'USD', 1500.0, $user->id);
    $invoiceEntry = VendorLedgerEntry::createFromInvoice($invoice);

    $memo = PostedPurchaseCreditMemo::query()->create([
        'business_id' => $vendorContext['business']->id,
        'document_number' => 'PCM-REV',
        'vendor_id' => $vendorContext['vendor']->id,
        'vendor_name' => 'Fixture Vendor',
        'posting_date' => now(),
        'document_date' => now(),
        'currency_code' => 'USD',
        'currency_factor' => 1500.0,
        'grand_total' => 20.0,
        'posted' => true,
        'posted_at' => now(),
        'posted_by' => $user->id,
    ]);
    $memoEntry = VendorLedgerEntry::createFromCreditMemo($memo);

    $memoEntry->applyToEntries([['entry_id' => $invoiceEntry->id, 'amount' => 20.0]]);

    // Reload so the applied-entries snapshot that reversal reads is current.
    $memoEntry->refresh();

    $unapply = new ReflectionMethod($memoEntry, 'unapplyAll');
    $unapply->setAccessible(true);
    $unapply->invoke($memoEntry);

    $invoiceEntry->refresh();

    expect((float) $invoiceEntry->original_remaining_amount)->toBe(220.0)
        ->and((float) $invoiceEntry->remaining_amount)->toBe(330000.0)
        ->and((bool) $invoiceEntry->open)->toBeTrue();
});

test('3d1 a version-2 credit memo with a different recognition factor fails closed', function (): void {
    $vendorContext = phase3d1VendorContext();
    $user = phase3d1User();

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-CM-FACTOR', 220.0, $vendorContext['business']->id, 'USD', 1500.0, $user->id);
    $invoiceEntry = VendorLedgerEntry::createFromInvoice($invoice);

    $memo = PostedPurchaseCreditMemo::query()->create([
        'business_id' => $vendorContext['business']->id,
        'document_number' => 'PCM-FACTOR',
        'vendor_id' => $vendorContext['vendor']->id,
        'vendor_name' => 'Fixture Vendor',
        'posting_date' => now(),
        'document_date' => now(),
        'currency_code' => 'USD',
        'currency_factor' => 1400.0,
        'grand_total' => 20.0,
        'posted' => true,
        'posted_at' => now(),
        'posted_by' => $user->id,
    ]);
    $memoEntry = VendorLedgerEntry::createFromCreditMemo($memo);

    expect(fn () => $memoEntry->applyToEntries([['entry_id' => $invoiceEntry->id, 'amount' => 20.0]]))
        ->toThrow(BusinessException::class, 'same recognition factor');
});

test('3d1 a version-2 entry cannot be settled against a legacy entry', function (): void {
    $vendorContext = phase3d1VendorContext();
    $user = phase3d1User();

    $legacy = VendorLedgerEntry::query()->create([
        'entry_number' => 900,
        'vendor_id' => $vendorContext['vendor']->id,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'PI-LEGACY-MIX',
        'description' => 'legacy invoice',
        'posting_date' => now(),
        'document_date' => now(),
        'debit_amount' => 0,
        'credit_amount' => 100.0,
        'amount' => 100.0,
        'running_balance' => 100.0,
        'remaining_amount' => 100.0,
        'open' => true,
        'currency_code' => 'USD',
        'currency_factor' => 1500.0,
        'original_credit_amount' => 0.0667,
        'created_by' => $user->id,
    ]);

    $memo = PostedPurchaseCreditMemo::query()->create([
        'business_id' => $vendorContext['business']->id,
        'document_number' => 'PCM-MIX',
        'vendor_id' => $vendorContext['vendor']->id,
        'vendor_name' => 'Fixture Vendor',
        'posting_date' => now(),
        'document_date' => now(),
        'currency_code' => 'USD',
        'currency_factor' => 1500.0,
        'grand_total' => 20.0,
        'posted' => true,
        'posted_at' => now(),
        'posted_by' => $user->id,
    ]);
    $memoEntry = VendorLedgerEntry::createFromCreditMemo($memo);

    expect(fn () => $memoEntry->applyToEntries([['entry_id' => $legacy->id, 'amount' => 20.0]]))
        ->toThrow(BusinessException::class, 'same ledger semantics version');
});

// ---------------------------------------------------------------------------
// 7. Opening balances
// ---------------------------------------------------------------------------

test('3d1 vendor opening balances are marked version 2 with a reproducible six-decimal factor', function (): void {
    $business = Business::query()->create(['code' => 'B-OB', 'name' => 'Opening Business']);
    session(['active_business_id' => $business->id]);

    AccountingPeriod::query()->updateOrCreate(
        ['start_date' => '2026-01-01', 'end_date' => '2026-12-31'],
        ['name' => 'FY2026', 'is_closed' => false],
    );
    GeneralLedgerSetup::instance()->update([
        'allow_posting_from' => '2026-01-01',
        'allow_posting_to' => '2026-12-31',
    ]);

    $equity = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::EQUITY,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
        'direct_posting' => true,
        'blocked' => false,
    ]);
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $equity->id]);

    foreach ([['CUSTOMER-OPENING', 'COB'], ['VENDOR-OPENING', 'VOB']] as [$code, $prefix]) {
        $series = NumberSeries::query()->create([
            'code' => $code, 'description' => $code, 'prefix' => $prefix,
            'starting_number' => 1, 'ending_number' => 999999, 'current_number' => 0,
            'year' => 2026, 'is_active' => true, 'module' => 'finance',
        ]);
        NumberSeriesLine::query()->create([
            'number_series_id' => $series->id, 'starting_date' => '2026-01-01',
            'starting_no' => 0, 'increment_by' => 1, 'last_no_used' => 0,
            'no_of_digits' => 5, 'blocked' => false,
        ]);
    }

    $payables = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::LIABILITY,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    $vendor = Vendor::factory()->create();
    VendorPostingGroup::query()->findOrFail($vendor->vendor_posting_group_id)->update(['payables_account_id' => $payables->id]);

    $user = phase3d1User([
        'finance.subledger_opening_balance.view',
        'finance.subledger_opening_balance.create',
        'finance.subledger_opening_balance.update',
        'finance.subledger_opening_balance.post',
        'finance.subledger_opening_balance.reverse',
    ]);
    $this->actingAs($user);

    $opening = app(SubledgerOpeningBalanceService::class)->createDraft([
        'business_id' => $business->id,
        'party_type' => 'VENDOR',
        'party_id' => $vendor->id,
        'original_amount' => '70.00',
        'currency_code' => 'USD',
        'currency_factor' => '1450.123456789',
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
    ], $user->id);

    $posted = app(SubledgerOpeningBalanceService::class)->post($opening, $user->id);
    $entry = $posted->vendorLedgerEntry;

    // Factor is normalized to the certified 6 dp contract.
    expect((float) $posted->currency_factor)->toBe(1450.123457)
        ->and((float) $entry->currency_factor)->toBe(1450.123457)
        ->and($entry->ledger_semantics_version)->toBe(2)
        ->and((float) $entry->original_remaining_amount)->toBe(70.0)
        // LCY amount reproducible from the persisted factor.
        ->and((float) $entry->remaining_amount)->toBe((float) LedgerSemantics::lcyFromDocument(70.0, '1450.123457'))
        ->and((float) $entry->credit_amount)->toBe((float) $entry->remaining_amount);
});

// ---------------------------------------------------------------------------
// 8. Signed control-account exposure
// ---------------------------------------------------------------------------

test('3d1 signed LCY remaining follows the AP direction rules', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-SIGN', 220.0, $vendorContext['business']->id, 'USD', 1500.0, $user->id);
    $invoiceEntry = VendorLedgerEntry::createFromInvoice($invoice);

    $payment = phase3d1PostedPayment($vendorContext, $currencies, 100.0, 1550.0, $user->id, 'PAY-SIGN');
    $paymentEntry = phase3d1VendorPaymentLedger(app(PaymentService::class), $payment, $user->id);

    $memo = PostedPurchaseCreditMemo::query()->create([
        'business_id' => $vendorContext['business']->id,
        'document_number' => 'PCM-SIGN',
        'vendor_id' => $vendorContext['vendor']->id,
        'vendor_name' => 'Fixture Vendor',
        'posting_date' => now(),
        'document_date' => now(),
        'currency_code' => 'USD',
        'currency_factor' => 1500.0,
        'grand_total' => 10.0,
        'posted' => true,
        'posted_at' => now(),
        'posted_by' => $user->id,
    ]);
    $memoEntry = VendorLedgerEntry::createFromCreditMemo($memo);

    expect((float) $invoiceEntry->signed_lcy_remaining_amount)->toBe(330000.0)
        ->and((float) $memoEntry->signed_lcy_remaining_amount)->toBe(-15000.0)
        ->and((float) $paymentEntry->signed_lcy_remaining_amount)->toBe(-155000.0);
});

// ---------------------------------------------------------------------------
// 9. Version-aware aggregation / reporting
// ---------------------------------------------------------------------------

test('3d1 legacy and version-2 rows are never summed as if they shared one unit', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    // Legacy USD invoice: FCY 100 @ 1000 -> LCY 100,000.
    VendorLedgerEntry::query()->create([
        'entry_number' => 1,
        'vendor_id' => $vendorContext['vendor']->id,
        'business_id' => $vendorContext['business']->id,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'PI-LEGACY-MIX-AGG',
        'description' => 'legacy invoice',
        'posting_date' => now(),
        'document_date' => now(),
        'debit_amount' => 0,
        'credit_amount' => 100.0,
        'amount' => 100.0,
        'running_balance' => 100.0,
        'remaining_amount' => 100.0,
        'open' => true,
        'currency_id' => $currencies['usd']->id,
        'currency_code' => 'USD',
        'currency_factor' => 1000.0,
        'original_credit_amount' => 0.1,
        'created_by' => $user->id,
    ]);

    // Version-2 USD invoice: FCY 50 @ 2000 -> LCY 100,000.
    $v2 = phase3d1Invoice($vendorContext['vendor']->id, 'PI-V2-AGG', 50.0, $vendorContext['business']->id, 'USD', 2000.0, $user->id);
    VendorLedgerEntry::createFromInvoice($v2);

    $total = (float) VendorLedgerEntry::query()
        ->sum(DB::raw(LedgerSemantics::lcyRemainingSql('vendor_ledger_entries')));

    // 100,000 (legacy converted) + 100,000 (version-2 LCY base) = 200,000.
    // A non-version-aware sum would double-convert the version-2 row to 100,000,000.
    expect($total)->toBe(200000.0);

    $signed = (float) VendorLedgerEntry::query()
        ->sum(DB::raw(LedgerSemantics::signedLcyRemainingSql('vendor_ledger_entries')));

    expect($signed)->toBe(200000.0)
        ->and($vendorContext['vendor']->fresh()->open_balance)->toBe(200000.0);
});

test('3d1 an ambiguous legacy row is not fabricated into LCY', function (): void {
    // A foreign legacy row with no factor cannot be converted under a trusted rule.
    expect(LedgerSemantics::toLcy('100', null, 'PURCHASE_INVOICE', null, 'USD'))->toBeNull()
        ->and(LedgerSemantics::toLcy('100', null, 'PURCHASE_INVOICE', '1500', 'USD'))->toBe('150000.0000')
        ->and(LedgerSemantics::toLcy('100', null, 'PURCHASE_INVOICE', null, 'NGN'))->toBe('100.0000')
        // Opening balances are already LCY.
        ->and(LedgerSemantics::toLcy('100', null, 'OPENING_BALANCE', null, 'USD'))->toBe('100.0000');
});

test('3d1 the purchase dashboard reports version-aware LCY payables', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    VendorLedgerEntry::query()->create([
        'entry_number' => 1,
        'vendor_id' => $vendorContext['vendor']->id,
        'business_id' => $vendorContext['business']->id,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'PI-DASH-LEGACY',
        'description' => 'legacy invoice',
        'posting_date' => now(),
        'document_date' => now(),
        'debit_amount' => 0,
        'credit_amount' => 100.0,
        'amount' => 100.0,
        'running_balance' => 100.0,
        'remaining_amount' => 100.0,
        'open' => true,
        'currency_id' => $currencies['usd']->id,
        'currency_code' => 'USD',
        'currency_factor' => 1000.0,
        'original_credit_amount' => 0.1,
        'created_by' => $user->id,
    ]);

    $v2 = phase3d1Invoice($vendorContext['vendor']->id, 'PI-DASH-V2', 50.0, $vendorContext['business']->id, 'USD', 2000.0, $user->id);
    VendorLedgerEntry::createFromInvoice($v2);

    $summary = app(PurchaseDashboardService::class)->summary(null, null, $vendorContext['business']->id);

    expect($summary['outstanding_payables'])->toBe(200000.0)
        ->and($summary['invoices_not_paid']['amount'])->toBe(200000.0);
});

// ---------------------------------------------------------------------------
// 10. Control-account reconciliation
// ---------------------------------------------------------------------------

test('3d1 the payables control account reconciles for a version-2 NGN invoice', function (): void {
    $vendorContext = phase3d1VendorContext();
    $user = phase3d1User();

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-REC-NGN', 100.0, $vendorContext['business']->id, 'NGN', 1.0, $user->id);
    $entry = VendorLedgerEntry::createFromInvoice($invoice);

    GlEntry::query()->create([
        'entry_number' => 1,
        'transaction_number' => 1,
        'user_id' => $user->id,
        'business_id' => $vendorContext['business']->id,
        'chart_of_account_id' => $vendorContext['apAccount']->id,
        'posting_date' => now(),
        'document_date' => now(),
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => $invoice->document_number,
        'description' => 'AP control',
        'debit_amount' => 0,
        'credit_amount' => 100.0,
        'amount' => -100.0,
        'amount_lcy' => -100.0,
        'credit_amount_lcy' => 100.0,
        'debit_amount_lcy' => 0,
    ]);

    $report = phase3d1ReconcileReport();

    expect($report['vendor_ledger_payables_mismatches'] ?? [])->toBe([]);
});

test('3d1 the payables control account reconciles after a version-2 NGN payment application', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-REC-PAY', 100.0, $vendorContext['business']->id, 'NGN', 1.0, $user->id);
    $invoiceEntry = VendorLedgerEntry::createFromInvoice($invoice);

    $payment = phase3d1PostedPayment($vendorContext, $currencies, 40.0, 1.0, $user->id, 'PAY-REC-NGN');
    phase3d1VendorPaymentLedger(app(PaymentService::class), $payment, $user->id);

    app(PaymentService::class)->applyToDocument($payment, [
        'document_type' => 'PURCHASE_INVOICE',
        'document_id' => $invoice->id,
        'amount' => 40.0,
    ], $user->id);

    // Composite journal: invoice credit 100, payment debit 40.
    GlEntry::query()->create([
        'entry_number' => 1,
        'transaction_number' => 1,
        'user_id' => $user->id,
        'business_id' => $vendorContext['business']->id,
        'chart_of_account_id' => $vendorContext['apAccount']->id,
        'posting_date' => now(),
        'document_date' => now(),
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => $invoice->document_number,
        'description' => 'AP control',
        'debit_amount' => 0,
        'credit_amount' => 100.0,
        'amount' => -100.0,
        'amount_lcy' => -100.0,
        'credit_amount_lcy' => 100.0,
        'debit_amount_lcy' => 0,
    ]);
    GlEntry::query()->create([
        'entry_number' => 2,
        'transaction_number' => 2,
        'user_id' => $user->id,
        'business_id' => $vendorContext['business']->id,
        'chart_of_account_id' => $vendorContext['apAccount']->id,
        'posting_date' => now(),
        'document_date' => now(),
        'document_type' => 'PAYMENT',
        'document_number' => $payment->payment_number,
        'description' => 'AP payment leg',
        'debit_amount' => 40.0,
        'credit_amount' => 0,
        'amount' => 40.0,
        'amount_lcy' => 40.0,
        'credit_amount_lcy' => 0,
        'debit_amount_lcy' => 40.0,
    ]);

    $invoiceEntry->refresh();

    expect((float) $invoiceEntry->remaining_amount)->toBe(60.0)
        ->and((float) $invoiceEntry->original_remaining_amount)->toBe(60.0);

    $report = phase3d1ReconcileReport();

    expect($report['vendor_ledger_payables_mismatches'] ?? [])->toBe([]);
});

test('3d1 the payable subledger side of the reconcile is LCY-normalized for version-2 FCY rows', function (): void {
    $vendorContext = phase3d1VendorContext();
    $user = phase3d1User();

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-REC-FCY', 220.0, $vendorContext['business']->id, 'USD', 1500.0, $user->id);
    VendorLedgerEntry::createFromInvoice($invoice);

    $report = phase3d1ReconcileReport();
    $mismatches = collect($report['vendor_ledger_payables_mismatches'] ?? []);
    $group = $mismatches->firstWhere('posting_group_id', $vendorContext['vpg']->id);

    // The subledger side is the LCY carrying amount, not the FCY document amount.
    expect($group)->not->toBeNull()
        ->and((float) $group['subledger_balance'])->toBe(330000.0);
});

// ---------------------------------------------------------------------------
// 11. Non-regression guards
// ---------------------------------------------------------------------------

test('3d1 customer ledger writers keep their legacy semantics', function (): void {
    $currencies = phase3d1Currencies();

    $receivables = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::ASSET,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    $cpg = CustomerPostingGroup::factory()->create(['receivables_account_id' => $receivables->id]);
    $customer = Customer::factory()->create(['customer_posting_group_id' => $cpg->id]);
    $user = phase3d1User();

    $invoice = PostedSalesInvoice::query()->create([
        'document_number' => 'SI-CLE-1',
        'customer_id' => $customer->id,
        'customer_name' => 'Fixture Customer',
        'posting_date' => now(),
        'document_date' => now(),
        'due_date' => now()->addDays(30),
        'currency_code' => 'USD',
        'currency_factor' => 1500.0,
        'grand_total' => 220.0,
        'remaining_amount' => 220.0,
        'posted_by' => $user->id,
        'posted_at' => now(),
    ]);

    $entry = CustomerLedgerEntry::createFromInvoice($invoice);

    // Customer Ledger keeps the legacy document-currency base and the legacy
    // `amount / factor` original convention; it is NOT migrated in this phase.
    expect($entry->ledger_semantics_version)->toBeNull()
        ->and((float) $entry->debit_amount)->toBe(220.0)
        ->and((float) $entry->remaining_amount)->toBe(220.0)
        ->and((float) $entry->original_debit_amount)->toBe(round(220.0 / 1500.0, 4));
});

test('3d1 the same-currency bank guard is unchanged', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();
    $service = app(PaymentService::class);

    $guard = new ReflectionMethod($service, 'assertBankAccountCurrencyMatches');
    $guard->setAccessible(true);

    $payment = phase3d1PostedPayment($vendorContext, $currencies, 100.0, 1550.0, $user->id, 'PAY-GUARD');

    // No bank account -> fails closed.
    expect(fn () => $guard->invoke($service, $payment))->toThrow(BusinessException::class);

    // Matching bank currency -> allowed.
    $usdBank = BankAccount::factory()->create(['currency_id' => $currencies['usd']->id]);
    $payment->setRelation('bankAccount', $usdBank);
    $guard->invoke($service, $payment);
    expect(true)->toBeTrue();

    // Mismatched bank currency -> fails closed.
    $ngnBank = BankAccount::factory()->create(['currency_id' => $currencies['ngn']->id]);
    $payment->setRelation('bankAccount', $ngnBank);
    expect(fn () => $guard->invoke($service, $payment))->toThrow(BusinessException::class);
});

test('3d1 the purchase invoice G/L caller has not adopted the currency-aware mode', function (): void {
    $source = file_get_contents(app_path('Services/Purchase/PurchaseInvoiceService.php'));

    expect($source)->not->toContain('CURRENCY_AWARE')
        ->and($source)->not->toContain('PostingMode::CURRENCY_AWARE');
});

test('3d1 no fixture or code path touches the protected production payment', function (): void {
    expect(VendorLedgerEntry::query()->where('document_number', 'PAY-2026-00004')->exists())->toBeFalse()
        ->and(Payment::query()->where('payment_number', 'PAY-2026-00004')->exists())->toBeFalse();

    foreach ([
        app_path('Support/LedgerSemantics.php'),
        app_path('Models/VendorLedgerEntry.php'),
        app_path('Services/Finance/PaymentService.php'),
        app_path('Services/Finance/SubledgerOpeningBalanceService.php'),
    ] as $path) {
        expect(file_get_contents($path))->not->toContain('PAY-2026-00004');
    }
});

// ---------------------------------------------------------------------------
// 12. Phase 3D-1B: reconcile transition classification
// ---------------------------------------------------------------------------

test('3d1b a legacy FCY invoice under the pre-3C-C G/L convention is a pending-3C-C transition, not corruption', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    phase3d1bLegacyInvoiceEntry($vendorContext, $currencies, $user, 'PI-3D1B-LEGACY-FCY', 220.0, 1500.0);

    // Legacy (pre-3C-C) purchase invoice G/L convention: the payable control leg
    // carries the document amount in both the nominal and the LCY column.
    phase3d1bGlEntry($vendorContext['apAccount'], $vendorContext['business']->id, $user, 'PI-3D1B-LEGACY-FCY', 220.0, 220.0, false);

    $group = phase3d1bPayablesMismatchFor($vendorContext['vpg']->id);

    expect($group)->not->toBeNull()
        ->and($group['classification'])->toBe('legacy_gl_currency_semantics_pending_3c_c')
        ->and($group['severity'])->toBe('warning')
        ->and((float) $group['subledger_balance'])->toBe(330000.0)
        ->and((float) $group['gl_balance'])->toBe(220.0)
        ->and((float) $group['difference'])->toBe(329780.0)
        ->and((float) $group['legacy_fcy_invoice_lcy_gap'])->toBe(329780.0)
        ->and((float) $group['unexplained_difference'])->toBe(0.0)
        ->and($group['suggested_remediation'])->toContain('3C-C')
        ->and($group['suggested_remediation'])->toContain('No data repair');
});

test('3d1b the command never claims a legacy FCY payables difference reconciles', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    phase3d1bLegacyInvoiceEntry($vendorContext, $currencies, $user, 'PI-3D1B-LEGACY-NR', 220.0, 1500.0);
    phase3d1bGlEntry($vendorContext['apAccount'], $vendorContext['business']->id, $user, 'PI-3D1B-LEGACY-NR', 220.0, 220.0, false);

    $mismatches = collect(phase3d1ReconcileReport()['vendor_ledger_payables_mismatches'] ?? []);

    expect($mismatches)->not->toBeEmpty()
        ->and($mismatches->where('classification', 'vendor_ledger_gl_mismatch'))->toBeEmpty()
        ->and((float) $mismatches->first()['difference'])->not->toBe(0.0);
});

test('3d1b a genuine version-2 AP control mismatch stays critical', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();
    $offset = ChartOfAccount::factory()->create(['account_category' => AccountCategory::EQUITY]);

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-3D1B-V2-FCY', 220.0, $vendorContext['business']->id, 'USD', 1500.0, $user->id);
    VendorLedgerEntry::createFromInvoice($invoice);

    // Convention-consistent control leg plus an unexplained orphan debit that the
    // known pre-3C-C condition cannot account for.
    phase3d1bGlEntry($vendorContext['apAccount'], $vendorContext['business']->id, $user, 'PI-3D1B-V2-FCY', 220.0, 220.0, false);
    phase3d1bGlEntry($vendorContext['apAccount'], $vendorContext['business']->id, $user, 'PI-3D1B-ORPHAN', 5000.0, 5000.0, true);
    phase3d1bGlEntry($offset, $vendorContext['business']->id, $user, 'PI-3D1B-ORPHAN', 5000.0, 5000.0, false);

    $group = phase3d1bPayablesMismatchFor($vendorContext['vpg']->id);

    expect($group)->not->toBeNull()
        ->and($group['classification'])->toBe('vendor_ledger_gl_mismatch')
        ->and($group['severity'])->toBe('critical')
        ->and((float) $group['legacy_fcy_invoice_lcy_gap'])->toBe(329780.0)
        ->and((float) $group['unexplained_difference'])->toBe(5000.0);
});

test('3d1b an NGN payables mismatch remains a genuine critical mismatch', function (): void {
    $vendorContext = phase3d1VendorContext();
    $user = phase3d1User();
    $offset = ChartOfAccount::factory()->create(['account_category' => AccountCategory::EQUITY]);

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-3D1B-NGN-MIS', 250000.0, $vendorContext['business']->id, 'NGN', 1.0, $user->id);
    VendorLedgerEntry::createFromInvoice($invoice);

    phase3d1bGlEntry($vendorContext['apAccount'], $vendorContext['business']->id, $user, 'PI-3D1B-NGN-MIS', 250000.0, 250000.0, false);
    phase3d1bGlEntry($vendorContext['apAccount'], $vendorContext['business']->id, $user, 'PI-3D1B-NGN-ORPHAN', 1000.0, 1000.0, false);
    phase3d1bGlEntry($offset, $vendorContext['business']->id, $user, 'PI-3D1B-NGN-ORPHAN', 1000.0, 1000.0, true);

    $group = phase3d1bPayablesMismatchFor($vendorContext['vpg']->id);

    expect($group)->not->toBeNull()
        ->and($group['classification'])->toBe('vendor_ledger_gl_mismatch')
        ->and($group['severity'])->toBe('critical')
        ->and((float) $group['legacy_fcy_invoice_lcy_gap'])->toBe(0.0)
        ->and((float) $group['unexplained_difference'])->toBe(-1000.0);
});

test('3d1b the missing-payables-control finding states its LCY unit', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    // Vendor ledger entry with no matching payables control G/L entry.
    phase3d1bLegacyInvoiceEntry($vendorContext, $currencies, $user, 'PI-3D1B-NOGL', 220.0, 1500.0);

    $rows = collect(phase3d1ReconcileReport()['missing_control_account_entries'] ?? []);

    $finding = $rows->firstWhere('document_number', 'PI-3D1B-NOGL');

    expect($finding)->not->toBeNull()
        ->and($finding['amount_unit'])->toBe('LCY')
        ->and((float) $finding['amount'])->toBe(330000.0);
});

// ---------------------------------------------------------------------------
// 13. Phase 3D-1B: running-balance contract
// ---------------------------------------------------------------------------

test('3d1b a version-2 running balance normalizes a legacy NGN predecessor', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    VendorLedgerEntry::query()->create([
        'entry_number' => 1,
        'vendor_id' => $vendorContext['vendor']->id,
        'business_id' => $vendorContext['business']->id,
        'vendor_posting_group_id' => $vendorContext['vpg']->id,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'PI-3D1B-LEGACY-NGN',
        'description' => 'legacy NGN invoice',
        'posting_date' => now(),
        'document_date' => now(),
        'debit_amount' => 0,
        'credit_amount' => 100.0,
        'amount' => 100.0,
        'running_balance' => 100.0,
        'remaining_amount' => 100.0,
        'open' => true,
        'currency_id' => $currencies['ngn']->id,
        'currency_code' => 'NGN',
        'currency_factor' => 1.0,
        'original_credit_amount' => 100.0,
        'created_by' => $user->id,
    ]);

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-3D1B-AFTER-NGN', 200.0, $vendorContext['business']->id, 'USD', 1000.0, $user->id);
    $entry = VendorLedgerEntry::createFromInvoice($invoice);

    // 100 LCY (legacy NGN) + 200,000 LCY (version 2) - never the raw FCY 200.
    expect((float) $entry->running_balance)->toBe(200100.0);
});

test('3d1b a version-2 running balance normalizes a legacy FCY predecessor through its trusted factor', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    phase3d1bLegacyInvoiceEntry($vendorContext, $currencies, $user, 'PI-3D1B-LEGACY-PRED', 220.0, 1500.0);

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-3D1B-AFTER-FCY', 200.0, $vendorContext['business']->id, 'USD', 1000.0, $user->id);
    $entry = VendorLedgerEntry::createFromInvoice($invoice);

    // 330,000 LCY (220 x 1500) + 200,000 LCY.
    expect((float) $entry->running_balance)->toBe(530000.0);
});

test('3d1b multiple version-2 rows accumulate an LCY running balance', function (): void {
    $vendorContext = phase3d1VendorContext();
    $user = phase3d1User();

    $first = phase3d1bV2InvoiceEntry($vendorContext, 'PI-3D1B-RB-1', 100.0, 1000.0, $user);
    $second = phase3d1bV2InvoiceEntry($vendorContext, 'PI-3D1B-RB-2', 50.0, 1000.0, $user);
    $third = phase3d1bV2InvoiceEntry($vendorContext, 'PI-3D1B-RB-3', 25.0, 1000.0, $user);

    expect((float) $first->running_balance)->toBe(100000.0)
        ->and((float) $second->running_balance)->toBe(150000.0)
        ->and((float) $third->running_balance)->toBe(175000.0);
});

test('3d1b an ambiguous predecessor is never fabricated into the version-2 running balance', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    // A legacy FCY row with a zero factor cannot be normalized under the trusted
    // rules and must not contribute a fabricated LCY amount.
    VendorLedgerEntry::query()->create([
        'entry_number' => 1,
        'vendor_id' => $vendorContext['vendor']->id,
        'business_id' => $vendorContext['business']->id,
        'vendor_posting_group_id' => $vendorContext['vpg']->id,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'PI-3D1B-AMBIGUOUS',
        'description' => 'ambiguous legacy row',
        'posting_date' => now(),
        'document_date' => now(),
        'debit_amount' => 0,
        'credit_amount' => 50.0,
        'amount' => 50.0,
        'running_balance' => 50.0,
        'remaining_amount' => 50.0,
        'open' => true,
        'currency_id' => $currencies['usd']->id,
        'currency_code' => 'USD',
        'currency_factor' => 0.0,
        'original_credit_amount' => 50.0,
        'created_by' => $user->id,
    ]);

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-3D1B-AMBIG-V2', 200.0, $vendorContext['business']->id, 'USD', 1000.0, $user->id);
    $entry = VendorLedgerEntry::createFromInvoice($invoice);

    $contract = VendorLedgerEntry::calculateLcyRunningBalance($vendorContext['vendor']->id, (int) $entry->entry_number, 200000.0);

    expect($contract['authoritative'])->toBeFalse()
        // The row's own LCY amount only: no partial or mixed-unit total.
        ->and((float) $entry->running_balance)->toBe(200000.0)
        ->and((float) $contract['value'])->toBe(200000.0);
});

// ---------------------------------------------------------------------------
// 14. Phase 3D-1B: application factor and rounding
// ---------------------------------------------------------------------------

test('3d1b multiple partial applications with different settlement factors stay exact', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-3D1B-ROUND', 220.0, $vendorContext['business']->id, 'USD', 1500.0, $user->id);
    $invoiceEntry = VendorLedgerEntry::createFromInvoice($invoice);

    $first = phase3d1bPostedPayment($vendorContext, 'USD', $currencies['usd']->id, 100.0, 1550.0, $user->id, 'PAY-3D1B-ROUND-1');
    phase3d1VendorPaymentLedger(app(PaymentService::class), $first, $user->id);
    $applicationA = app(PaymentService::class)->applyToDocument($first, [
        'document_type' => 'PURCHASE_INVOICE',
        'document_id' => $invoice->id,
        'amount' => 100.0,
    ], $user->id);

    $second = phase3d1bPostedPayment($vendorContext, 'USD', $currencies['usd']->id, 120.0, 1600.0, $user->id, 'PAY-3D1B-ROUND-2');
    phase3d1VendorPaymentLedger(app(PaymentService::class), $second, $user->id);
    $applicationB = app(PaymentService::class)->applyToDocument($second, [
        'document_type' => 'PURCHASE_INVOICE',
        'document_id' => $invoice->id,
        'amount' => 120.0,
    ], $user->id);

    expect((float) $applicationA->amount_applied)->toBe(100.0)
        ->and((float) $applicationA->amount_applied_lcy)->toBe(155000.0)
        ->and((float) $applicationA->document_amount_applied_lcy)->toBe(150000.0)
        ->and((float) $applicationA->gain_loss_amount)->toBe(5000.0)
        ->and((float) $applicationB->amount_applied)->toBe(120.0)
        ->and((float) $applicationB->amount_applied_lcy)->toBe(192000.0)
        ->and((float) $applicationB->document_amount_applied_lcy)->toBe(180000.0)
        ->and((float) $applicationB->gain_loss_amount)->toBe(12000.0);

    // The recognition-LCY releases sum exactly to the invoice carrying amount.
    $recognitionReleased = (float) PaymentApplication::query()
        ->whereIn('id', [$applicationA->id, $applicationB->id])
        ->sum('document_amount_applied_lcy');

    expect($recognitionReleased)->toBe(330000.0)
        ->and((float) $invoiceEntry->fresh()->remaining_amount)->toBe(0.0)
        ->and((float) $invoiceEntry->fresh()->original_remaining_amount)->toBe(0.0);
});

test('3d1b a positive sub-1 factor converts with deterministic decimal arithmetic', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    $eur = Currency::query()->updateOrCreate(['code' => 'EUR'], [
        'description' => 'Euro',
        'decimal_places' => 2,
        'is_active' => true,
        'is_lcy' => false,
        'exchange_rate' => 0.1,
    ]);

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-3D1B-SUB1', 3.0, $vendorContext['business']->id, 'EUR', 0.1, $user->id);
    $invoiceEntry = VendorLedgerEntry::createFromInvoice($invoice);

    $payment = phase3d1bPostedPayment($vendorContext, 'EUR', $eur->id, 3.0, 0.1, $user->id, 'PAY-3D1B-SUB1');
    phase3d1VendorPaymentLedger(app(PaymentService::class), $payment, $user->id);

    $application = app(PaymentService::class)->applyToDocument($payment, [
        'document_type' => 'PURCHASE_INVOICE',
        'document_id' => $invoice->id,
        'amount' => 3.0,
    ], $user->id);

    // 3 x 0.1 must be exactly 0.3, not a float artifact.
    expect((float) $invoiceEntry->remaining_amount)->toBe(0.3)
        ->and((float) $application->document_amount_applied_lcy)->toBe(0.3)
        ->and((float) $application->amount_applied_lcy)->toBe(0.3)
        ->and((float) $application->gain_loss_amount)->toBe(0.0);
});

test('3d1b a six-decimal factor releases exactly the document carrying amount', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    $expected = (float) LedgerSemantics::lcyFromDocument(10.0, '12.345678');

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-3D1B-FRACTION', 10.0, $vendorContext['business']->id, 'USD', 12.345678, $user->id);
    $invoiceEntry = VendorLedgerEntry::createFromInvoice($invoice);

    $payment = phase3d1bPostedPayment($vendorContext, 'USD', $currencies['usd']->id, 10.0, 12.345678, $user->id, 'PAY-3D1B-FRACTION');
    phase3d1VendorPaymentLedger(app(PaymentService::class), $payment, $user->id);

    $application = app(PaymentService::class)->applyToDocument($payment, [
        'document_type' => 'PURCHASE_INVOICE',
        'document_id' => $invoice->id,
        'amount' => 10.0,
    ], $user->id);

    expect((float) $invoiceEntry->remaining_amount)->toBe($expected)
        ->and((float) $application->document_amount_applied_lcy)->toBe($expected)
        ->and((float) $application->gain_loss_amount)->toBe(0.0)
        ->and((float) $invoiceEntry->fresh()->remaining_amount)->toBe(0.0);
});

test('3d1b a foreign application with an unusable factor fails closed', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-3D1B-NOFACTOR', 220.0, $vendorContext['business']->id, 'USD', 1500.0, $user->id);
    VendorLedgerEntry::createFromInvoice($invoice);

    $payment = phase3d1bPostedPayment($vendorContext, 'USD', $currencies['usd']->id, 100.0, 1550.0, $user->id, 'PAY-3D1B-NOFACTOR');
    $payment->forceFill(['currency_factor' => 0])->save();

    // A missing foreign factor is rejected by the certified contract itself.
    expect(fn () => LedgerSemantics::normalizeFactor('USD', null))->toThrow(InvalidArgumentException::class);

    expect(fn () => app(PaymentService::class)->applyToDocument($payment, [
        'document_type' => 'PURCHASE_INVOICE',
        'document_id' => $invoice->id,
        'amount' => 100.0,
    ], $user->id))->toThrow(InvalidArgumentException::class);

    expect(PaymentApplication::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// 15. Phase 3D-1B: production posting pipeline
// ---------------------------------------------------------------------------

test('3d1b a real NGN purchase invoice posting reconciles the payables control account', function (): void {
    $ctx = phase3d1bPostingContext();
    $this->actingAs($ctx['user']);

    phase3d1bPostInvoiceThroughService($ctx, 'PI-3D1B-NGN-REAL', 1000.0, 'NGN', 1.0);

    $entry = VendorLedgerEntry::query()->where('document_number', 'PI-3D1B-NGN-REAL')->firstOrFail();

    expect($entry->ledger_semantics_version)->toBe(2)
        ->and((float) $entry->credit_amount)->toBe(1000.0)
        ->and((float) $entry->original_remaining_amount)->toBe(1000.0);

    expect(phase3d1ReconcileReport()['vendor_ledger_payables_mismatches'] ?? [])->toBe([]);
});

test('3d1b a real FCY purchase invoice posting yields the explicit pending-3C-C transition', function (): void {
    $ctx = phase3d1bPostingContext();
    $this->actingAs($ctx['user']);

    phase3d1bPostInvoiceThroughService($ctx, 'PI-3D1B-FCY-REAL', 220.0, 'USD', 1500.0);

    $entry = VendorLedgerEntry::query()->where('document_number', 'PI-3D1B-FCY-REAL')->firstOrFail();

    expect((float) $entry->credit_amount)->toBe(330000.0)
        ->and((float) $entry->original_remaining_amount)->toBe(220.0)
        ->and((float) $entry->currency_factor)->toBe(1500.0);

    $group = phase3d1bPayablesMismatchFor($ctx['vendorPostingGroup']->id);

    expect($group)->not->toBeNull()
        ->and($group['classification'])->toBe('legacy_gl_currency_semantics_pending_3c_c')
        ->and($group['severity'])->toBe('warning')
        ->and((float) $group['unexplained_difference'])->toBe(0.0);
});

// ---------------------------------------------------------------------------
// 16. Phase 3D-1B: opening-balance factor contract
// ---------------------------------------------------------------------------

test('3d1b a foreign-currency opening balance without a factor fails closed', function (): void {
    $ctx = phase3d1bOpeningContext();
    $this->actingAs($ctx['user']);

    expect(fn () => app(SubledgerOpeningBalanceService::class)->createDraft([
        'business_id' => $ctx['business']->id,
        'party_type' => 'VENDOR',
        'party_id' => $ctx['vendor']->id,
        'original_amount' => '70.00',
        'currency_code' => 'USD',
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
    ], $ctx['user']->id))->toThrow(BusinessException::class, 'explicit positive exchange-rate factor');

    expect(SubledgerOpeningBalance::query()->count())->toBe(0);
});

test('3d1b an NGN opening balance without a factor resolves to factor 1', function (): void {
    $ctx = phase3d1bOpeningContext();
    $this->actingAs($ctx['user']);

    $opening = app(SubledgerOpeningBalanceService::class)->createDraft([
        'business_id' => $ctx['business']->id,
        'party_type' => 'VENDOR',
        'party_id' => $ctx['vendor']->id,
        'original_amount' => '70.00',
        'currency_code' => 'NGN',
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
    ], $ctx['user']->id);

    expect((float) $opening->currency_factor)->toBe(1.0)
        ->and((float) $opening->amount_lcy)->toBe(70.0);
});

test('3d1b an opening balance with a non-positive factor fails closed', function (string $factor): void {
    $ctx = phase3d1bOpeningContext();
    $this->actingAs($ctx['user']);

    expect(fn () => app(SubledgerOpeningBalanceService::class)->createDraft([
        'business_id' => $ctx['business']->id,
        'party_type' => 'VENDOR',
        'party_id' => $ctx['vendor']->id,
        'original_amount' => '70.00',
        'currency_code' => 'USD',
        'currency_factor' => $factor,
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
    ], $ctx['user']->id))->toThrow(BusinessException::class, 'greater than zero');

    expect(SubledgerOpeningBalance::query()->count())->toBe(0);
})->with([
    'zero' => '0',
    'negative' => '-5',
]);

test('3d1b a positive sub-1 fractional opening factor is accepted and normalized to six decimals', function (): void {
    $ctx = phase3d1bOpeningContext();
    $this->actingAs($ctx['user']);

    $opening = app(SubledgerOpeningBalanceService::class)->createDraft([
        'business_id' => $ctx['business']->id,
        'party_type' => 'VENDOR',
        'party_id' => $ctx['vendor']->id,
        'original_amount' => '70.00',
        'currency_code' => 'EUR',
        'currency_factor' => '0.1234567',
        'posting_date' => '2026-08-30',
        'document_date' => '2026-08-30',
    ], $ctx['user']->id);

    expect((float) $opening->currency_factor)->toBe(0.123457)
        ->and((float) $opening->amount_lcy)->toBe((float) LedgerSemantics::lcyFromDocument(70.0, '0.123457'));
});

// ---------------------------------------------------------------------------
// 17. Phase 3D-1B: end-to-end version-2 vendor payment void
// ---------------------------------------------------------------------------

test('3d1b a version-2 vendor payment can be posted applied and voided end to end', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User(['finance.payment.post']);
    phase3d1bEnsureBankLedgerNumberSeries();
    GeneralLedgerSetup::query()->updateOrCreate(
        ['company_name' => 'Default Company'],
        ['allow_posting_from' => '2026-01-01', 'allow_posting_to' => '2026-12-31'],
    );
    session(['active_business_id' => $vendorContext['business']->id]);

    $vendorId = $vendorContext['vendor']->id;

    $invoice = phase3d1Invoice($vendorId, 'PI-3D1B-VOID', 220.0, $vendorContext['business']->id, 'USD', 1500.0, $user->id);
    $invoiceEntry = VendorLedgerEntry::createFromInvoice($invoice);

    $bankAccount = BankAccount::factory()->paymentOnly()->create([
        'currency_id' => $currencies['usd']->id,
        'current_balance' => 100000,
        'available_balance' => 100000,
    ]);

    $payment = phase3d1bApprovedVendorPayment($vendorContext, 'USD', $currencies['usd']->id, 100.0, 1550.0, $bankAccount->id, $user->id, 'PAY-3D1B-VOID');

    $exposureBefore = phase3d1bVendorExposure($vendorId);

    app(PaymentService::class)->post($payment, $user->id);

    $paymentEntry = VendorLedgerEntry::query()
        ->where('document_number', 'PAY-3D1B-VOID')
        ->where('document_type', 'PAYMENT')
        ->firstOrFail();

    expect($paymentEntry->ledger_semantics_version)->toBe(2)
        ->and((float) $paymentEntry->remaining_amount)->toBe(155000.0)
        ->and((float) $paymentEntry->original_remaining_amount)->toBe(100.0)
        ->and((float) $paymentEntry->currency_factor)->toBe(1550.0);

    $application = app(PaymentService::class)->applyToDocument($payment->fresh(), [
        'document_type' => 'PURCHASE_INVOICE',
        'document_id' => $invoice->id,
        'amount' => 100.0,
    ], $user->id);

    expect((float) $invoiceEntry->fresh()->remaining_amount)->toBe(180000.0)
        ->and((float) $invoiceEntry->fresh()->original_remaining_amount)->toBe(120.0)
        ->and((float) $paymentEntry->fresh()->remaining_amount)->toBe(0.0)
        ->and(phase3d1bVendorExposure($vendorId))->toBe(180000.0)
        ->and(GlEntry::query()->where('payment_application_id', $application->id)->count())->toBeGreaterThan(0);

    app(PaymentService::class)->void($payment->fresh(), 'focused e2e void', $user->id);

    $invoiceEntry->refresh();
    $paymentEntry->refresh();

    expect((float) $invoiceEntry->original_remaining_amount)->toBe(220.0)
        ->and((float) $invoiceEntry->remaining_amount)->toBe(330000.0)
        ->and((bool) $invoiceEntry->open)->toBeTrue()
        ->and((bool) $application->fresh()->reversed)->toBeTrue()
        ->and((float) $paymentEntry->remaining_amount)->toBe(0.0)
        ->and((float) $paymentEntry->original_remaining_amount)->toBe(0.0)
        ->and((bool) $paymentEntry->reversed)->toBeTrue()
        ->and((bool) $paymentEntry->open)->toBeFalse();

    $mirror = VendorLedgerEntry::query()
        ->where('document_type', 'ADJUSTMENT')
        ->where('document_number', 'like', 'REV-%')
        ->firstOrFail();

    expect($mirror->ledger_semantics_version)->toBe(2)
        ->and((float) $mirror->original_remaining_amount)->toBe(0.0)
        ->and((float) $mirror->remaining_amount)->toBe(0.0);

    // Realized-FX G/L (original plus reversal) nets to zero.
    expect(round((float) GlEntry::query()->where('payment_application_id', $application->id)->sum(DB::raw('debit_amount - credit_amount')), 4))->toBe(0.0);

    expect(PaymentApplication::query()->where('payment_id', $payment->id)->where('reversed', false)->count())->toBe(0)
        ->and(phase3d1bVendorExposure($vendorId))->toBe(330000.0)
        ->and(phase3d1bVendorExposure($vendorId))->toBe($exposureBefore);

    expect(fn () => app(PaymentService::class)->void($payment->fresh(), 'again', $user->id))
        ->toThrow(Exception::class, 'Only posted payments can be voided.');
});

// ---------------------------------------------------------------------------
// 18. Phase 3D-1B: credit memo entry point and accessor
// ---------------------------------------------------------------------------

test('3d1b the posted purchase credit memo entry point applies against a version-2 invoice', function (): void {
    // PostedPurchaseCreditMemo::applyToInvoices() has no production UI caller
    // (verified by repo-wide search); this exercises the real public entry point.
    $vendorContext = phase3d1VendorContext();
    $user = phase3d1User();

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-3D1B-CM-ENTRY', 220.0, $vendorContext['business']->id, 'USD', 1500.0, $user->id);
    $invoiceEntry = VendorLedgerEntry::createFromInvoice($invoice);

    $memo = phase3d1bPostedCreditMemo($vendorContext, $user, 'PCM-3D1B-ENTRY', 110.0);
    $memoEntry = VendorLedgerEntry::createFromCreditMemo($memo);

    $memo->applyToInvoices([['entry_id' => $invoiceEntry->id, 'amount' => 110.0]]);

    $invoiceEntry->refresh();
    $memoEntry->refresh();

    expect((float) $invoiceEntry->original_remaining_amount)->toBe(110.0)
        ->and((float) $invoiceEntry->remaining_amount)->toBe(165000.0)
        ->and((float) $memoEntry->original_remaining_amount)->toBe(0.0)
        ->and((float) $memoEntry->remaining_amount)->toBe(0.0)
        ->and((bool) $memoEntry->fully_applied)->toBeTrue();
});

test('3d1b the purchase credit memo remaining accessor reports document currency for version-2 rows', function (): void {
    $vendorContext = phase3d1VendorContext();
    $user = phase3d1User();

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-3D1B-CM-ACCESSOR', 220.0, $vendorContext['business']->id, 'USD', 1500.0, $user->id);
    $invoiceEntry = VendorLedgerEntry::createFromInvoice($invoice);

    $memo = phase3d1bPostedCreditMemo($vendorContext, $user, 'PCM-3D1B-ACCESSOR', 110.0);
    $memoEntry = VendorLedgerEntry::createFromCreditMemo($memo);

    $memo->applyToInvoices([['entry_id' => $invoiceEntry->id, 'amount' => 30.0]]);

    // Document currency (110 - 30 = 80), never the LCY carrying amount (120,000).
    expect((float) $memo->fresh()->remaining_amount)->toBe(80.0)
        ->and((float) $memoEntry->fresh()->remaining_amount)->toBe(120000.0)
        ->and($memo->fresh()->is_fully_applied)->toBeFalse();
});

// ---------------------------------------------------------------------------
// 19. Phase 3D-1B: version-2 safety fences and semantic immutability
// ---------------------------------------------------------------------------

test('3d1b currency adjustment refuses to consume a version-2 vendor ledger entry', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-3D1B-CURADJ', 220.0, $vendorContext['business']->id, 'USD', 1500.0, $user->id);
    $entry = VendorLedgerEntry::createFromInvoice($invoice);

    $service = app(CurrencyAdjustmentService::class);

    expect(fn () => $service->revalueCurrency($currencies['usd'], now(), 'CUR-ADJ-3D1B'))
        ->toThrow(BusinessException::class, 'not version-2 aware');

    expect(fn () => $service->postRealizedGainLoss($entry, 100.0, 1550.0, 'CUR-ADJ-3D1B'))
        ->toThrow(BusinessException::class, 'not version-2 aware');

    $entry->refresh();

    expect(CurrencyAdjustmentLedger::query()->count())->toBe(0)
        ->and((float) $entry->currency_factor)->toBe(1500.0)
        ->and((float) $entry->remaining_amount)->toBe(330000.0);
});

test('3d1b the legacy bank-ledger vendor mutation path cannot touch a version-2 entry', function (): void {
    $vendorContext = phase3d1VendorContext();
    $user = phase3d1User();
    phase3d1bEnsureBankLedgerNumberSeries();

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-3D1B-BANKFENCE', 220.0, $vendorContext['business']->id, 'USD', 1500.0, $user->id);
    $entry = VendorLedgerEntry::createFromInvoice($invoice);

    $bankAccount = BankAccount::factory()->create(['current_balance' => 1000, 'available_balance' => 1000]);
    $before = BankAccountLedgerEntry::query()->count();

    expect(fn () => app(BankAccountLedgerService::class)->postPayment($bankAccount, [
        'amount' => 50.0,
        'posting_date' => now(),
        'document_date' => now(),
        'description' => 'fence probe',
        'document_no' => 'BNK-3D1B-FENCE',
        'user_id' => $user->id,
    ], $entry))->toThrow(BusinessException::class, 'not version-2 aware');

    $entry->refresh();

    expect((float) $entry->remaining_amount)->toBe(330000.0)
        ->and((float) $entry->original_remaining_amount)->toBe(220.0)
        ->and(BankAccountLedgerEntry::query()->count())->toBe($before)
        ->and(BankAccountLedgerEntry::query()->where('document_no', 'BNK-3D1B-FENCE')->exists())->toBeFalse();
});

test('3d1b a legacy vendor ledger entry cannot be promoted to version 2 by a generic update', function (): void {
    $vendorContext = phase3d1VendorContext();
    $currencies = phase3d1Currencies();
    $user = phase3d1User();

    $legacy = VendorLedgerEntry::query()->create([
        'entry_number' => 1,
        'vendor_id' => $vendorContext['vendor']->id,
        'business_id' => $vendorContext['business']->id,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => 'PI-3D1B-PROMOTE',
        'description' => 'legacy row',
        'posting_date' => now(),
        'document_date' => now(),
        'debit_amount' => 0,
        'credit_amount' => 100.0,
        'amount' => 100.0,
        'running_balance' => 100.0,
        'remaining_amount' => 100.0,
        'open' => true,
        'currency_id' => $currencies['usd']->id,
        'currency_code' => 'USD',
        'currency_factor' => 1500.0,
        'original_credit_amount' => 100.0,
        'created_by' => $user->id,
    ]);

    expect(fn () => $legacy->update(['ledger_semantics_version' => 2]))
        ->toThrow(BusinessException::class, 'cannot be promoted to version 2');

    expect(fn () => $legacy->fresh()->forceFill(['ledger_semantics_version' => 2])->save())
        ->toThrow(BusinessException::class, 'cannot be promoted to version 2');

    expect($legacy->fresh()->ledger_semantics_version)->toBeNull();
});

test('3d1b version-2 remaining amounts stay mutable while the marker stays immutable', function (): void {
    $vendorContext = phase3d1VendorContext();
    $user = phase3d1User();

    $invoice = phase3d1Invoice($vendorContext['vendor']->id, 'PI-3D1B-MUTABLE', 220.0, $vendorContext['business']->id, 'USD', 1500.0, $user->id);
    $entry = VendorLedgerEntry::createFromInvoice($invoice);

    $entry->update(['remaining_amount' => 180000.0, 'original_remaining_amount' => 120.0]);

    $entry->refresh();

    expect((float) $entry->remaining_amount)->toBe(180000.0)
        ->and((float) $entry->original_remaining_amount)->toBe(120.0)
        ->and($entry->ledger_semantics_version)->toBe(2);
});

// ---------------------------------------------------------------------------
// Phase 3D-1B helpers
// ---------------------------------------------------------------------------

function phase3d1bPayablesMismatchFor(int $postingGroupId): ?array
{
    return collect(phase3d1ReconcileReport()['vendor_ledger_payables_mismatches'] ?? [])
        ->firstWhere('posting_group_id', $postingGroupId);
}

function phase3d1bVendorExposure(int $vendorId): float
{
    return (float) VendorLedgerEntry::query()
        ->where('vendor_id', $vendorId)
        ->where('reversed', false)
        ->sum(DB::raw(LedgerSemantics::signedLcyRemainingSql('vendor_ledger_entries')));
}

function phase3d1bGlEntry(
    ChartOfAccount $account,
    int $businessId,
    User $user,
    string $documentNumber,
    float $documentAmount,
    float $lcyAmount,
    bool $isDebit,
    string $documentType = 'PURCHASE_INVOICE',
): GlEntry {
    return GlEntry::query()->create([
        'entry_number' => ((int) GlEntry::query()->max('entry_number')) + 1,
        'transaction_number' => ((int) GlEntry::query()->max('transaction_number')) + 1,
        'user_id' => $user->id,
        'business_id' => $businessId,
        'chart_of_account_id' => $account->id,
        'posting_date' => now(),
        'document_date' => now(),
        'document_type' => $documentType,
        'document_number' => $documentNumber,
        'description' => 'Reconcile fixture control entry',
        'debit_amount' => $isDebit ? $documentAmount : 0,
        'credit_amount' => $isDebit ? 0 : $documentAmount,
        'amount' => $isDebit ? $documentAmount : -$documentAmount,
        'amount_lcy' => $isDebit ? $lcyAmount : -$lcyAmount,
        'debit_amount_lcy' => $isDebit ? $lcyAmount : 0,
        'credit_amount_lcy' => $isDebit ? 0 : $lcyAmount,
    ]);
}

function phase3d1bLegacyInvoiceEntry(array $vendorContext, array $currencies, User $user, string $number, float $documentAmount, float $factor): VendorLedgerEntry
{
    return VendorLedgerEntry::query()->create([
        'entry_number' => ((int) VendorLedgerEntry::query()->where('vendor_id', $vendorContext['vendor']->id)->max('entry_number')) + 1,
        'vendor_id' => $vendorContext['vendor']->id,
        'business_id' => $vendorContext['business']->id,
        'vendor_posting_group_id' => $vendorContext['vpg']->id,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => $number,
        'description' => 'legacy FCY invoice',
        'posting_date' => now(),
        'document_date' => now(),
        'debit_amount' => 0,
        'credit_amount' => $documentAmount,
        'amount' => $documentAmount,
        'running_balance' => $documentAmount,
        'remaining_amount' => $documentAmount,
        'open' => true,
        'fully_applied' => false,
        'reversed' => false,
        'currency_id' => $currencies['usd']->id,
        'currency_code' => 'USD',
        'currency_factor' => $factor,
        'original_credit_amount' => $documentAmount,
        'created_by' => $user->id,
    ]);
}

function phase3d1bV2InvoiceEntry(array $vendorContext, string $number, float $documentAmount, float $factor, User $user): VendorLedgerEntry
{
    $invoice = phase3d1Invoice($vendorContext['vendor']->id, $number, $documentAmount, $vendorContext['business']->id, 'USD', $factor, $user->id);

    return VendorLedgerEntry::createFromInvoice($invoice);
}

function phase3d1bPostedPayment(array $vendorContext, string $currencyCode, int $currencyId, float $amount, float $factor, int $userId, string $number): Payment
{
    $payment = new Payment;
    $payment->payment_number = $number;
    $payment->payment_date = now();
    $payment->posting_date = now();
    $payment->party_type = 'VENDOR';
    $payment->party_id = $vendorContext['vendor']->id;
    $payment->party_name = $vendorContext['vendor']->vendor_name;
    $payment->business_id = $vendorContext['business']->id;
    $payment->payment_method = 'BANK_TRANSFER';
    $payment->payment_amount = $amount;
    $payment->payment_amount_lcy = $amount * $factor;
    $payment->currency_code = $currencyCode;
    $payment->currency_factor = $factor;
    $payment->currency_id = $currencyId;
    $payment->status = 'POSTED';
    $payment->payment_direction = 'DISBURSEMENT';
    $payment->applied_amount = 0;
    $payment->unapplied_amount = $amount;
    $payment->created_by = $userId;
    $payment->save();

    return $payment;
}

function phase3d1bApprovedVendorPayment(array $vendorContext, string $currencyCode, int $currencyId, float $amount, float $factor, int $bankAccountId, int $userId, string $number): Payment
{
    $payment = phase3d1bPostedPayment($vendorContext, $currencyCode, $currencyId, $amount, $factor, $userId, $number);
    $payment->forceFill([
        'status' => 'APPROVED',
        'bank_account_id' => $bankAccountId,
    ])->save();

    return $payment->fresh();
}

function phase3d1bPostedCreditMemo(array $vendorContext, User $user, string $number, float $documentAmount, string $currencyCode = 'USD', float $factor = 1500.0): PostedPurchaseCreditMemo
{
    return PostedPurchaseCreditMemo::query()->create([
        'business_id' => $vendorContext['business']->id,
        'document_number' => $number,
        'vendor_id' => $vendorContext['vendor']->id,
        'vendor_name' => $vendorContext['vendor']->vendor_name,
        'posting_date' => now(),
        'document_date' => now(),
        'currency_code' => $currencyCode,
        'currency_factor' => $factor,
        'grand_total' => $documentAmount,
        'posted' => true,
        'posted_at' => now(),
        'posted_by' => $user->id,
    ]);
}

function phase3d1bEnsureBankLedgerNumberSeries(): void
{
    $series = NumberSeries::query()->firstOrCreate(
        ['code' => 'BANK-LEDGER'],
        [
            'description' => 'Bank Ledger Entries',
            'prefix' => '',
            'starting_number' => 1,
            'ending_number' => null,
            'current_number' => 0,
            'year' => 2026,
            'is_active' => true,
            'allow_manual' => false,
            'module' => 'finance',
        ],
    );

    NumberSeriesLine::query()->firstOrCreate(
        ['number_series_id' => $series->id, 'starting_date' => now()->startOfYear()->toDateString()],
        [
            'prefix' => '',
            'suffix' => '',
            'starting_no' => 0,
            'ending_no' => null,
            'increment_by' => 1,
            'last_no_used' => 0,
            'no_of_digits' => 6,
            'blocked' => false,
        ],
    );
}

/**
 * Real PurchaseInvoiceService posting context: a single non-inventory (service)
 * line avoids the inventory/value-entry machinery while still exercising the
 * production liability posting path.
 *
 * @return array<string, mixed>
 */
function phase3d1bPostingContext(): array
{
    phase3d1Currencies();

    GeneralLedgerSetup::query()->updateOrCreate(
        ['company_name' => 'Default Company'],
        ['allow_posting_from' => '2026-01-01', 'allow_posting_to' => '2026-12-31'],
    );

    $user = phase3d1User();
    $location = Location::factory()->create(['code' => 'MAIN']);
    $business = Business::query()->create(['code' => 'B-3D1B', 'name' => 'Phase 3D-1B Business']);
    session(['active_business_id' => $business->id]);

    $payables = ChartOfAccount::factory()->create([
        'account_number' => '2110',
        'name' => 'Accounts Payable 3D1B',
        'account_category' => AccountCategory::LIABILITY,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    $expense = ChartOfAccount::factory()->create([
        'account_number' => '5110',
        'name' => 'Purchases 3D1B',
        'account_category' => AccountCategory::DIRECT_EXPENSE,
        'income_balance' => IncomeBalanceType::INCOME_STATEMENT,
    ]);

    $businessGroup = GeneralBusinessPostingGroup::query()->create(['code' => 'DOMESTIC', 'description' => 'Domestic', 'blocked' => false]);
    $productGroup = GeneralProductPostingGroup::query()->create(['code' => 'SERVICES', 'description' => 'Services', 'blocked' => false]);
    $inventoryGroup = InventoryPostingGroup::query()->create(['code' => 'SERVICES', 'description' => 'Services', 'blocked' => false]);
    $vendorPostingGroup = VendorPostingGroup::query()->create([
        'code' => 'DOMESTIC',
        'description' => 'Domestic Vendors',
        'payables_account_id' => $payables->id,
        'blocked' => false,
    ]);

    GeneralPostingSetup::query()->create([
        'general_business_posting_group_id' => $businessGroup->id,
        'general_product_posting_group_id' => $productGroup->id,
        'purchase_account_id' => $expense->id,
        'blocked' => false,
    ]);

    $vendor = Vendor::factory()->create([
        'general_business_posting_group_id' => $businessGroup->id,
        'vendor_posting_group_id' => $vendorPostingGroup->id,
    ]);

    $item = Item::query()->create([
        'item_code' => 'SVC-3D1B',
        'description' => 'Consulting Service',
        'item_type' => ItemType::SERVICE,
        'unit_cost' => 0,
        'inventory' => 0,
        'general_product_posting_group_id' => $productGroup->id,
        'inventory_posting_group_id' => $inventoryGroup->id,
    ]);

    return compact('user', 'location', 'business', 'payables', 'expense', 'businessGroup', 'productGroup', 'vendorPostingGroup', 'vendor', 'item');
}

function phase3d1bPostInvoiceThroughService(array $ctx, string $number, float $documentTotal, string $currencyCode, float $factor): PostedPurchaseInvoice
{
    $invoice = PurchaseInvoice::query()->create([
        'business_id' => $ctx['business']->id,
        'document_number' => $number,
        'vendor_id' => $ctx['vendor']->id,
        'vendor_name' => $ctx['vendor']->vendor_name,
        'general_business_posting_group_id' => $ctx['businessGroup']->id,
        'vendor_posting_group_id' => $ctx['vendorPostingGroup']->id,
        'location_id' => $ctx['location']->id,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'due_date' => now()->addDays(7)->toDateString(),
        'status' => ApprovalStatus::APPROVED,
        'total_amount' => $documentTotal,
        'total_vat' => 0,
        'grand_total' => $documentTotal,
        'remaining_amount' => $documentTotal,
        'currency_code' => $currencyCode,
        'currency_factor' => $factor,
        'approved_by' => $ctx['user']->id,
        'approved_at' => now(),
        'cancelled' => false,
    ]);

    $invoice->lines()->create([
        'line_number' => 10000,
        'item_id' => $ctx['item']->id,
        'item_code' => $ctx['item']->item_code,
        'item_description' => $ctx['item']->description,
        'general_product_posting_group_id' => $ctx['item']->general_product_posting_group_id,
        'inventory_posting_group_id' => null,
        'quantity' => 1,
        'unit_of_measure_code' => 'EA',
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => 1,
        'unit_cost' => $documentTotal,
        'unit_cost_lcy' => $documentTotal,
        'line_total' => $documentTotal,
        'vat_percentage' => 0,
        'vat_amount' => 0,
        'vat_amount_lcy' => 0,
        'amount_including_vat' => $documentTotal,
        'amount_including_vat_lcy' => $documentTotal,
        'posting_date' => $invoice->posting_date,
    ]);

    return app(PurchaseInvoiceService::class)->post($invoice);
}

/**
 * Opening-balance context: series, equity account, payable party and permissions.
 *
 * @return array<string, mixed>
 */
function phase3d1bOpeningContext(): array
{
    $business = Business::query()->create(['code' => 'B-3D1B-OB', 'name' => 'Opening Business 3D1B']);
    session(['active_business_id' => $business->id]);

    GeneralLedgerSetup::instance()->update([
        'allow_posting_from' => '2026-01-01',
        'allow_posting_to' => '2026-12-31',
    ]);

    $equity = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::EQUITY,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
        'direct_posting' => true,
        'blocked' => false,
    ]);
    GeneralLedgerSetup::instance()->update(['opening_balance_equity_account_id' => $equity->id]);

    foreach ([['CUSTOMER-OPENING', 'COB'], ['VENDOR-OPENING', 'VOB']] as [$code, $prefix]) {
        $series = NumberSeries::query()->create([
            'code' => $code, 'description' => $code, 'prefix' => $prefix,
            'starting_number' => 1, 'ending_number' => 999999, 'current_number' => 0,
            'year' => 2026, 'is_active' => true, 'module' => 'finance',
        ]);
        NumberSeriesLine::query()->create([
            'number_series_id' => $series->id, 'starting_date' => '2026-01-01',
            'starting_no' => 0, 'increment_by' => 1, 'last_no_used' => 0,
            'no_of_digits' => 5, 'blocked' => false,
        ]);
    }

    $payables = ChartOfAccount::factory()->create([
        'account_category' => AccountCategory::LIABILITY,
        'income_balance' => IncomeBalanceType::BALANCE_SHEET,
    ]);
    $vendor = Vendor::factory()->create();
    VendorPostingGroup::query()->findOrFail($vendor->vendor_posting_group_id)->update(['payables_account_id' => $payables->id]);

    $user = phase3d1User([
        'finance.subledger_opening_balance.view',
        'finance.subledger_opening_balance.create',
        'finance.subledger_opening_balance.update',
        'finance.subledger_opening_balance.post',
        'finance.subledger_opening_balance.reverse',
    ]);

    return compact('business', 'vendor', 'user', 'payables');
}
