<?php

use App\Models\ChartOfAccount;
use App\Models\Contact;
use App\Models\Currency;
use App\Models\GeneralBusinessPostingGroup;
use App\Models\GlEntry;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\PostedPurchaseInvoice;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Models\VendorPostingGroup;
use App\Services\Finance\PaymentService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('it calculates and posts realized gain/loss during foreign currency application', function () {
    // 0. Setup User
    $user = User::factory()->create();
    $this->actingAs($user);

    // 1. Setup Accounts
    $apAccount = ChartOfAccount::create(['account_number' => '2100', 'name' => 'Accounts Payable', 'type' => 'LIABILITY']);
    $gainAccount = ChartOfAccount::create(['account_number' => '8100', 'name' => 'Realized Gain', 'type' => 'INCOME']);
    $lossAccount = ChartOfAccount::create(['account_number' => '8200', 'name' => 'Realized Loss', 'type' => 'EXPENSE']);
    $bankGlAccount = ChartOfAccount::create(['account_number' => '1100', 'name' => 'Bank', 'type' => 'ASSET']);

    // 2. Setup Currencies
    $lcy = Currency::query()->updateOrCreate(
        ['code' => 'NGN'],
        [
            'description' => 'Nigerian Naira',
            'symbol' => '₦',
            'decimal_places' => 2,
            'is_active' => true,
            'is_lcy' => true,
            'exchange_rate' => 1,
        ]
    );
    $fcy = Currency::query()->updateOrCreate(
        ['code' => 'USD'],
        [
            'description' => 'US Dollar',
            'symbol' => '$',
            'decimal_places' => 2,
            'is_active' => true,
            'is_lcy' => false,
            'exchange_rate' => 1300,
            'realized_gains_account_id' => $gainAccount->id,
            'realized_losses_account_id' => $lossAccount->id,
        ]
    );

    // 3. Setup Vendor
    $gbpg = GeneralBusinessPostingGroup::create([
        'code' => 'DOMESTIC',
        'description' => 'Domestic Business',
    ]);
    $vpg = VendorPostingGroup::create([
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

    // 4. Create a Posted Purchase Invoice
    // 100 USD @ 1200 = 120,000 NGN
    $invoice = PostedPurchaseInvoice::create([
        'document_number' => 'INV-001',
        'vendor_id' => $vendor->id,
        'vendor_name' => $vendor->vendor_name,
        'posting_date' => now()->subDays(10),
        'document_date' => now()->subDays(10),
        'due_date' => now()->addDays(20),
        'currency_code' => 'USD',
        'currency_factor' => 1200.0,
        'total_amount' => 100.0,
        'grand_total' => 100.0,
        'remaining_amount' => 100.0,
        'paid_in_full' => false,
        'posted_by' => $user->id,
        'posted_at' => now()->subDays(10),
    ]);

    // Create its ledger entry (usually done by PostingService, but we mock it here for simplicity)
    $invoiceEntry = VendorLedgerEntry::create([
        'entry_number' => 1,
        'transaction_number' => 1,
        'vendor_id' => $vendor->id,
        'posting_date' => $invoice->posting_date,
        'document_date' => $invoice->document_date,
        'document_type' => 'PURCHASE_INVOICE',
        'document_number' => $invoice->document_number,
        'description' => "Invoice {$invoice->document_number}",
        'currency_id' => $fcy->id,
        'currency_code' => 'USD',
        'currency_factor' => 1200.0,
        'credit_amount' => 100.0,
        'amount' => -100.0,
        'running_balance' => -100.0,
        'remaining_amount' => -100.0,
        'open' => true,
        'created_by' => $user->id,
        'source_id' => $invoice->id,
        'source_type' => get_class($invoice),
    ]);

    // 5. Create a Payment
    // 100 USD @ 1300 = 130,000 NGN
    // Loss of 100 * (1300 - 1200) = 10,000 NGN
    $payment = new Payment;
    $payment->payment_number = 'PAY-001';
    $payment->payment_date = now();
    $payment->posting_date = now();
    $payment->party_type = 'VENDOR';
    $payment->party_id = $vendor->id;
    $payment->party_name = $vendor->vendor_name;
    $payment->payment_method = 'BANK_TRANSFER';
    $payment->payment_amount = 100.0;
    $payment->payment_amount_lcy = 130000.0;
    $payment->currency_code = 'USD';
    $payment->currency_factor = 1300.0;
    $payment->status = 'POSTED';
    $payment->payment_direction = 'DISBURSEMENT';
    $payment->unapplied_amount = 100.0;
    $payment->created_by = $user->id;
    $payment->currency_id = $fcy->id; // Explicitly setting it since it works
    $payment->save();

    // Create payment ledger entry
    $paymentEntry = VendorLedgerEntry::create([
        'entry_number' => 2,
        'transaction_number' => 2,
        'vendor_id' => $vendor->id,
        'posting_date' => now(),
        'document_date' => now(),
        'document_type' => 'PAYMENT',
        'document_number' => 'PAY-001',
        'description' => 'Test Payment',
        'currency_id' => $fcy->id,
        'currency_code' => 'USD',
        'currency_factor' => 1300.0,
        'debit_amount' => 100.0,
        'amount' => 100.0,
        'running_balance' => 0.0,
        'remaining_amount' => 100.0,
        'open' => true,
        'source_id' => $payment->id,
        'source_type' => 'PAYMENT',
        'created_by' => $user->id,
    ]);

    // 6. Apply Payment
    $service = app(PaymentService::class);
    $service->applyToDocument($payment, [
        'document_type' => 'PURCHASE_INVOICE',
        'document_id' => $invoice->id,
        'amount' => 100.0,
    ], $user->id);

    // 7. Verify
    $application = PaymentApplication::first();
    expect((float) $application->amount_applied)->toBe(100.0);
    expect((float) $application->amount_applied_lcy)->toBe(130000.0);

    // Gain/Loss: (Payment Factor * Amount) - (Document Factor * Amount)
    // (1300 * 100) - (1200 * 100) = 130,000 - 120,000 = 10,000
    expect((float) $application->gain_loss_amount)->toBe(10000.0);

    // Check G/L Entries
    $glEntries = GlEntry::where('document_number', 'PAY-001')
        ->where('description', 'like', '%Realized%')
        ->get();

    expect($glEntries->count())->toBeGreaterThan(0);
    // There should be entries for realized loss
    $lossEntries = $glEntries->where('chart_of_account_id', $lossAccount->id);
    expect($lossEntries->count())->toBeGreaterThan(0);
    expect((float) $lossEntries->sum('debit_amount'))->toEqual(10000.0);
});
