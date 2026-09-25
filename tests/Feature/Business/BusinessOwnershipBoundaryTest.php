<?php

declare(strict_types=1);

use App\Enums\ApprovalStatus;
use App\Enums\PurchaseOrderStatus;
use App\Exceptions\BusinessException;
use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Models\Business;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerPostingGroup;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GeneralLedgerSetup;
use App\Models\GlEntry;
use App\Models\Location;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\PostedPurchaseInvoice;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\Role;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPostingGroup;
use App\Services\Business\BusinessOwnershipService;
use App\Services\Finance\PaymentService;
use App\Services\PostingService;
use App\Services\Purchase\PurchaseInvoiceService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Purchase Order creation
// ---------------------------------------------------------------------------

it('assigns the active business to a new purchase order', function (): void {
    $business = bobBusiness('B-ACTIVE');
    session(['active_business_id' => $business->id]);

    $order = bobPurchaseOrder(['business_id' => null]);

    expect($order->business_id)->toBe($business->id);
});

it('preserves an explicitly supplied business on purchase order creation', function (): void {
    $explicit = bobBusiness('B-EXPLICIT');
    bobBusiness('B-OTHER');
    session()->forget('active_business_id');

    $order = bobPurchaseOrder(['business_id' => $explicit->id]);

    expect($order->business_id)->toBe($explicit->id);
});

it('fails closed when a purchase order has no business ownership', function (): void {
    bobBusiness('B-ONLY');
    session()->forget('active_business_id');

    expect(fn () => bobPurchaseOrder(['business_id' => null]))
        ->toThrow(BusinessException::class);

    expect(PurchaseOrder::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Purchase Invoice creation / ownership propagation
// ---------------------------------------------------------------------------

it('makes a purchase order linked invoice inherit the order business', function (): void {
    $business = bobBusiness('B-INHERIT');
    session(['active_business_id' => $business->id]);

    $order = bobPurchaseOrder(['business_id' => $business->id]);
    $invoice = bobPurchaseInvoice(['order_id' => $order->id, 'business_id' => null]);

    expect($invoice->business_id)->toBe($business->id);
});

it('rejects a purchase invoice whose business conflicts with its purchase order', function (): void {
    $a = bobBusiness('B-A');
    $b = bobBusiness('B-B');
    session(['active_business_id' => $a->id]);

    $order = bobPurchaseOrder(['business_id' => $a->id]);

    expect(fn () => bobPurchaseInvoice(['order_id' => $order->id, 'business_id' => $b->id]))
        ->toThrow(BusinessException::class);
});

it('fails closed when a purchase invoice is linked to a null owned purchase order', function (): void {
    $business = bobBusiness('B-NULLPO');
    session(['active_business_id' => $business->id]);

    $order = bobPurchaseOrder(['business_id' => $business->id]);
    $order->forceFill(['business_id' => null])->saveQuietly();

    expect(fn () => bobPurchaseInvoice(['order_id' => $order->id]))
        ->toThrow(BusinessException::class);
});

it('fails closed when a standalone purchase invoice has no business ownership', function (): void {
    bobBusiness('B-STANDALONE');
    session()->forget('active_business_id');

    expect(fn () => bobPurchaseInvoice(['business_id' => null]))
        ->toThrow(BusinessException::class);
});

it('refuses to post a purchase invoice without authoritative business ownership before any side effect', function (): void {
    $business = bobBusiness('B-POST');
    session()->forget('active_business_id');

    $invoice = bobPurchaseInvoice(['business_id' => $business->id, 'status' => ApprovalStatus::APPROVED]);
    bobInvoiceLine($invoice);
    $invoice->forceFill(['business_id' => null])->saveQuietly();
    $invoice->refresh();

    expect(fn () => app(PurchaseInvoiceService::class)->post($invoice))
        ->toThrow(BusinessException::class);

    expect(PostedPurchaseInvoice::query()->count())->toBe(0)
        ->and(VendorLedgerEntry::query()->count())->toBe(0)
        ->and(GlEntry::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Payment creation / posting
// ---------------------------------------------------------------------------

it('fails closed when a payment has no business ownership', function (): void {
    $vendor = Vendor::factory()->create();
    bobBusiness('B-PAY');
    session()->forget('active_business_id');

    expect(fn () => bobPayment($vendor, ['business_id' => null]))
        ->toThrow(BusinessException::class);

    expect(Payment::query()->count())->toBe(0);
});

it('refuses to post a payment without business ownership before any side effect', function (): void {
    $user = bobSuperAdmin();
    $business = bobBusiness('B-PAYPOST');
    bobPostingCalendar();
    $bankAccount = bobBankAccount('NGN');
    $vendor = Vendor::factory()->create();

    $payment = bobPayment($vendor, [
        'business_id' => $business->id,
        'bank_account_id' => $bankAccount->id,
        'status' => 'APPROVED',
        'payment_amount' => 100,
        'payment_amount_lcy' => 100,
        'unapplied_amount' => 100,
    ]);

    // Simulate a legacy null-owned payment.
    $payment->forceFill(['business_id' => null])->saveQuietly();

    expect(fn () => app(PaymentService::class)->post($payment->refresh(), $user->id))
        ->toThrow(BusinessException::class);

    expect(BankAccountLedgerEntry::query()->count())->toBe(0)
        ->and(VendorLedgerEntry::query()->count())->toBe(0)
        ->and(GlEntry::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Payment application — strict business match
// ---------------------------------------------------------------------------

it('applies a payment to a document owned by the same business', function (): void {
    $user = bobSuperAdmin();
    $business = bobBusiness('B-APP');
    bobPostingCalendar();
    $vendor = Vendor::factory()->create();

    $payment = bobPostedPayment($vendor, $business);
    $document = bobPostedInvoice($vendor, $business);

    $application = app(PaymentService::class)->applyToDocument($payment, [
        'document_type' => 'PURCHASE_INVOICE',
        'document_id' => $document->id,
        'amount' => 100,
    ], $user->id);

    expect($application->business_id)->toBe($business->id)
        ->and((float) $application->amount_applied)->toBe(100.0);
});

it('rejects payment application for every null or mismatched business combination', function (): void {
    $user = bobSuperAdmin();
    $a = bobBusiness('B-APP-A');
    $b = bobBusiness('B-APP-B');
    bobPostingCalendar();
    $vendor = Vendor::factory()->create();

    $paymentA = bobPostedPayment($vendor, $a);
    $paymentNull = bobPostedPayment($vendor, $a, nullifyBusiness: true);
    $docA = bobPostedInvoice($vendor, $a);
    $docB = bobPostedInvoice($vendor, $b);
    $docNull = bobPostedInvoice($vendor, $a, nullifyBusiness: true);

    $apply = fn (Payment $payment, PostedPurchaseInvoice $document) => app(PaymentService::class)
        ->applyToDocument($payment, [
            'document_type' => 'PURCHASE_INVOICE',
            'document_id' => $document->id,
            'amount' => 50,
        ], $user->id);

    // NULL ↔ NULL
    expect(fn () => $apply($paymentNull->fresh(), $docNull->fresh()))->toThrow(Exception::class);
    // NULL payment ↔ business document
    expect(fn () => $apply($paymentNull->fresh(), $docA->fresh()))->toThrow(Exception::class);
    // business payment ↔ NULL document
    expect(fn () => $apply($paymentA->fresh(), $docNull->fresh()))->toThrow(Exception::class);
    // business A ↔ business B
    expect(fn () => $apply($paymentA->fresh(), $docB->fresh()))->toThrow(Exception::class);

    expect(PaymentApplication::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Legacy payment G/L ownership propagation
// ---------------------------------------------------------------------------

it('propagates the payment business to legacy disbursement G/L entries', function (): void {
    $business = bobBusiness('B-DISB');
    bobPostingCalendar();
    $vendor = bobVendorWithPayables();
    $bankAccount = bobBankAccount('NGN');

    $entries = app(PostingService::class)->postPaymentDisbursement(
        vendor: $vendor,
        amount: 100,
        bankAccount: $bankAccount,
        discount: 0,
        postingDate: now(),
        documentNumber: 'PAY-PROP-001',
        currencyId: $bankAccount->currency_id,
        exchangeRate: 1.0,
        vendorLedgerEntryId: null,
        businessId: $business->id,
    );

    expect($entries)->not->toBeEmpty();

    foreach ($entries as $entry) {
        expect($entry->business_id)->toBe($business->id);
    }
});

it('propagates the payment business to legacy receipt G/L entries', function (): void {
    $business = bobBusiness('B-RECEIPT');
    bobPostingCalendar();
    $customer = Customer::factory()->create([
        'customer_posting_group_id' => bobCustomerPostingGroupWithReceivables()->id,
        'general_business_posting_group_id' => bobGeneralBusinessPostingGroup()->id,
    ]);
    $bankAccount = bobBankAccount('NGN');

    $entries = app(PostingService::class)->postPaymentReceipt(
        customer: $customer,
        amount: 100,
        bankAccount: $bankAccount,
        discount: 0,
        postingDate: now(),
        documentNumber: 'PAY-PROP-002',
        currencyId: $bankAccount->currency_id,
        exchangeRate: 1.0,
        customerLedgerEntryId: null,
        businessId: $business->id,
    );

    expect($entries)->not->toBeEmpty();

    foreach ($entries as $entry) {
        expect($entry->business_id)->toBe($business->id);
    }
});

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function bobBusiness(string $code): Business
{
    return Business::query()->create([
        'code' => $code,
        'name' => $code,
        'is_active' => true,
    ]);
}

function bobSuperAdmin(): User
{
    $role = Role::query()->firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);
    $user = User::factory()->create();
    $user->assignRole($role);

    return $user;
}

function bobPostingCalendar(): void
{
    GeneralLedgerSetup::query()->updateOrCreate(
        ['company_name' => 'Default Company'],
        ['allow_posting_from' => '2026-01-01', 'allow_posting_to' => '2026-12-31'],
    );

    AccountingPeriod::query()->firstOrCreate(
        ['name' => 'FY2026'],
        ['start_date' => '2026-01-01', 'end_date' => '2026-12-31', 'is_closed' => false],
    );

    Currency::query()->firstOrCreate(
        ['code' => 'NGN'],
        [
            'description' => 'Nigerian Naira',
            'symbol' => '₦',
            'decimal_places' => 2,
            'is_active' => true,
            'is_lcy' => true,
            'exchange_rate' => 1.0,
        ],
    );

    // Bank ledger entries require their number series before posting.
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
 * @param  array<string, mixed>  $overrides
 */
function bobPurchaseOrder(array $overrides = []): PurchaseOrder
{
    $vendor = Vendor::factory()->create();
    $location = Location::factory()->create();

    return PurchaseOrder::query()->create(array_merge([
        'order_number' => 'PO-BOB-'.substr(uniqid(), -6),
        'status' => PurchaseOrderStatus::APPROVED,
        'vendor_id' => $vendor->id,
        'vendor_name' => $vendor->vendor_name,
        'order_date' => now()->toDateString(),
        'posting_date' => now()->toDateString(),
        'location_id' => $location->id,
        'currency_code' => 'NGN',
        'created_by' => bobSuperAdmin()->id,
        'total_amount' => 0,
        'grand_total' => 0,
    ], $overrides));
}

/**
 * @param  array<string, mixed>  $overrides
 */
function bobPurchaseInvoice(array $overrides = []): PurchaseInvoice
{
    $vendor = Vendor::factory()->create();

    return PurchaseInvoice::query()->create(array_merge([
        'document_number' => 'PI-BOB-'.substr(uniqid(), -6),
        'vendor_id' => $vendor->id,
        'vendor_name' => $vendor->vendor_name,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'due_date' => now()->toDateString(),
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'status' => ApprovalStatus::DRAFT,
        'total_amount' => 100,
        'total_vat' => 0,
        'grand_total' => 100,
        'remaining_amount' => 100,
    ], $overrides));
}

function bobInvoiceLine(PurchaseInvoice $invoice): void
{
    $invoice->lines()->create([
        'line_number' => 10000,
        'item_code' => 'BOB-ITEM',
        'item_description' => 'Ownership test line',
        'quantity' => 1,
        'unit_of_measure_code' => 'PCS',
        'qty_per_unit_of_measure' => 1,
        'quantity_base' => 1,
        'unit_cost' => 100,
        'unit_cost_lcy' => 100,
        'line_total' => 100,
        'amount_including_vat' => 100,
        'amount_including_vat_lcy' => 100,
        'posting_date' => $invoice->posting_date,
    ]);
}

/**
 * @param  array<string, mixed>  $overrides
 */
function bobPayment(Vendor $vendor, array $overrides = []): Payment
{
    return Payment::factory()->create(array_merge([
        'party_id' => $vendor->id,
        'party_name' => $vendor->vendor_name,
        'payment_direction' => 'DISBURSEMENT',
        'payment_amount' => 100,
        'payment_amount_lcy' => 100,
        'applied_amount' => 0,
        'unapplied_amount' => 100,
        'status' => 'POSTED',
    ], $overrides));
}

function bobPostedPayment(Vendor $vendor, Business $business, bool $nullifyBusiness = false): Payment
{
    $payment = bobPayment($vendor, ['business_id' => $business->id]);

    if ($nullifyBusiness) {
        $payment->forceFill(['business_id' => null])->saveQuietly();
    }

    return $payment->refresh();
}

function bobPostedInvoice(Vendor $vendor, Business $business, bool $nullifyBusiness = false): PostedPurchaseInvoice
{
    $document = PostedPurchaseInvoice::query()->create([
        'document_number' => 'PPI-BOB-'.substr(uniqid(), -6),
        'vendor_id' => $vendor->id,
        'vendor_name' => $vendor->vendor_name,
        'business_id' => $business->id,
        'posting_date' => now()->toDateString(),
        'document_date' => now()->toDateString(),
        'due_date' => now()->toDateString(),
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'total_amount' => 1000,
        'grand_total' => 1000,
        'remaining_amount' => 1000,
        'paid_in_full' => false,
        'cancelled' => false,
    ]);

    if ($nullifyBusiness) {
        $document->forceFill(['business_id' => null])->saveQuietly();
    }

    return $document->refresh();
}

function bobBankAccount(string $currencyCode): BankAccount
{
    $currency = Currency::query()->where('code', $currencyCode)->firstOrFail();
    $glAccount = ChartOfAccount::query()->create([
        'account_number' => 'BANK-'.substr(uniqid(), -6),
        'name' => 'Bank GL',
        'account_category' => 'asset',
        'income_balance' => '0',
        'direct_posting' => true,
        'blocked' => false,
    ]);

    return BankAccount::factory()->create([
        'currency_id' => $currency->id,
        'gl_account_id' => $glAccount->id,
        'current_balance' => 100000,
        'available_balance' => 100000,
        'allow_payments' => true,
        'allow_receipts' => true,
        'active' => true,
    ]);
}

function bobVendorWithPayables(): Vendor
{
    $payables = ChartOfAccount::query()->create([
        'account_number' => 'AP-'.substr(uniqid(), -6),
        'name' => 'Accounts Payable',
        'account_category' => 'payable',
        'income_balance' => '0',
        'direct_posting' => true,
        'blocked' => false,
    ]);

    $vendorPostingGroup = VendorPostingGroup::query()->create([
        'code' => 'BOB-'.substr(uniqid(), -6),
        'description' => 'Bob vendors',
        'payables_account_id' => $payables->id,
        'blocked' => false,
    ]);

    return Vendor::factory()->create([
        'vendor_posting_group_id' => $vendorPostingGroup->id,
    ]);
}

function bobGeneralBusinessPostingGroup(): GeneralBusinessPostingGroup
{
    return GeneralBusinessPostingGroup::query()->create([
        'code' => 'BOB-'.substr(uniqid(), -6),
        'description' => 'Bob business group',
        'blocked' => false,
    ]);
}

function bobCustomerPostingGroupWithReceivables(): CustomerPostingGroup
{
    $receivables = ChartOfAccount::query()->create([
        'account_number' => 'AR-'.substr(uniqid(), -6),
        'name' => 'Accounts Receivable',
        'account_category' => 'receivable',
        'income_balance' => '0',
        'direct_posting' => true,
        'blocked' => false,
    ]);

    return CustomerPostingGroup::query()->create([
        'code' => 'BOB-'.substr(uniqid(), -6),
        'description' => 'Bob customers',
        'receivables_account_id' => $receivables->id,
        'blocked' => false,
    ]);
}

// ---------------------------------------------------------------------------
// Phase 1C — 1. PI posting must never adopt the active session
// ---------------------------------------------------------------------------

it('does not adopt the active session when posting a null-owned standalone purchase invoice', function (): void {
    $owner = bobBusiness('B-P1C-OWNER');
    $activeSession = bobBusiness('B-P1C-SESSION');

    $invoice = bobPurchaseInvoice(['business_id' => $owner->id, 'status' => ApprovalStatus::APPROVED]);
    bobInvoiceLine($invoice);
    $invoice->forceFill(['business_id' => null])->saveQuietly();
    $invoice->refresh();

    // A perfectly valid active business session exists — posting must ignore it.
    session(['active_business_id' => $activeSession->id]);

    expect(fn () => app(PurchaseInvoiceService::class)->post($invoice))
        ->toThrow(BusinessException::class);

    expect($invoice->fresh()->business_id)->toBeNull()
        ->and(PostedPurchaseInvoice::query()->count())->toBe(0)
        ->and(VendorLedgerEntry::query()->count())->toBe(0)
        ->and(GlEntry::query()->count())->toBe(0)
        ->and(BankAccountLedgerEntry::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Phase 1C — 2. Authoritative validation vs authorized context
// ---------------------------------------------------------------------------

it('rejects a nonexistent business id as authoritative ownership', function (): void {
    expect(fn () => app(BusinessOwnershipService::class)->requirePersistedId(987654, 'purchase order'))
        ->toThrow(BusinessException::class);
});

it('rejects an inactive business as authoritative ownership', function (): void {
    $inactive = Business::query()->create([
        'code' => 'B-P1C-INACTIVE',
        'name' => 'Inactive Business',
        'is_active' => false,
    ]);

    expect(fn () => app(BusinessOwnershipService::class)->requirePersistedId($inactive->id, 'purchase order'))
        ->toThrow(BusinessException::class);
});

it('rejects an authenticated user who is not authorized for the selected business', function (): void {
    $allowed = bobBusiness('B-P1C-ALLOWED');
    $other = bobBusiness('B-P1C-OTHER');

    $user = User::factory()->create();
    $user->businesses()->attach($allowed->id, ['granted_by' => $user->id]);
    $this->actingAs($user);

    expect(fn () => app(BusinessOwnershipService::class)->requireActiveContextId($other->id, 'purchase order'))
        ->toThrow(BusinessException::class);
});

it('accepts an authorized active business for the authenticated user', function (): void {
    $allowed = bobBusiness('B-P1C-AUTH-OK');

    $user = User::factory()->create();
    $user->businesses()->attach($allowed->id, ['granted_by' => $user->id]);
    $this->actingAs($user);

    expect(app(BusinessOwnershipService::class)->requireActiveContextId($allowed->id, 'purchase order'))
        ->toBe($allowed->id);
});

it('accepts trusted persisted parent ownership without replacing it from the session', function (): void {
    $sessionBusiness = bobBusiness('B-P1C-SESSION-2');
    $parentBusiness = bobBusiness('B-P1C-PARENT');
    session(['active_business_id' => $sessionBusiness->id]);

    expect(app(BusinessOwnershipService::class)->requirePersistedId($parentBusiness->id, 'purchase order'))
        ->toBe($parentBusiness->id);
});

// ---------------------------------------------------------------------------
// Phase 1C — 3. Vendor payment VLE ownership
// ---------------------------------------------------------------------------

it('stamps the payment business on the vendor payment ledger and ordinary G/L', function (): void {
    $user = bobSuperAdmin();
    $business = bobBusiness('B-P1C-VLE');
    bobPostingCalendar();
    $bankAccount = bobBankAccount('NGN');
    $vendor = bobVendorWithPayables();

    $payment = bobPayment($vendor, [
        'business_id' => $business->id,
        'bank_account_id' => $bankAccount->id,
        'status' => 'APPROVED',
        'payment_amount' => 100,
        'payment_amount_lcy' => 100,
        'unapplied_amount' => 100,
    ]);

    app(PaymentService::class)->post($payment, $user->id);

    $ledger = VendorLedgerEntry::query()
        ->where('document_type', 'PAYMENT')
        ->where('document_number', $payment->payment_number)
        ->firstOrFail();

    expect($ledger->business_id)->toBe($business->id)
        ->and((int) $ledger->ledger_semantics_version)->toBe(2);

    $glBusinessIds = GlEntry::query()
        ->where('document_number', $payment->payment_number)
        ->pluck('business_id')
        ->unique()
        ->values()
        ->all();

    expect($glBusinessIds)->toBe([$business->id]);
});

// ---------------------------------------------------------------------------
// Phase 1C — 4. Realized FX and reversal ownership
// ---------------------------------------------------------------------------

it('stamps the application business on every realized-FX vendor row', function (): void {
    $business = bobBusiness('B-P1C-FX-V');
    bobPostingCalendar();
    $currency = bobUsdCurrency();
    $vendor = bobVendorWithPayables();

    $application = bobFxApplication($business, 'VENDOR', $vendor, $currency, 10000.0);

    $entries = app(PostingService::class)->postRealizedGainLoss($application);

    expect($entries)->not->toBeEmpty();

    foreach ($entries as $entry) {
        expect($entry->business_id)->toBe($business->id);
    }
});

it('stamps the application business on every realized-FX customer row', function (): void {
    $business = bobBusiness('B-P1C-FX-C');
    bobPostingCalendar();
    $currency = bobUsdCurrency();
    $customer = bobCustomerWithReceivables();

    $application = bobFxApplication($business, 'CUSTOMER', $customer, $currency, 10000.0);

    $entries = app(PostingService::class)->postRealizedGainLoss($application);

    expect($entries)->not->toBeEmpty();

    foreach ($entries as $entry) {
        expect($entry->business_id)->toBe($business->id);
    }
});

it('stamps the application business on vendor realized-FX reversal rows', function (): void {
    $business = bobBusiness('B-P1C-FX-REV-V');
    bobPostingCalendar();
    $currency = bobUsdCurrency();
    $vendor = bobVendorWithPayables();

    $application = bobFxApplication($business, 'VENDOR', $vendor, $currency, 10000.0);

    app(PostingService::class)->postRealizedGainLoss($application);
    app(PostingService::class)->reverseRealizedGainLoss($application);

    $reversals = GlEntry::query()
        ->where('payment_application_id', $application->id)
        ->whereNotNull('reversal_of_gl_entry_id')
        ->get();

    expect($reversals)->not->toBeEmpty();

    foreach ($reversals as $entry) {
        expect($entry->business_id)->toBe($business->id);
    }
});

it('stamps the application business on customer realized-FX reversal rows', function (): void {
    $business = bobBusiness('B-P1C-FX-REV-C');
    bobPostingCalendar();
    $currency = bobUsdCurrency();
    $customer = bobCustomerWithReceivables();

    $application = bobFxApplication($business, 'CUSTOMER', $customer, $currency, 10000.0);

    app(PostingService::class)->postRealizedGainLoss($application);
    app(PostingService::class)->reverseRealizedGainLoss($application);

    $reversals = GlEntry::query()
        ->where('payment_application_id', $application->id)
        ->whereNotNull('reversal_of_gl_entry_id')
        ->get();

    expect($reversals)->not->toBeEmpty();

    foreach ($reversals as $entry) {
        expect($entry->business_id)->toBe($business->id);
    }
});

it('fails closed before any FX G/L when the payment application is null-owned', function (): void {
    $business = bobBusiness('B-P1C-FX-NULL');
    $currency = bobUsdCurrency();
    $vendor = bobVendorWithPayables();

    $application = bobFxApplication($business, 'VENDOR', $vendor, $currency, 10000.0);
    $application->forceFill(['business_id' => null])->saveQuietly();

    expect(fn () => app(PostingService::class)->postRealizedGainLoss($application->refresh()))
        ->toThrow(BusinessException::class);

    expect(GlEntry::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Phase 1C — 5. Business ownership is immutable after creation
// ---------------------------------------------------------------------------

it('makes purchase order business ownership immutable after creation', function (): void {
    $a = bobBusiness('B-IMM-PO-A');
    $b = bobBusiness('B-IMM-PO-B');
    session(['active_business_id' => $a->id]);

    $order = bobPurchaseOrder(['business_id' => $a->id]);

    expect(fn () => $order->update(['business_id' => $b->id]))->toThrow(BusinessException::class);

    $order->refresh();

    expect(fn () => $order->forceFill(['business_id' => null])->save())->toThrow(BusinessException::class);

    $order->refresh();
    $order->update(['comment' => 'ordinary update remains allowed']);

    expect($order->fresh()->business_id)->toBe($a->id);
});

it('makes purchase invoice business ownership immutable in draft and posted states', function (): void {
    $a = bobBusiness('B-IMM-PI-A');
    $b = bobBusiness('B-IMM-PI-B');

    $invoice = bobPurchaseInvoice(['business_id' => $a->id]);

    expect(fn () => $invoice->update(['business_id' => $b->id]))->toThrow(BusinessException::class);
    $invoice->refresh();
    expect(fn () => $invoice->forceFill(['business_id' => null])->save())->toThrow(BusinessException::class);

    $invoice->refresh();
    $invoice->forceFill(['status' => ApprovalStatus::POSTED])->saveQuietly();

    expect(fn () => $invoice->update(['business_id' => $b->id]))->toThrow(BusinessException::class);

    $invoice->refresh();
    $invoice->update(['comment' => 'x']);

    expect($invoice->fresh()->business_id)->toBe($a->id);
});

it('makes payment business ownership immutable and preserves downstream artifacts', function (): void {
    $user = bobSuperAdmin();
    $a = bobBusiness('B-IMM-PAY-A');
    $b = bobBusiness('B-IMM-PAY-B');
    bobPostingCalendar();
    $bankAccount = bobBankAccount('NGN');
    $vendor = bobVendorWithPayables();

    $payment = bobPayment($vendor, [
        'business_id' => $a->id,
        'bank_account_id' => $bankAccount->id,
        'status' => 'APPROVED',
        'payment_amount' => 100,
        'payment_amount_lcy' => 100,
        'unapplied_amount' => 100,
    ]);

    app(PaymentService::class)->post($payment, $user->id);

    expect(fn () => $payment->update(['business_id' => $b->id]))->toThrow(BusinessException::class);

    $glBusinessIds = GlEntry::query()
        ->where('document_number', $payment->payment_number)
        ->pluck('business_id')
        ->unique()
        ->values()
        ->all();

    expect($payment->fresh()->business_id)->toBe($a->id)
        ->and($glBusinessIds)->toBe([$a->id]);
});

// ---------------------------------------------------------------------------
// Phase 1C — 6. Payment-specific legacy G/L fails closed without ownership
// ---------------------------------------------------------------------------

it('fails closed before any G/L when a payment disbursement omits business ownership', function (): void {
    bobPostingCalendar();
    $vendor = bobVendorWithPayables();
    $bankAccount = bobBankAccount('NGN');

    expect(fn () => app(PostingService::class)->postPaymentDisbursement(
        vendor: $vendor,
        amount: 100,
        bankAccount: $bankAccount,
        discount: 0,
        postingDate: now(),
        documentNumber: 'PAY-P1C-NULL-001',
    ))->toThrow(BusinessException::class);

    expect(GlEntry::query()->count())->toBe(0);
});

it('fails closed before any G/L when a payment receipt omits business ownership', function (): void {
    bobPostingCalendar();
    $customer = bobCustomerWithReceivables();
    $bankAccount = bobBankAccount('NGN');

    expect(fn () => app(PostingService::class)->postPaymentReceipt(
        customer: $customer,
        amount: 100,
        bankAccount: $bankAccount,
        discount: 0,
        postingDate: now(),
        documentNumber: 'PAY-P1C-NULL-002',
    ))->toThrow(BusinessException::class);

    expect(GlEntry::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Phase 1C helpers
// ---------------------------------------------------------------------------

function bobAccount(string $number, string $name, string $category): ChartOfAccount
{
    return ChartOfAccount::query()->create([
        'account_number' => $number,
        'name' => $name,
        'account_category' => $category,
        'income_balance' => '0',
        'direct_posting' => true,
        'blocked' => false,
    ]);
}

function bobUsdCurrency(): Currency
{
    return Currency::query()->create([
        'code' => 'USD',
        'description' => 'US Dollar',
        'symbol' => '$',
        'decimal_places' => 2,
        'is_active' => true,
        'is_lcy' => false,
        'exchange_rate' => 1500,
        'realized_gains_account_id' => bobAccount('FXG-'.substr(uniqid(), -4), 'Realized Gain', 'revenue')->id,
        'realized_losses_account_id' => bobAccount('FXL-'.substr(uniqid(), -4), 'Realized Loss', 'direct_expense')->id,
    ]);
}

function bobCustomerWithReceivables(): Customer
{
    return Customer::factory()->create([
        'customer_posting_group_id' => bobCustomerPostingGroupWithReceivables()->id,
        'general_business_posting_group_id' => bobGeneralBusinessPostingGroup()->id,
    ]);
}

function bobFxApplication(Business $business, string $partyType, object $party, Currency $currency, float $gainLossAmount): PaymentApplication
{
    $payment = Payment::factory()->create([
        'party_type' => $partyType,
        'party_id' => $party->id,
        'party_name' => $partyType === 'CUSTOMER' ? $party->name : $party->vendor_name,
        'business_id' => $business->id,
        'currency_id' => $currency->id,
        'currency_code' => $currency->code,
        'currency_factor' => 1500,
        'payment_direction' => $partyType === 'CUSTOMER' ? 'RECEIPT' : 'DISBURSEMENT',
        'payment_amount' => 100,
        'payment_amount_lcy' => 150000,
        'applied_amount' => 100,
        'unapplied_amount' => 0,
        'status' => 'POSTED',
    ]);

    return PaymentApplication::query()->create([
        'payment_id' => $payment->id,
        'business_id' => $business->id,
        'document_type' => 'PURCHASE_INVOICE',
        'document_id' => 1,
        'document_number' => 'PPI-P1C-FX',
        'document_original_amount' => 100,
        'document_remaining_before' => 100,
        'amount_applied' => 100,
        'amount_applied_lcy' => 150000,
        'document_amount_applied_lcy' => 140000,
        'gain_loss_amount' => $gainLossAmount,
        'document_remaining_after' => 0,
        'currency_id' => $currency->id,
        'applied_by' => $payment->created_by,
        'applied_at' => now(),
    ]);
}
