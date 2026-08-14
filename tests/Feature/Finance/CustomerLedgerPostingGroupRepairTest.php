<?php

declare(strict_types=1);

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\CustomerPostingGroup;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('reports missing payment customer ledger posting groups without mutating data', function (): void {
    [$customer, $payment, $entry] = malformedPaymentCustomerLedgerEntry();

    expect(Artisan::call('biwms:customer-ledger-posting-groups-repair', ['--dry-run' => true]))->toBe(0);

    expect(Artisan::output())->toContain('Deterministic candidate rows: 1')
        ->and($entry->fresh()->customer_posting_group_id)->toBeNull()
        ->and($entry->fresh()->general_business_posting_group_id)->toBeNull()
        ->and($payment->fresh()->status)->toBe('POSTED')
        ->and($customer->fresh()->customer_posting_group_id)->not->toBeNull();
});

it('applies deterministic payment customer ledger posting group metadata only when requested', function (): void {
    [$customer, $payment, $entry] = malformedPaymentCustomerLedgerEntry();

    expect(Artisan::call('biwms:customer-ledger-posting-groups-repair', ['--apply' => true]))->toBe(0);

    $entry->refresh();

    expect($entry->customer_posting_group_id)->toBe($customer->customer_posting_group_id)
        ->and($entry->general_business_posting_group_id)->toBe($customer->general_business_posting_group_id)
        ->and((float) $entry->amount)->toBe(-1800.0)
        ->and((float) $entry->remaining_amount)->toBe(1800.0)
        ->and($payment->fresh()->status)->toBe('POSTED');
});

it('finance reconcile reports malformed customer ledger posting group metadata separately', function (): void {
    [, , $entry] = malformedPaymentCustomerLedgerEntry();

    expect(Artisan::call('biwms:finance-reconcile', ['--json' => true]))->toBe(0);

    $report = json_decode(trim(Artisan::output()), true);

    expect($report['customer_ledger_missing_posting_groups'])->toHaveCount(1)
        ->and($report['customer_ledger_missing_posting_groups'][0]['entry_id'])->toBe($entry->id)
        ->and($report['customer_ledger_missing_posting_groups'][0]['classification'])->toBe('customer_ledger_missing_posting_group');
});

/**
 * @return array{0: Customer, 1: Payment, 2: CustomerLedgerEntry}
 */
function malformedPaymentCustomerLedgerEntry(): array
{
    $receivablesAccount = ChartOfAccount::factory()->create();
    $customerPostingGroup = CustomerPostingGroup::factory()->create([
        'receivables_account_id' => $receivablesAccount->id,
    ]);
    $customer = Customer::factory()->create([
        'customer_posting_group_id' => $customerPostingGroup->id,
    ]);
    $user = User::factory()->create();
    $payment = Payment::factory()->customerReceipt()->create([
        'party_id' => $customer->id,
        'party_name' => $customer->name,
        'payment_amount' => 1800,
        'payment_amount_lcy' => 1800,
        'applied_amount' => 0,
        'unapplied_amount' => 1800,
        'status' => 'POSTED',
        'posted_by' => $user->id,
        'posted_at' => now(),
    ]);

    $entry = CustomerLedgerEntry::query()->create([
        'entry_number' => 1,
        'customer_id' => $customer->id,
        'document_type' => 'PAYMENT',
        'document_number' => $payment->payment_number,
        'description' => "Payment {$payment->payment_number}",
        'posting_date' => now(),
        'document_date' => now(),
        'debit_amount' => 0,
        'credit_amount' => 1800,
        'amount' => -1800,
        'running_balance' => -1800,
        'remaining_amount' => 1800,
        'open' => true,
        'fully_applied' => false,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'original_credit_amount' => 1800,
        'source_id' => $payment->id,
        'source_type' => Payment::class,
        'created_by' => $user->id,
    ]);

    return [$customer, $payment, $entry];
}
