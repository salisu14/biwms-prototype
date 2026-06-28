<?php

use App\Events\PaymentApplied;
use App\Events\PaymentUnapplied;
use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Models\CashReceiptLine;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\GlEntry;
use App\Models\JournalBatch;
use App\Models\JournalLine;
use App\Models\JournalTemplate;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\PaymentJournalLine;
use App\Models\Permission;
use App\Models\PostedPurchaseInvoice;
use App\Models\PostedSalesInvoice;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPostingGroup;
use App\Services\BankAccountLedgerService;
use App\Services\Finance\PaymentService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->refreshPostgresTestingDatabase();
    ensureBankLedgerNumberSeries();
});

it('creates a bank ledger entry and increases bank balance for a customer receipt', function () {
    $user = User::factory()->create();
    grantPaymentPostingPermission($user);

    $customer = Customer::factory()->create();
    $bankAccount = BankAccount::factory()->receiptOnly()->create([
        'current_balance' => 1250,
        'available_balance' => 1250,
    ]);

    $payment = Payment::factory()->customerReceipt()->create([
        'party_id' => $customer->id,
        'party_name' => $customer->name,
        'bank_account_id' => $bankAccount->id,
        'payment_amount' => 450,
        'payment_amount_lcy' => 450,
        'applied_amount' => 0,
        'unapplied_amount' => 450,
        'status' => 'APPROVED',
        'created_by' => $user->id,
    ]);

    app(PaymentService::class)->post($payment, $user->id);

    $bankEntry = BankAccountLedgerEntry::query()
        ->where('document_no', $payment->payment_number)
        ->where('bank_account_id', $bankAccount->id)
        ->first();

    expect($bankEntry)->not->toBeNull()
        ->and((float) $bankEntry->amount)->toBe(450.0)
        ->and((float) $bankEntry->debit_amount)->toBe(450.0)
        ->and((float) $bankEntry->credit_amount)->toBe(0.0)
        ->and($bankEntry->source_type)->toBe(Payment::class)
        ->and($bankEntry->source_id)->toBe($payment->id)
        ->and($bankEntry->user_id)->toBe($user->id)
        ->and((float) $bankAccount->fresh()->current_balance)->toBe(1700.0)
        ->and($payment->fresh()->status)->toBe('POSTED');

    $ledgerEntry = CustomerLedgerEntry::query()
        ->where('document_type', 'PAYMENT')
        ->where('document_number', $payment->payment_number)
        ->where('customer_id', $customer->id)
        ->first();

    expect($ledgerEntry)->not->toBeNull()
        ->and((float) $ledgerEntry->debit_amount)->toBe(0.0)
        ->and((float) $ledgerEntry->credit_amount)->toBe(450.0)
        ->and((float) $ledgerEntry->amount)->toBe(-450.0)
        ->and((float) $ledgerEntry->remaining_amount)->toBe(450.0)
        ->and($ledgerEntry->open)->toBeTrue()
        ->and((float) $customer->fresh()->balance)->toBe(-450.0);
});

it('creates a bank ledger entry and reduces bank balance for a vendor payment', function () {
    $user = User::factory()->create();
    grantPaymentPostingPermission($user);

    $payablesAccount = ChartOfAccount::factory()->create();
    $vendorPostingGroup = VendorPostingGroup::factory()->create([
        'payables_account_id' => $payablesAccount->id,
    ]);
    $vendor = Vendor::factory()->create([
        'vendor_posting_group_id' => $vendorPostingGroup->id,
    ]);
    $bankAccount = BankAccount::factory()->paymentOnly()->create([
        'current_balance' => 2000,
        'available_balance' => 2000,
    ]);

    $payment = Payment::factory()->create([
        'party_id' => $vendor->id,
        'party_name' => $vendor->vendor_name,
        'bank_account_id' => $bankAccount->id,
        'payment_amount' => 600,
        'payment_amount_lcy' => 600,
        'applied_amount' => 0,
        'unapplied_amount' => 600,
        'status' => 'APPROVED',
        'created_by' => $user->id,
    ]);

    app(PaymentService::class)->post($payment, $user->id);

    $bankEntry = BankAccountLedgerEntry::query()
        ->where('document_no', $payment->payment_number)
        ->where('bank_account_id', $bankAccount->id)
        ->first();

    expect($bankEntry)->not->toBeNull()
        ->and((float) $bankEntry->amount)->toBe(-600.0)
        ->and((float) $bankEntry->debit_amount)->toBe(0.0)
        ->and((float) $bankEntry->credit_amount)->toBe(600.0)
        ->and((float) $bankAccount->fresh()->current_balance)->toBe(1400.0)
        ->and($payment->fresh()->status)->toBe('POSTED');

    $ledgerEntry = VendorLedgerEntry::query()
        ->where('document_type', 'PAYMENT')
        ->where('document_number', $payment->payment_number)
        ->where('vendor_id', $vendor->id)
        ->first();

    expect($ledgerEntry)->not->toBeNull()
        ->and((float) $ledgerEntry->debit_amount)->toBe(0.0)
        ->and((float) $ledgerEntry->credit_amount)->toBe(600.0)
        ->and((float) $ledgerEntry->amount)->toBe(-600.0)
        ->and((float) $ledgerEntry->remaining_amount)->toBe(600.0)
        ->and($ledgerEntry->open)->toBeTrue()
        ->and((float) $vendor->fresh()->balance)->toBe(-600.0);
});

it('blocks double posting and does not duplicate bank ledger entries', function () {
    $user = User::factory()->create();
    grantPaymentPostingPermission($user);

    $customer = Customer::factory()->create();
    $bankAccount = BankAccount::factory()->receiptOnly()->create();
    $payment = Payment::factory()->customerReceipt()->create([
        'party_id' => $customer->id,
        'party_name' => $customer->name,
        'bank_account_id' => $bankAccount->id,
        'payment_amount' => 300,
        'payment_amount_lcy' => 300,
        'applied_amount' => 0,
        'unapplied_amount' => 300,
        'status' => 'APPROVED',
        'created_by' => $user->id,
    ]);

    $service = app(PaymentService::class);
    $service->post($payment, $user->id);

    expect(fn () => $service->post($payment->fresh(), $user->id))
        ->toThrow(Exception::class, 'Payment is already posted.');

    expect(BankAccountLedgerEntry::query()
        ->where('document_no', $payment->payment_number)
        ->count())->toBe(1);
});

it('rejects posting without a bank account', function () {
    $user = User::factory()->create();
    grantPaymentPostingPermission($user);

    $customer = Customer::factory()->create();
    $payment = Payment::factory()->customerReceipt()->create([
        'party_id' => $customer->id,
        'party_name' => $customer->name,
        'bank_account_id' => null,
        'payment_amount' => 300,
        'payment_amount_lcy' => 300,
        'applied_amount' => 0,
        'unapplied_amount' => 300,
        'status' => 'APPROVED',
        'created_by' => $user->id,
    ]);

    expect(fn () => app(PaymentService::class)->post($payment, $user->id))
        ->toThrow(Exception::class, 'A bank account is required before posting this payment.');

    expect($payment->fresh()->status)->toBe('APPROVED')
        ->and(BankAccountLedgerEntry::query()->where('document_no', $payment->payment_number)->exists())->toBeFalse();
});

it('rejects zero and negative payment amounts', function (float $amount) {
    $user = User::factory()->create();
    grantPaymentPostingPermission($user);

    $customer = Customer::factory()->create();
    $bankAccount = BankAccount::factory()->receiptOnly()->create();
    $payment = Payment::factory()->customerReceipt()->create([
        'party_id' => $customer->id,
        'party_name' => $customer->name,
        'bank_account_id' => $bankAccount->id,
        'payment_amount' => $amount,
        'payment_amount_lcy' => $amount,
        'applied_amount' => 0,
        'unapplied_amount' => $amount,
        'status' => 'APPROVED',
        'created_by' => $user->id,
    ]);

    expect(fn () => app(PaymentService::class)->post($payment, $user->id))
        ->toThrow(Exception::class, 'Payment amount must be greater than zero.');

    expect($payment->fresh()->status)->toBe('APPROVED')
        ->and(BankAccountLedgerEntry::query()->where('document_no', $payment->payment_number)->exists())->toBeFalse();
})->with([
    'zero amount' => 0.0,
    'negative amount' => -100.0,
]);

it('blocks users without payment posting permission', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create();
    $bankAccount = BankAccount::factory()->receiptOnly()->create();
    $payment = Payment::factory()->customerReceipt()->create([
        'party_id' => $customer->id,
        'party_name' => $customer->name,
        'bank_account_id' => $bankAccount->id,
        'payment_amount' => 300,
        'payment_amount_lcy' => 300,
        'applied_amount' => 0,
        'unapplied_amount' => 300,
        'status' => 'APPROVED',
        'created_by' => $user->id,
    ]);

    expect(fn () => app(PaymentService::class)->post($payment, $user->id))
        ->toThrow(AuthorizationException::class);

    expect($payment->fresh()->status)->toBe('APPROVED')
        ->and(BankAccountLedgerEntry::query()->where('document_no', $payment->payment_number)->exists())->toBeFalse();
});

it('requires the bank ledger number series and rolls back payment posting when it is missing', function () {
    $user = User::factory()->create();
    grantPaymentPostingPermission($user);

    $customer = Customer::factory()->create();
    $bankAccount = BankAccount::factory()->receiptOnly()->create([
        'current_balance' => 700,
        'available_balance' => 700,
    ]);

    $payment = Payment::factory()->customerReceipt()->create([
        'party_id' => $customer->id,
        'party_name' => $customer->name,
        'bank_account_id' => $bankAccount->id,
        'payment_amount' => 250,
        'payment_amount_lcy' => 250,
        'applied_amount' => 0,
        'unapplied_amount' => 250,
        'status' => 'APPROVED',
        'created_by' => $user->id,
    ]);

    NumberSeries::query()->where('code', 'BANK-LEDGER')->delete();

    expect(fn () => app(PaymentService::class)->post($payment, $user->id))
        ->toThrow(RuntimeException::class, 'Missing or invalid BANK-LEDGER number series configuration.');

    expect($payment->fresh()->status)->toBe('APPROVED')
        ->and((float) $bankAccount->fresh()->current_balance)->toBe(700.0)
        ->and(BankAccountLedgerEntry::query()->where('document_no', $payment->payment_number)->exists())->toBeFalse()
        ->and(CustomerLedgerEntry::query()->where('document_number', $payment->payment_number)->exists())->toBeFalse()
        ->and(GlEntry::query()->where('document_number', $payment->payment_number)->exists())->toBeFalse();
});

it('does not auto post GL from the bank ledger service', function () {
    $user = User::factory()->create();
    $bankAccount = BankAccount::factory()->receiptOnly()->create([
        'current_balance' => 100,
        'available_balance' => 100,
    ]);

    $entry = app(BankAccountLedgerService::class)->postDeposit($bankAccount, [
        'amount' => 125,
        'posting_date' => now(),
        'document_no' => 'BNK-DIRECT-001',
        'description' => 'Direct bank deposit',
        'user_id' => $user->id,
        'post_gl' => true,
    ]);

    expect($entry->gl_entry_id)->toBeNull()
        ->and((float) $bankAccount->fresh()->current_balance)->toBe(225.0)
        ->and(GlEntry::query()->where('document_number', 'BNK-DIRECT-001')->exists())->toBeFalse();
});

it('blocks users without payment apply permission', function () {
    $user = User::factory()->create();
    $customer = Customer::factory()->create();
    $payment = postedCustomerPayment($customer, $user, 100);
    $invoice = postedSalesInvoice($customer, $user, 100);

    expect(fn () => app(PaymentService::class)->applyToDocument($payment, [
        'document_type' => 'SALES_INVOICE',
        'document_id' => $invoice->id,
        'amount' => 100,
    ], $user->id))->toThrow(AuthorizationException::class);

    expect(PaymentApplication::query()->count())->toBe(0)
        ->and((float) $invoice->fresh()->remaining_amount)->toBe(100.0)
        ->and((float) $payment->fresh()->unapplied_amount)->toBe(100.0);
});

it('applies payment for authorized users and dispatches an audit event', function () {
    Event::fake([PaymentApplied::class]);

    $user = User::factory()->create();
    grantPaymentApplyPermission($user);

    $customer = Customer::factory()->create();
    $payment = postedCustomerPayment($customer, $user, 100);
    $invoice = postedSalesInvoice($customer, $user, 100);

    $application = app(PaymentService::class)->applyToDocument($payment, [
        'document_type' => 'SALES_INVOICE',
        'document_id' => $invoice->id,
        'amount' => 100,
    ], $user->id);

    expect((float) $application->amount_applied)->toBe(100.0)
        ->and((float) $invoice->fresh()->remaining_amount)->toBe(0.0)
        ->and((float) $payment->fresh()->unapplied_amount)->toBe(0.0);

    Event::assertDispatched(PaymentApplied::class, fn (PaymentApplied $event): bool => $event->application->is($application));
});

it('partially applies a customer payment and keeps customer ledger balances in sync', function () {
    $user = User::factory()->create();
    grantPaymentApplyPermission($user);

    $customer = Customer::factory()->create();
    $payment = postedCustomerPayment($customer, $user, 60);
    $paymentEntry = customerPaymentLedgerEntry($payment, $customer, 60);
    $invoice = postedSalesInvoice($customer, $user, 100);
    $invoiceEntry = CustomerLedgerEntry::createFromInvoice($invoice);

    app(PaymentService::class)->applyToDocument($payment, [
        'document_type' => 'SALES_INVOICE',
        'document_id' => $invoice->id,
        'amount' => 60,
    ], $user->id);

    expect((float) $invoice->fresh()->remaining_amount)->toBe(40.0)
        ->and($invoice->fresh()->paid_in_full)->toBeFalse()
        ->and((float) $invoiceEntry->fresh()->remaining_amount)->toBe(40.0)
        ->and($invoiceEntry->fresh()->open)->toBeTrue()
        ->and((float) $payment->fresh()->unapplied_amount)->toBe(0.0)
        ->and((float) $paymentEntry->fresh()->remaining_amount)->toBe(0.0)
        ->and($paymentEntry->fresh()->open)->toBeFalse()
        ->and((float) $customer->fresh()->balance)->toBe(40.0);
});

it('fully applies a customer payment and closes the sales invoice ledger entry', function () {
    $user = User::factory()->create();
    grantPaymentApplyPermission($user);

    $customer = Customer::factory()->create();
    $payment = postedCustomerPayment($customer, $user, 100);
    customerPaymentLedgerEntry($payment, $customer, 100);
    $invoice = postedSalesInvoice($customer, $user, 100);
    $invoiceEntry = CustomerLedgerEntry::createFromInvoice($invoice);

    app(PaymentService::class)->applyToDocument($payment, [
        'document_type' => 'SALES_INVOICE',
        'document_id' => $invoice->id,
        'amount' => 100,
    ], $user->id);

    expect((float) $invoice->fresh()->remaining_amount)->toBe(0.0)
        ->and($invoice->fresh()->paid_in_full)->toBeTrue()
        ->and((float) $invoiceEntry->fresh()->remaining_amount)->toBe(0.0)
        ->and($invoiceEntry->fresh()->open)->toBeFalse()
        ->and((float) $customer->fresh()->balance)->toBe(0.0);
});

it('blocks customer payment over-allocation to a posted invoice', function () {
    $user = User::factory()->create();
    grantPaymentApplyPermission($user);

    $customer = Customer::factory()->create();
    $payment = postedCustomerPayment($customer, $user, 150);
    customerPaymentLedgerEntry($payment, $customer, 150);
    $invoice = postedSalesInvoice($customer, $user, 100);
    $invoiceEntry = CustomerLedgerEntry::createFromInvoice($invoice);

    expect(fn () => app(PaymentService::class)->applyToDocument($payment, [
        'document_type' => 'SALES_INVOICE',
        'document_id' => $invoice->id,
        'amount' => 150,
    ], $user->id))->toThrow(Exception::class, 'Cannot apply more than the document remaining amount.');

    expect(PaymentApplication::query()->count())->toBe(0)
        ->and((float) $invoice->fresh()->remaining_amount)->toBe(100.0)
        ->and((float) $invoiceEntry->fresh()->remaining_amount)->toBe(100.0)
        ->and((float) $payment->fresh()->unapplied_amount)->toBe(150.0);
});

it('applies a vendor payment and keeps vendor ledger balances in sync', function () {
    $user = User::factory()->create();
    grantPaymentApplyPermission($user);

    $vendor = Vendor::factory()->create();
    $payment = postedVendorPayment($vendor, $user, 75);
    $paymentEntry = vendorPaymentLedgerEntry($payment, $vendor, 75);
    $invoice = postedPurchaseInvoice($vendor, $user, 100);
    $invoiceEntry = VendorLedgerEntry::createFromInvoice($invoice);

    app(PaymentService::class)->applyToDocument($payment, [
        'document_type' => 'PURCHASE_INVOICE',
        'document_id' => $invoice->id,
        'amount' => 75,
    ], $user->id);

    expect((float) $invoice->fresh()->remaining_amount)->toBe(25.0)
        ->and($invoice->fresh()->paid_in_full)->toBeFalse()
        ->and((float) $invoiceEntry->fresh()->remaining_amount)->toBe(25.0)
        ->and($invoiceEntry->fresh()->open)->toBeTrue()
        ->and((float) $payment->fresh()->unapplied_amount)->toBe(0.0)
        ->and((float) $paymentEntry->fresh()->remaining_amount)->toBe(0.0)
        ->and($paymentEntry->fresh()->open)->toBeFalse()
        ->and((float) $vendor->fresh()->balance)->toBe(25.0);
});

it('fully applies a vendor payment and closes the purchase invoice ledger entry', function () {
    $user = User::factory()->create();
    grantPaymentApplyPermission($user);

    $vendor = Vendor::factory()->create();
    $payment = postedVendorPayment($vendor, $user, 100);
    vendorPaymentLedgerEntry($payment, $vendor, 100);
    $invoice = postedPurchaseInvoice($vendor, $user, 100);
    $invoiceEntry = VendorLedgerEntry::createFromInvoice($invoice);

    app(PaymentService::class)->applyToDocument($payment, [
        'document_type' => 'PURCHASE_INVOICE',
        'document_id' => $invoice->id,
        'amount' => 100,
    ], $user->id);

    expect((float) $invoice->fresh()->remaining_amount)->toBe(0.0)
        ->and($invoice->fresh()->paid_in_full)->toBeTrue()
        ->and((float) $invoiceEntry->fresh()->remaining_amount)->toBe(0.0)
        ->and($invoiceEntry->fresh()->open)->toBeFalse()
        ->and((float) $vendor->fresh()->balance)->toBe(0.0);
});

it('unapplies payment without changing bank ledger or bank balance and blocks duplicate unapply', function () {
    $user = User::factory()->create();
    grantPaymentApplyPermission($user);
    grantPaymentUnapplyPermission($user);

    $customer = Customer::factory()->create();
    $bankAccount = BankAccount::factory()->receiptOnly()->create([
        'current_balance' => 500,
        'available_balance' => 500,
    ]);
    $payment = postedCustomerPayment($customer, $user, 100);
    $payment->forceFill(['bank_account_id' => $bankAccount->id])->save();
    $invoice = postedSalesInvoice($customer, $user, 100);

    $application = app(PaymentService::class)->applyToDocument($payment, [
        'document_type' => 'SALES_INVOICE',
        'document_id' => $invoice->id,
        'amount' => 100,
    ], $user->id);

    Event::fake([PaymentUnapplied::class]);

    $bankLedgerCount = BankAccountLedgerEntry::query()->count();
    $bankBalance = (float) $bankAccount->fresh()->current_balance;

    app(PaymentService::class)->unapply($application, $user->id);

    expect($application->fresh()->reversed)->toBeTrue()
        ->and((float) $invoice->fresh()->remaining_amount)->toBe(100.0)
        ->and((float) $payment->fresh()->unapplied_amount)->toBe(100.0)
        ->and(BankAccountLedgerEntry::query()->count())->toBe($bankLedgerCount)
        ->and((float) $bankAccount->fresh()->current_balance)->toBe($bankBalance);

    Event::assertDispatched(PaymentUnapplied::class, fn (PaymentUnapplied $event): bool => $event->application->is($application->fresh()));

    expect(fn () => app(PaymentService::class)->unapply($application->fresh(), $user->id))
        ->toThrow(Exception::class, 'Payment application is already reversed.');
});

it('keeps payment application transactional when validation fails', function () {
    Event::fake([PaymentApplied::class]);

    $user = User::factory()->create();
    grantPaymentApplyPermission($user);

    $customer = Customer::factory()->create();
    $payment = postedCustomerPayment($customer, $user, 50);
    $invoice = postedSalesInvoice($customer, $user, 100);

    expect(fn () => app(PaymentService::class)->applyToDocument($payment, [
        'document_type' => 'SALES_INVOICE',
        'document_id' => $invoice->id,
        'amount' => 75,
    ], $user->id))->toThrow(Exception::class, 'Payment does not have enough unapplied amount.');

    expect(PaymentApplication::query()->count())->toBe(0)
        ->and((float) $invoice->fresh()->remaining_amount)->toBe(100.0)
        ->and((float) $payment->fresh()->unapplied_amount)->toBe(50.0);

    Event::assertNotDispatched(PaymentApplied::class);
});

it('routes legacy cash receipt line posting through payment service and bank ledger service', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    grantPaymentPostingPermission($user);

    $customer = Customer::factory()->create();
    $bankAccount = BankAccount::factory()->receiptOnly()->create();
    $journalLine = journalLineForPayment($user, 'CashReceipt', 'CRJ-001', 175);

    $line = CashReceiptLine::query()->create([
        'journal_line_id' => $journalLine->id,
        'customer_id' => $customer->id,
        'customer_no' => $customer->customer_number,
        'amount_received' => 175,
        'amount_received_lcy' => 175,
        'remaining_amount' => 175,
        'bank_account_id' => $bankAccount->id,
        'bank_account_no' => $bankAccount->account_number,
        'payment_method_code' => 'Bank Transfer',
    ]);

    $line->applyPayment();

    $payment = Payment::query()->where('payment_number', 'CRJ-001')->first();

    expect($payment)->not->toBeNull()
        ->and($payment->status)->toBe('POSTED')
        ->and(BankAccountLedgerEntry::query()->where('source_type', Payment::class)->where('source_id', $payment->id)->exists())->toBeTrue()
        ->and($line->fresh()->exported_to_payment_jnl)->toBeTrue()
        ->and($journalLine->fresh()->status)->toBe('Posted');
});

it('routes legacy payment journal line posting through payment service and bank ledger service', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    grantPaymentPostingPermission($user);

    $payablesAccount = ChartOfAccount::factory()->create();
    $vendorPostingGroup = VendorPostingGroup::factory()->create([
        'payables_account_id' => $payablesAccount->id,
    ]);
    $vendor = Vendor::factory()->create([
        'vendor_posting_group_id' => $vendorPostingGroup->id,
    ]);
    $bankAccount = BankAccount::factory()->paymentOnly()->create([
        'current_balance' => 500,
        'available_balance' => 500,
    ]);
    $journalLine = journalLineForPayment($user, 'Payment', 'PYJ-001', 150);

    $line = PaymentJournalLine::query()->create([
        'journal_line_id' => $journalLine->id,
        'vendor_id' => $vendor->id,
        'vendor_no' => $vendor->vendor_code,
        'amount_paid' => 150,
        'amount_paid_lcy' => 150,
        'remaining_amount' => 150,
        'bank_account_id' => $bankAccount->id,
        'bank_account_no' => $bankAccount->account_number,
        'payment_method_code' => 'Bank Transfer',
    ]);

    $line->applyPayment();

    $payment = Payment::query()->where('payment_number', 'PYJ-001')->first();

    expect($payment)->not->toBeNull()
        ->and($payment->status)->toBe('POSTED')
        ->and(BankAccountLedgerEntry::query()->where('source_type', Payment::class)->where('source_id', $payment->id)->exists())->toBeTrue()
        ->and((float) $bankAccount->fresh()->current_balance)->toBe(350.0)
        ->and($line->fresh()->payment_processed)->toBeTrue()
        ->and($journalLine->fresh()->status)->toBe('Posted');
});

function grantPaymentPostingPermission(User $user): void
{
    Permission::query()->firstOrCreate([
        'name' => 'finance.payment.post',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo('finance.payment.post');
}

function grantPaymentApplyPermission(User $user): void
{
    Permission::query()->firstOrCreate([
        'name' => 'finance.payment.apply',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo('finance.payment.apply');
}

function grantPaymentUnapplyPermission(User $user): void
{
    Permission::query()->firstOrCreate([
        'name' => 'finance.payment.unapply',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo('finance.payment.unapply');
}

function ensureBankLedgerNumberSeries(): void
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
        ]
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
        ]
    );
}

function postedCustomerPayment(Customer $customer, User $user, float $amount): Payment
{
    return Payment::factory()->customerReceipt()->create([
        'party_id' => $customer->id,
        'party_name' => $customer->name,
        'payment_amount' => $amount,
        'payment_amount_lcy' => $amount,
        'applied_amount' => 0,
        'unapplied_amount' => $amount,
        'status' => 'POSTED',
        'payment_direction' => 'RECEIPT',
        'created_by' => $user->id,
        'posted_by' => $user->id,
        'posted_at' => now(),
    ]);
}

function postedSalesInvoice(Customer $customer, User $user, float $amount): PostedSalesInvoice
{
    return PostedSalesInvoice::query()->create([
        'document_number' => 'PSI-'.fake()->unique()->numberBetween(1000, 9999),
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'general_business_posting_group_id' => $customer->general_business_posting_group_id,
        'customer_posting_group_id' => $customer->customer_posting_group_id,
        'posting_date' => now(),
        'document_date' => now(),
        'due_date' => now()->addDays(30),
        'subtotal' => $amount,
        'total_amount' => $amount,
        'grand_total' => $amount,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'amount_paid' => 0,
        'remaining_amount' => $amount,
        'paid_in_full' => false,
        'posted_by' => $user->id,
        'posted_at' => now(),
    ]);
}

function postedVendorPayment(Vendor $vendor, User $user, float $amount): Payment
{
    return Payment::factory()->create([
        'party_type' => 'VENDOR',
        'party_id' => $vendor->id,
        'party_name' => $vendor->vendor_name,
        'payment_amount' => $amount,
        'payment_amount_lcy' => $amount,
        'applied_amount' => 0,
        'unapplied_amount' => $amount,
        'status' => 'POSTED',
        'payment_direction' => 'DISBURSEMENT',
        'created_by' => $user->id,
        'posted_by' => $user->id,
        'posted_at' => now(),
    ]);
}

function postedPurchaseInvoice(Vendor $vendor, User $user, float $amount): PostedPurchaseInvoice
{
    return PostedPurchaseInvoice::query()->create([
        'document_number' => 'PPI-'.fake()->unique()->numberBetween(1000, 9999),
        'vendor_id' => $vendor->id,
        'vendor_name' => $vendor->vendor_name,
        'general_business_posting_group_id' => $vendor->general_business_posting_group_id,
        'vendor_posting_group_id' => $vendor->vendor_posting_group_id,
        'posting_date' => now(),
        'document_date' => now(),
        'due_date' => now()->addDays(30),
        'total_amount' => $amount,
        'grand_total' => $amount,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'amount_paid' => 0,
        'remaining_amount' => $amount,
        'paid_in_full' => false,
        'posted_by' => $user->id,
        'posted_at' => now(),
    ]);
}

function customerPaymentLedgerEntry(Payment $payment, Customer $customer, float $amount): CustomerLedgerEntry
{
    return CustomerLedgerEntry::query()->create([
        'entry_number' => ((int) CustomerLedgerEntry::query()->where('customer_id', $customer->id)->max('entry_number')) + 1,
        'customer_id' => $customer->id,
        'document_type' => 'PAYMENT',
        'document_number' => $payment->payment_number,
        'description' => "Payment {$payment->payment_number}",
        'posting_date' => $payment->posting_date,
        'document_date' => $payment->payment_date,
        'debit_amount' => 0,
        'credit_amount' => $amount,
        'amount' => -$amount,
        'running_balance' => (float) CustomerLedgerEntry::query()->where('customer_id', $customer->id)->sum('amount') - $amount,
        'remaining_amount' => $amount,
        'open' => true,
        'fully_applied' => false,
        'currency_id' => $payment->currency_id,
        'currency_code' => $payment->currency_code,
        'currency_factor' => $payment->currency_factor,
        'original_credit_amount' => $amount,
        'general_business_posting_group_id' => $customer->general_business_posting_group_id,
        'customer_posting_group_id' => $customer->customer_posting_group_id,
        'source_id' => $payment->id,
        'source_type' => Payment::class,
        'created_by' => $payment->created_by,
    ]);
}

function vendorPaymentLedgerEntry(Payment $payment, Vendor $vendor, float $amount): VendorLedgerEntry
{
    return VendorLedgerEntry::query()->create([
        'entry_number' => ((int) VendorLedgerEntry::query()->where('vendor_id', $vendor->id)->max('entry_number')) + 1,
        'vendor_id' => $vendor->id,
        'document_type' => 'PAYMENT',
        'document_number' => $payment->payment_number,
        'description' => "Payment {$payment->payment_number}",
        'posting_date' => $payment->posting_date,
        'document_date' => $payment->payment_date,
        'debit_amount' => 0,
        'credit_amount' => $amount,
        'amount' => -$amount,
        'running_balance' => (float) VendorLedgerEntry::query()->where('vendor_id', $vendor->id)->sum('amount') - $amount,
        'remaining_amount' => $amount,
        'open' => true,
        'fully_applied' => false,
        'currency_id' => $payment->currency_id,
        'currency_code' => $payment->currency_code,
        'currency_factor' => $payment->currency_factor,
        'original_credit_amount' => $amount,
        'general_business_posting_group_id' => $vendor->general_business_posting_group_id,
        'vendor_posting_group_id' => $vendor->vendor_posting_group_id,
        'source_id' => $payment->id,
        'source_type' => Payment::class,
        'created_by' => $payment->created_by,
    ]);
}

function journalLineForPayment(User $user, string $templateType, string $documentNumber, float $amount): JournalLine
{
    $template = JournalTemplate::query()->create([
        'name' => $templateType.'-'.$documentNumber,
        'description' => $templateType.' journal',
        'type' => $templateType,
    ]);

    $batch = JournalBatch::query()->create([
        'journal_template_id' => $template->id,
        'name' => 'DEFAULT',
        'description' => $templateType.' batch',
        'user_id' => $user->id,
    ]);

    return JournalLine::query()->create([
        'journal_batch_id' => $batch->id,
        'line_no' => 10000,
        'posting_date' => now(),
        'document_date' => now(),
        'document_type' => 'Payment',
        'document_no' => $documentNumber,
        'account_type' => $templateType === 'CashReceipt' ? 'Customer' : 'Vendor',
        'account_no' => $documentNumber,
        'description' => $templateType.' line',
        'amount' => $amount,
        'debit_amount' => $templateType === 'CashReceipt' ? $amount : 0,
        'credit_amount' => $templateType === 'CashReceipt' ? 0 : $amount,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'amount_lcy' => $amount,
        'status' => 'Open',
    ]);
}
