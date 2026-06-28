<?php

use App\Models\BankAccount;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\NumberSeries;
use App\Models\NumberSeriesLine;
use App\Models\Payment;
use App\Models\User;
use App\Services\Customer\CustomerSubledgerSummaryService;
use App\Services\Finance\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\CreatesFinancialDocumentFixtures;

uses(RefreshDatabase::class, CreatesFinancialDocumentFixtures::class);

it('nets unapplied customer credits against open balance and aging', function () {
    $customer = Customer::factory()->create();
    $user = User::factory()->create();

    CustomerLedgerEntry::query()->create([
        'entry_number' => 1,
        'customer_id' => $customer->id,
        'document_type' => 'SALES_INVOICE',
        'document_number' => 'SI-OPEN-001',
        'description' => 'Open invoice',
        'posting_date' => now()->subDays(40),
        'document_date' => now()->subDays(40),
        'due_date' => now()->subDays(10),
        'debit_amount' => 1000,
        'credit_amount' => 0,
        'amount' => 1000,
        'running_balance' => 1000,
        'remaining_amount' => 1000,
        'open' => true,
        'reversed' => false,
        'created_by' => $user->id,
    ]);

    CustomerLedgerEntry::query()->create([
        'entry_number' => 2,
        'customer_id' => $customer->id,
        'document_type' => 'PAYMENT',
        'document_number' => 'REC-OPEN-001',
        'description' => 'Open payment',
        'posting_date' => now()->subDays(5),
        'document_date' => now()->subDays(5),
        'debit_amount' => 0,
        'credit_amount' => 250,
        'amount' => -250,
        'running_balance' => 750,
        'remaining_amount' => 250,
        'open' => true,
        'fully_applied' => false,
        'reversed' => false,
        'created_by' => $user->id,
    ]);

    $customer->refresh();
    $aging = $customer->aging;

    expect((float) $customer->open_balance)->toBe(750.0)
        ->and((float) $customer->overdue_balance)->toBe(1000.0)
        ->and((float) $aging['1-30'])->toBe(1000.0)
        ->and((float) $aging['CURRENT'])->toBe(-250.0)
        ->and((float) $aging['TOTAL'])->toBe(750.0);
});

it('reports customer subledger open remaining as a net receivable position', function () {
    $customer = Customer::factory()->create();
    $user = User::factory()->create();

    CustomerLedgerEntry::query()->create([
        'entry_number' => 1,
        'customer_id' => $customer->id,
        'document_type' => 'SALES_INVOICE',
        'document_number' => 'SI-SUM-001',
        'description' => 'Summary invoice',
        'posting_date' => now()->subDays(20),
        'document_date' => now()->subDays(20),
        'due_date' => now()->subDays(2),
        'debit_amount' => 1200,
        'credit_amount' => 0,
        'amount' => 1200,
        'running_balance' => 1200,
        'remaining_amount' => 1200,
        'open' => true,
        'reversed' => false,
        'created_by' => $user->id,
    ]);

    CustomerLedgerEntry::query()->create([
        'entry_number' => 2,
        'customer_id' => $customer->id,
        'document_type' => 'SALES_CREDIT_MEMO',
        'document_number' => 'SCM-SUM-001',
        'description' => 'Summary credit memo',
        'posting_date' => now()->subDay(),
        'document_date' => now()->subDay(),
        'debit_amount' => 0,
        'credit_amount' => 300,
        'amount' => -300,
        'running_balance' => 900,
        'remaining_amount' => 300,
        'open' => true,
        'fully_applied' => false,
        'reversed' => false,
        'created_by' => $user->id,
    ]);

    $report = app(CustomerSubledgerSummaryService::class)->generate([
        'customer_id' => $customer->id,
    ]);

    expect((float) $report['summary']['open_remaining'])->toBe(900.0)
        ->and((float) $report['aging']['1_30'])->toBe(1200.0)
        ->and((float) $report['aging']['current'])->toBe(-300.0);
});

it('posts customer receipts as open credit ledger entries until they are applied', function () {
    $this->ensureOpenAccountingPeriod(now());
    ensureCustomerSubledgerBankLedgerNumberSeries();

    $customer = Customer::factory()->create();
    $user = User::factory()->create();
    grantCustomerSubledgerPaymentPostingPermission($user);

    $bankAccount = BankAccount::factory()->receiptOnly()->create();

    $payment = Payment::factory()->customerReceipt()->create([
        'party_id' => $customer->id,
        'party_name' => $customer->name,
        'bank_account_id' => $bankAccount->id,
        'status' => 'APPROVED',
        'payment_amount' => 450,
        'applied_amount' => 0,
        'unapplied_amount' => 450,
    ]);

    app(PaymentService::class)->post($payment, $user->id);

    $ledgerEntry = CustomerLedgerEntry::query()
        ->where('document_type', 'PAYMENT')
        ->where('document_number', $payment->payment_number)
        ->first();

    expect($ledgerEntry)->not->toBeNull()
        ->and((float) $ledgerEntry->remaining_amount)->toBe(450.0)
        ->and($ledgerEntry->open)->toBeTrue()
        ->and((float) $customer->fresh()->open_balance)->toBe(-450.0);
});

function grantCustomerSubledgerPaymentPostingPermission(User $user): void
{
    Permission::query()->firstOrCreate([
        'name' => 'finance.payment.post',
        'guard_name' => 'web',
    ]);

    $user->givePermissionTo('finance.payment.post');
}

function ensureCustomerSubledgerBankLedgerNumberSeries(): void
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
