<?php

declare(strict_types=1);

use App\Models\ChartOfAccount;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\GlEntry;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\PostedSalesInvoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

function subledgerInvoiceFixture(float $total = 100): array
{
    $customer = Customer::factory()->create();
    $user = User::factory()->create();
    $invoice = PostedSalesInvoice::query()->create([
        'document_number' => 'SINV-RECON-'.$customer->id,
        'customer_id' => $customer->id,
        'customer_name' => $customer->name,
        'general_business_posting_group_id' => $customer->general_business_posting_group_id,
        'customer_posting_group_id' => $customer->customer_posting_group_id,
        'posting_date' => '2026-08-01',
        'document_date' => '2026-08-01',
        'due_date' => '2026-08-31',
        'subtotal' => $total,
        'total_amount' => $total,
        'grand_total' => $total,
        'currency_code' => 'NGN',
        'currency_factor' => 1,
        'amount_paid' => 50,
        'remaining_amount' => $total - 50,
        'paid_in_full' => false,
        'posted_by' => $user->id,
        'posted_at' => now(),
    ]);
    CustomerLedgerEntry::query()->create([
        'entry_number' => $customer->id,
        'customer_id' => $customer->id,
        'document_type' => 'SALES_INVOICE',
        'document_number' => $invoice->document_number,
        'description' => 'Reconciliation fixture invoice',
        'posting_date' => '2026-08-01',
        'document_date' => '2026-08-01',
        'due_date' => '2026-08-31',
        'debit_amount' => $total,
        'credit_amount' => 0,
        'amount' => $total,
        'running_balance' => 50,
        'remaining_amount' => 50,
        'open' => true,
        'fully_applied' => false,
        'currency_code' => 'NGN',
        'original_debit_amount' => $total,
        'original_credit_amount' => 0,
        'currency_factor' => 1,
        'general_business_posting_group_id' => $customer->general_business_posting_group_id,
        'customer_posting_group_id' => $customer->customer_posting_group_id,
        'source_type' => PostedSalesInvoice::class,
        'source_id' => $invoice->id,
        'created_by' => $user->id,
    ]);
    $payment = Payment::factory()->customerReceipt()->create([
        'party_id' => $customer->id,
        'party_name' => $customer->name,
        'payment_amount' => 100,
        'applied_amount' => 50,
        'unapplied_amount' => 50,
        'status' => 'POSTED',
        'created_by' => $user->id,
    ]);

    return compact('customer', 'invoice', 'payment', 'user');
}

function subledgerApplicationFixture(array $overrides = []): PaymentApplication
{
    $fixture = subledgerInvoiceFixture();

    return PaymentApplication::query()->create(array_merge([
        'payment_id' => $fixture['payment']->id,
        'document_type' => 'SALES_INVOICE',
        'document_id' => $fixture['invoice']->id,
        'document_number' => $fixture['invoice']->document_number,
        'document_original_amount' => 100,
        'document_remaining_before' => 100,
        'amount_applied' => 50,
        'discount_applied' => 0,
        'write_off_amount' => 0,
        'document_remaining_after' => 50,
        'full_payment' => false,
        'applied_by' => $fixture['user']->id,
        'applied_at' => now(),
        'reversed' => false,
    ], $overrides));
}

function subledgerCommandOutput(): string
{
    Artisan::call('biwms:subledger-reconcile', ['--details' => true]);

    return Artisan::output();
}

it('stays clean for a valid partial application and payment invariant', function (): void {
    subledgerApplicationFixture();

    expect(subledgerCommandOutput())->toContain('Findings')
        ->not->toContain('[CRITICAL]');
});

it('detects payment totals and duplicate active canonical applications', function (): void {
    $application = subledgerApplicationFixture();
    PaymentApplication::query()->create($application->only([
        'payment_id', 'document_type', 'document_id', 'document_number',
        'document_original_amount', 'document_remaining_before', 'amount_applied',
        'discount_applied', 'write_off_amount', 'document_remaining_after',
        'full_payment', 'applied_by', 'applied_at', 'reversed',
    ]));
    $application->payment->forceFill([
        'applied_amount' => 75,
        'unapplied_amount' => 25,
    ])->saveQuietly();

    $output = subledgerCommandOutput();

    expect($output)->toContain('duplicate_canonical_application')
        ->and($output)->toContain('application_total_mismatch');
});

it('does not classify a reversed application as an active duplicate', function (): void {
    $application = subledgerApplicationFixture();
    PaymentApplication::query()->create([
        ...$application->only([
            'payment_id', 'document_type', 'document_id', 'document_number',
            'document_original_amount', 'document_remaining_before', 'amount_applied',
            'discount_applied', 'write_off_amount', 'document_remaining_after',
            'full_payment', 'applied_by', 'applied_at',
        ]),
        'reversed' => true,
        'reversed_at' => now(),
        'reversed_by' => $application->applied_by,
    ]);

    expect(subledgerCommandOutput())->not->toContain('duplicate_canonical_application');
});

it('detects orphan and party-mismatched applications', function (): void {
    $application = subledgerApplicationFixture();
    $application->payment->forceFill(['party_id' => Customer::factory()->create()->id])->saveQuietly();
    PaymentApplication::query()->create([
        ...$application->only([
            'payment_id', 'document_type', 'document_number',
            'document_original_amount', 'document_remaining_before',
            'discount_applied', 'write_off_amount', 'document_remaining_after',
            'full_payment', 'applied_by', 'applied_at',
        ]),
        'document_id' => 999999,
        'amount_applied' => 0,
        'reversed' => false,
    ]);

    $output = subledgerCommandOutput();

    expect($output)->toContain('orphan_application')
        ->and($output)->toContain('application_party_mismatch');
});

it('detects realized FX applications without linked GL and orphan linked GL rows', function (): void {
    $application = subledgerApplicationFixture([
        'gain_loss_amount' => 10,
    ]);
    $account = ChartOfAccount::factory()->create();

    $output = subledgerCommandOutput();
    expect($output)->toContain('fx_application_missing_gl');

    GlEntry::query()->create([
        'entry_number' => 1,
        'transaction_number' => 1,
        'chart_of_account_id' => $account->id,
        'debit_amount' => 10,
        'credit_amount' => 0,
        'amount' => 10,
        'source_type' => 'CUSTOMER',
        'document_type' => 'PAYMENT',
        'document_number' => 'PAY-RECON',
        'document_date' => '2026-08-01',
        'posting_date' => '2026-08-01',
        'description' => 'FX',
        'payment_application_id' => $application->id,
    ]);

    expect(subledgerCommandOutput())->not->toContain('fx_application_missing_gl');
});
