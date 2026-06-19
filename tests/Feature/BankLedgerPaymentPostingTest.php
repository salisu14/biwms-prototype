<?php

use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Permission;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorPostingGroup;
use App\Services\Finance\PaymentService;
use Illuminate\Auth\Access\AuthorizationException;

beforeEach(function () {
    $this->refreshPostgresTestingDatabase();
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
        'status' => 'PENDING',
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
        'status' => 'PENDING',
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
        'status' => 'PENDING',
        'created_by' => $user->id,
    ]);

    $service = app(PaymentService::class);
    $service->post($payment, $user->id);

    expect(fn () => $service->post($payment->fresh(), $user->id))
        ->toThrow(Exception::class, 'Payment is not pending');

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
        'status' => 'PENDING',
        'created_by' => $user->id,
    ]);

    expect(fn () => app(PaymentService::class)->post($payment, $user->id))
        ->toThrow(Exception::class, 'A bank account is required before posting this payment.');

    expect($payment->fresh()->status)->toBe('PENDING')
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
        'status' => 'PENDING',
        'created_by' => $user->id,
    ]);

    expect(fn () => app(PaymentService::class)->post($payment, $user->id))
        ->toThrow(Exception::class, 'Payment amount must be greater than zero.');

    expect($payment->fresh()->status)->toBe('PENDING')
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
        'status' => 'PENDING',
        'created_by' => $user->id,
    ]);

    expect(fn () => app(PaymentService::class)->post($payment, $user->id))
        ->toThrow(AuthorizationException::class);

    expect($payment->fresh()->status)->toBe('PENDING')
        ->and(BankAccountLedgerEntry::query()->where('document_no', $payment->payment_number)->exists())->toBeFalse();
});

function grantPaymentPostingPermission(User $user): void
{
    Permission::query()->firstOrCreate([
        'name' => 'finance.payment.post',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo('finance.payment.post');
}
