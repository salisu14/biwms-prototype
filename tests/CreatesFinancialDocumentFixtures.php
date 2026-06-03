<?php

declare(strict_types=1);

namespace Tests;

use App\Enums\BankAccountLedgerEntryStatus;
use App\Enums\BankAccountLedgerEntryType;
use App\Models\AccountingPeriod;
use App\Models\BankAccount;
use App\Models\BankAccountLedgerEntry;
use App\Models\BankAccountStatementLine;
use App\Models\BankReconciliation;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Payment;
use App\Models\PostedPurchaseCreditMemo;
use App\Models\PostedPurchaseInvoice;
use App\Models\PostedSalesCreditMemo;
use App\Models\PostedSalesInvoice;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use Carbon\Carbon;

trait CreatesFinancialDocumentFixtures
{
    /**
     * @return array{bankAccount: BankAccount, customer: Customer, documentEntry: CustomerLedgerEntry, payment: Payment, paymentEntry: CustomerLedgerEntry, user: User}
     */
    protected function createReceivablePaymentFixture(
        float $documentAmount = 37670.40,
        float $paymentAmount = 30000.00,
    ): array {
        $user = User::factory()->create();
        $bankAccount = BankAccount::factory()->receiptOnly()->create();
        $customer = Customer::factory()->create();

        $documentEntry = CustomerLedgerEntry::query()->create([
            'entry_number' => 1,
            'customer_id' => $customer->id,
            'document_type' => 'SALES_INVOICE',
            'document_number' => 'AR-'.$customer->id.'-001',
            'description' => 'Fixture sales invoice',
            'posting_date' => now()->subDays(10),
            'document_date' => now()->subDays(10),
            'due_date' => now()->addDays(20),
            'debit_amount' => $documentAmount,
            'credit_amount' => 0,
            'amount' => $documentAmount,
            'running_balance' => $documentAmount,
            'remaining_amount' => $documentAmount,
            'open' => true,
            'fully_applied' => false,
            'currency_code' => 'NGN',
            'original_debit_amount' => $documentAmount,
            'original_credit_amount' => 0,
            'currency_factor' => 1,
            'general_business_posting_group_id' => $customer->general_business_posting_group_id,
            'customer_posting_group_id' => $customer->customer_posting_group_id,
            'source_type' => Customer::class,
            'source_id' => $customer->id,
            'created_by' => $user->id,
        ]);

        $payment = Payment::factory()
            ->customerReceipt()
            ->create([
                'party_id' => $customer->id,
                'party_name' => $customer->name,
                'bank_account_id' => $bankAccount->id,
                'payment_amount' => $paymentAmount,
                'applied_amount' => 0,
                'unapplied_amount' => $paymentAmount,
                'payment_amount_lcy' => $paymentAmount,
                'status' => 'POSTED',
                'created_by' => $user->id,
            ]);

        $paymentEntry = CustomerLedgerEntry::query()->create([
            'entry_number' => 2,
            'customer_id' => $customer->id,
            'document_type' => 'PAYMENT',
            'document_number' => $payment->payment_number,
            'description' => 'Fixture customer payment',
            'posting_date' => now()->subDays(3),
            'document_date' => now()->subDays(3),
            'debit_amount' => 0,
            'credit_amount' => $paymentAmount,
            'amount' => -$paymentAmount,
            'running_balance' => $documentAmount - $paymentAmount,
            'remaining_amount' => $paymentAmount,
            'open' => true,
            'fully_applied' => false,
            'currency_code' => 'NGN',
            'original_debit_amount' => 0,
            'original_credit_amount' => $paymentAmount,
            'currency_factor' => 1,
            'general_business_posting_group_id' => $customer->general_business_posting_group_id,
            'customer_posting_group_id' => $customer->customer_posting_group_id,
            'source_type' => Payment::class,
            'source_id' => $payment->id,
            'created_by' => $user->id,
        ]);

        return compact('bankAccount', 'customer', 'documentEntry', 'payment', 'paymentEntry', 'user');
    }

    /**
     * @return array{bankAccount: BankAccount, vendor: Vendor, documentEntry: VendorLedgerEntry, payment: Payment, paymentEntry: VendorLedgerEntry, user: User}
     */
    protected function createPayablePaymentFixture(
        float $documentAmount = 250000.00,
        float $paymentAmount = 200000.00,
    ): array {
        $user = User::factory()->create();
        $bankAccount = BankAccount::factory()->paymentOnly()->create();
        $vendor = Vendor::factory()->create();

        $documentEntry = VendorLedgerEntry::query()->create([
            'entry_number' => 1,
            'vendor_id' => $vendor->id,
            'document_type' => 'PURCHASE_INVOICE',
            'document_number' => 'AP-'.$vendor->id.'-001',
            'description' => 'Fixture purchase invoice',
            'posting_date' => now()->subDays(10),
            'document_date' => now()->subDays(10),
            'due_date' => now()->addDays(20),
            'debit_amount' => $documentAmount,
            'credit_amount' => 0,
            'amount' => $documentAmount,
            'running_balance' => $documentAmount,
            'remaining_amount' => $documentAmount,
            'open' => true,
            'fully_applied' => false,
            'currency_code' => 'NGN',
            'original_debit_amount' => $documentAmount,
            'original_credit_amount' => 0,
            'currency_factor' => 1,
            'general_business_posting_group_id' => $vendor->general_business_posting_group_id,
            'vendor_posting_group_id' => $vendor->vendor_posting_group_id,
            'source_type' => Vendor::class,
            'source_id' => $vendor->id,
            'created_by' => $user->id,
        ]);

        $payment = Payment::factory()->create([
            'party_id' => $vendor->id,
            'party_name' => $vendor->vendor_name,
            'bank_account_id' => $bankAccount->id,
            'payment_amount' => $paymentAmount,
            'applied_amount' => 0,
            'unapplied_amount' => $paymentAmount,
            'payment_amount_lcy' => $paymentAmount,
            'status' => 'POSTED',
            'created_by' => $user->id,
        ]);

        $paymentEntry = VendorLedgerEntry::query()->create([
            'entry_number' => 2,
            'vendor_id' => $vendor->id,
            'document_type' => 'PAYMENT',
            'document_number' => $payment->payment_number,
            'description' => 'Fixture vendor payment',
            'posting_date' => now()->subDays(3),
            'document_date' => now()->subDays(3),
            'debit_amount' => 0,
            'credit_amount' => $paymentAmount,
            'amount' => -$paymentAmount,
            'running_balance' => $documentAmount - $paymentAmount,
            'remaining_amount' => $paymentAmount,
            'open' => true,
            'fully_applied' => false,
            'currency_code' => 'NGN',
            'original_debit_amount' => 0,
            'original_credit_amount' => $paymentAmount,
            'currency_factor' => 1,
            'general_business_posting_group_id' => $vendor->general_business_posting_group_id,
            'vendor_posting_group_id' => $vendor->vendor_posting_group_id,
            'source_type' => Payment::class,
            'source_id' => $payment->id,
            'created_by' => $user->id,
        ]);

        return compact('bankAccount', 'vendor', 'documentEntry', 'payment', 'paymentEntry', 'user');
    }

    /**
     * @return array{customer: Customer, postedInvoice: PostedSalesInvoice, documentEntry: CustomerLedgerEntry, user: User}
     */
    protected function createPostedReceivableFixture(float $documentAmount = 37670.40): array
    {
        $user = User::factory()->create();
        $customer = Customer::factory()->create();
        $postedInvoice = PostedSalesInvoice::query()->create([
            'document_number' => 'PSI-'.$customer->id.'-001',
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_address' => (string) $customer->address,
            'ship_to_name' => $customer->name,
            'ship_to_address' => (string) $customer->address,
            'general_business_posting_group_id' => $customer->general_business_posting_group_id,
            'customer_posting_group_id' => $customer->customer_posting_group_id,
            'vat_bus_posting_group' => $customer->vat_bus_posting_group,
            'location_id' => $customer->location_id,
            'posting_date' => now()->subDays(10),
            'document_date' => now()->subDays(10),
            'due_date' => now()->addDays(20),
            'subtotal' => $documentAmount,
            'line_discount_total' => 0,
            'invoice_discount_amount' => 0,
            'total_amount' => $documentAmount,
            'total_vat' => 0,
            'grand_total' => $documentAmount,
            'currency_code' => 'NGN',
            'currency_factor' => 1,
            'amount_paid' => 0,
            'remaining_amount' => $documentAmount,
            'paid_in_full' => false,
            'posted_by' => $user->id,
            'posted_at' => now()->subDays(10),
            'cancelled' => false,
        ]);

        $documentEntry = CustomerLedgerEntry::query()->create([
            'entry_number' => 1,
            'customer_id' => $customer->id,
            'document_type' => 'SALES_INVOICE',
            'document_number' => $postedInvoice->document_number,
            'description' => "Posted invoice {$postedInvoice->document_number}",
            'posting_date' => $postedInvoice->posting_date,
            'document_date' => $postedInvoice->document_date,
            'due_date' => $postedInvoice->due_date,
            'debit_amount' => $documentAmount,
            'credit_amount' => 0,
            'amount' => $documentAmount,
            'running_balance' => $documentAmount,
            'remaining_amount' => $documentAmount,
            'open' => true,
            'fully_applied' => false,
            'currency_code' => 'NGN',
            'original_debit_amount' => $documentAmount,
            'original_credit_amount' => 0,
            'currency_factor' => 1,
            'general_business_posting_group_id' => $customer->general_business_posting_group_id,
            'customer_posting_group_id' => $customer->customer_posting_group_id,
            'source_type' => PostedSalesInvoice::class,
            'source_id' => $postedInvoice->id,
            'created_by' => $user->id,
        ]);

        return compact('customer', 'postedInvoice', 'documentEntry', 'user');
    }

    /**
     * @return array{vendor: Vendor, postedInvoice: PostedPurchaseInvoice, documentEntry: VendorLedgerEntry, user: User}
     */
    protected function createPostedPayableFixture(float $documentAmount = 250000.00): array
    {
        $user = User::factory()->create();
        $vendor = Vendor::factory()->create();
        $postedInvoice = PostedPurchaseInvoice::query()->create([
            'document_number' => 'PPI-'.$vendor->id.'-001',
            'vendor_id' => $vendor->id,
            'vendor_name' => $vendor->vendor_name,
            'vendor_address' => (string) $vendor->address,
            'general_business_posting_group_id' => $vendor->general_business_posting_group_id,
            'vendor_posting_group_id' => $vendor->vendor_posting_group_id,
            'location_id' => null,
            'posting_date' => now()->subDays(10),
            'document_date' => now()->subDays(10),
            'due_date' => now()->addDays(20),
            'total_amount' => $documentAmount,
            'total_vat' => 0,
            'grand_total' => $documentAmount,
            'currency_code' => 'NGN',
            'currency_factor' => 1,
            'amount_paid' => 0,
            'remaining_amount' => $documentAmount,
            'paid_in_full' => false,
            'posted_by' => $user->id,
            'posted_at' => now()->subDays(10),
            'cancelled' => false,
        ]);

        $documentEntry = VendorLedgerEntry::query()->create([
            'entry_number' => 1,
            'vendor_id' => $vendor->id,
            'document_type' => 'PURCHASE_INVOICE',
            'document_number' => $postedInvoice->document_number,
            'description' => "Posted invoice {$postedInvoice->document_number}",
            'posting_date' => $postedInvoice->posting_date,
            'document_date' => $postedInvoice->document_date,
            'due_date' => $postedInvoice->due_date,
            'debit_amount' => $documentAmount,
            'credit_amount' => 0,
            'amount' => $documentAmount,
            'running_balance' => $documentAmount,
            'remaining_amount' => $documentAmount,
            'open' => true,
            'fully_applied' => false,
            'currency_code' => 'NGN',
            'original_debit_amount' => $documentAmount,
            'original_credit_amount' => 0,
            'currency_factor' => 1,
            'general_business_posting_group_id' => $vendor->general_business_posting_group_id,
            'vendor_posting_group_id' => $vendor->vendor_posting_group_id,
            'source_type' => PostedPurchaseInvoice::class,
            'source_id' => $postedInvoice->id,
            'created_by' => $user->id,
        ]);

        return compact('vendor', 'postedInvoice', 'documentEntry', 'user');
    }

    /**
     * @return array{customer: Customer, postedInvoice: PostedSalesInvoice, documentEntry: CustomerLedgerEntry, payment: Payment, paymentEntry: CustomerLedgerEntry, bankAccount: BankAccount, user: User}
     */
    protected function createPostedReceivableApplicationFixture(
        float $documentAmount = 37670.40,
        float $paymentAmount = 30000.00,
    ): array {
        $this->ensureOpenAccountingPeriod(now());

        $receivable = $this->createPostedReceivableFixture($documentAmount);
        $bankAccount = BankAccount::factory()->receiptOnly()->create();
        $payment = Payment::factory()
            ->customerReceipt()
            ->create([
                'party_id' => $receivable['customer']->id,
                'party_name' => $receivable['customer']->name,
                'bank_account_id' => $bankAccount->id,
                'payment_amount' => $paymentAmount,
                'applied_amount' => 0,
                'unapplied_amount' => $paymentAmount,
                'payment_amount_lcy' => $paymentAmount,
                'status' => 'POSTED',
                'created_by' => $receivable['user']->id,
            ]);

        $paymentEntry = CustomerLedgerEntry::query()->create([
            'entry_number' => 2,
            'customer_id' => $receivable['customer']->id,
            'document_type' => 'PAYMENT',
            'document_number' => $payment->payment_number,
            'description' => 'Fixture payment for posted receivable',
            'posting_date' => now()->subDays(3),
            'document_date' => now()->subDays(3),
            'debit_amount' => 0,
            'credit_amount' => $paymentAmount,
            'amount' => -$paymentAmount,
            'running_balance' => $documentAmount - $paymentAmount,
            'remaining_amount' => $paymentAmount,
            'open' => true,
            'fully_applied' => false,
            'currency_code' => 'NGN',
            'original_debit_amount' => 0,
            'original_credit_amount' => $paymentAmount,
            'currency_factor' => 1,
            'general_business_posting_group_id' => $receivable['customer']->general_business_posting_group_id,
            'customer_posting_group_id' => $receivable['customer']->customer_posting_group_id,
            'source_type' => Payment::class,
            'source_id' => $payment->id,
            'created_by' => $receivable['user']->id,
        ]);

        return $receivable + compact('payment', 'paymentEntry', 'bankAccount');
    }

    /**
     * @return array{vendor: Vendor, postedInvoice: PostedPurchaseInvoice, documentEntry: VendorLedgerEntry, payment: Payment, paymentEntry: VendorLedgerEntry, bankAccount: BankAccount, user: User}
     */
    protected function createPostedPayableApplicationFixture(
        float $documentAmount = 250000.00,
        float $paymentAmount = 200000.00,
    ): array {
        $this->ensureOpenAccountingPeriod(now());

        $payable = $this->createPostedPayableFixture($documentAmount);
        $bankAccount = BankAccount::factory()->paymentOnly()->create();
        $payment = Payment::factory()->create([
            'party_id' => $payable['vendor']->id,
            'party_name' => $payable['vendor']->vendor_name,
            'bank_account_id' => $bankAccount->id,
            'payment_amount' => $paymentAmount,
            'applied_amount' => 0,
            'unapplied_amount' => $paymentAmount,
            'payment_amount_lcy' => $paymentAmount,
            'status' => 'POSTED',
            'created_by' => $payable['user']->id,
        ]);

        $paymentEntry = VendorLedgerEntry::query()->create([
            'entry_number' => 2,
            'vendor_id' => $payable['vendor']->id,
            'document_type' => 'PAYMENT',
            'document_number' => $payment->payment_number,
            'description' => 'Fixture payment for posted payable',
            'posting_date' => now()->subDays(3),
            'document_date' => now()->subDays(3),
            'debit_amount' => 0,
            'credit_amount' => $paymentAmount,
            'amount' => -$paymentAmount,
            'running_balance' => $documentAmount - $paymentAmount,
            'remaining_amount' => $paymentAmount,
            'open' => true,
            'fully_applied' => false,
            'currency_code' => 'NGN',
            'original_debit_amount' => 0,
            'original_credit_amount' => $paymentAmount,
            'currency_factor' => 1,
            'general_business_posting_group_id' => $payable['vendor']->general_business_posting_group_id,
            'vendor_posting_group_id' => $payable['vendor']->vendor_posting_group_id,
            'source_type' => Payment::class,
            'source_id' => $payment->id,
            'created_by' => $payable['user']->id,
        ]);

        return $payable + compact('payment', 'paymentEntry', 'bankAccount');
    }

    /**
     * @return array{customer: Customer, postedInvoice: PostedSalesInvoice, postedCreditMemo: PostedSalesCreditMemo, documentEntry: CustomerLedgerEntry, user: User}
     */
    protected function createPostedSalesCreditMemoFixture(float $creditAmount = 5000.00): array
    {
        $receivable = $this->createPostedReceivableFixture(max($creditAmount * 2, 10000));
        $customer = $receivable['customer'];
        $user = $receivable['user'];
        $postedInvoice = $receivable['postedInvoice'];

        $postedCreditMemo = PostedSalesCreditMemo::query()->create([
            'document_number' => 'PSCM-'.$customer->id.'-001',
            'corrected_invoice_id' => $postedInvoice->id,
            'corrected_invoice_number' => $postedInvoice->document_number,
            'customer_id' => $customer->id,
            'customer_name' => $customer->name,
            'customer_address' => (string) $customer->address,
            'ship_to_name' => $customer->name,
            'ship_to_address' => (string) $customer->address,
            'general_business_posting_group_id' => $customer->general_business_posting_group_id,
            'customer_posting_group_id' => $customer->customer_posting_group_id,
            'vat_bus_posting_group' => $customer->vat_bus_posting_group,
            'location_id' => $customer->location_id,
            'credit_memo_type' => 'ALLOWANCE',
            'posting_date' => now()->subDays(5),
            'document_date' => now()->subDays(5),
            'subtotal' => -$creditAmount,
            'line_discount_total' => 0,
            'total_amount' => -$creditAmount,
            'total_vat' => 0,
            'grand_total' => -$creditAmount,
            'currency_code' => 'NGN',
            'currency_factor' => 1,
            'amount_applied' => 0,
            'remaining_amount' => $creditAmount,
            'fully_applied' => false,
            'refunded' => false,
            'refund_amount' => 0,
            'posted_by' => $user->id,
            'posted_at' => now()->subDays(5),
            'corrected' => false,
        ]);

        $documentEntry = CustomerLedgerEntry::query()->create([
            'entry_number' => 2,
            'customer_id' => $customer->id,
            'document_type' => 'SALES_CREDIT_MEMO',
            'document_number' => $postedCreditMemo->document_number,
            'description' => "Posted credit memo {$postedCreditMemo->document_number}",
            'posting_date' => $postedCreditMemo->posting_date,
            'document_date' => $postedCreditMemo->document_date,
            'debit_amount' => 0,
            'credit_amount' => $creditAmount,
            'amount' => -$creditAmount,
            'running_balance' => (float) $receivable['documentEntry']->running_balance - $creditAmount,
            'remaining_amount' => $creditAmount,
            'open' => true,
            'fully_applied' => false,
            'currency_code' => 'NGN',
            'original_debit_amount' => 0,
            'original_credit_amount' => $creditAmount,
            'currency_factor' => 1,
            'general_business_posting_group_id' => $customer->general_business_posting_group_id,
            'customer_posting_group_id' => $customer->customer_posting_group_id,
            'source_type' => PostedSalesCreditMemo::class,
            'source_id' => $postedCreditMemo->id,
            'created_by' => $user->id,
        ]);

        return compact('customer', 'postedInvoice', 'postedCreditMemo', 'documentEntry', 'user');
    }

    /**
     * @return array{vendor: Vendor, postedInvoice: PostedPurchaseInvoice, postedCreditMemo: PostedPurchaseCreditMemo, documentEntry: VendorLedgerEntry, user: User}
     */
    protected function createPostedPurchaseCreditMemoFixture(float $creditAmount = 50000.00): array
    {
        $payable = $this->createPostedPayableFixture(max($creditAmount * 2, 100000));
        $vendor = $payable['vendor'];
        $user = $payable['user'];
        $postedInvoice = $payable['postedInvoice'];

        $postedCreditMemo = PostedPurchaseCreditMemo::query()->create([
            'document_number' => 'PPCM-'.$vendor->id.'-001',
            'vendor_id' => $vendor->id,
            'vendor_name' => $vendor->vendor_name,
            'vendor_address' => (string) $vendor->address,
            'vendor_city' => $vendor->city,
            'vendor_post_code' => $vendor->postal_code,
            'vendor_country' => $vendor->country,
            'posting_date' => now()->subDays(5),
            'document_date' => now()->subDays(5),
            'vendor_posting_group_id' => $vendor->vendor_posting_group_id,
            'general_business_posting_group_id' => $vendor->general_business_posting_group_id,
            'currency_code' => 'NGN',
            'currency_factor' => 1,
            'subtotal' => -$creditAmount,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'grand_total' => -$creditAmount,
            'posted' => true,
            'posted_at' => now()->subDays(5),
            'posted_by' => $user->id,
            'corrects_invoice_number' => $postedInvoice->document_number,
            'description' => 'Fixture purchase credit memo',
        ]);

        $documentEntry = VendorLedgerEntry::query()->create([
            'entry_number' => 2,
            'vendor_id' => $vendor->id,
            'document_type' => 'PURCHASE_CREDIT_MEMO',
            'document_number' => $postedCreditMemo->document_number,
            'description' => "Posted credit memo {$postedCreditMemo->document_number}",
            'posting_date' => $postedCreditMemo->posting_date,
            'document_date' => $postedCreditMemo->document_date,
            'debit_amount' => 0,
            'credit_amount' => $creditAmount,
            'amount' => -$creditAmount,
            'running_balance' => (float) $payable['documentEntry']->running_balance - $creditAmount,
            'remaining_amount' => $creditAmount,
            'open' => true,
            'fully_applied' => false,
            'currency_code' => 'NGN',
            'original_debit_amount' => 0,
            'original_credit_amount' => $creditAmount,
            'currency_factor' => 1,
            'general_business_posting_group_id' => $vendor->general_business_posting_group_id,
            'vendor_posting_group_id' => $vendor->vendor_posting_group_id,
            'source_type' => PostedPurchaseCreditMemo::class,
            'source_id' => $postedCreditMemo->id,
            'created_by' => $user->id,
        ]);

        return compact('vendor', 'postedInvoice', 'postedCreditMemo', 'documentEntry', 'user');
    }

    /**
     * @return array{bankAccount: BankAccount, reconciliation: BankReconciliation, ledgerEntry: BankAccountLedgerEntry, statementLine: BankAccountStatementLine, user: User}
     */
    protected function createBankReconciliationFixture(float $amount = 5000.00): array
    {
        $user = User::factory()->create();
        $bankAccount = BankAccount::factory()->receiptOnly()->create([
            'current_balance' => $amount,
            'available_balance' => $amount,
        ]);

        $ledgerEntry = BankAccountLedgerEntry::query()->create([
            'entry_number' => 1,
            'bank_account_id' => $bankAccount->id,
            'bank_account_no' => $bankAccount->account_code,
            'posting_date' => now()->subDay(),
            'document_date' => now()->subDay(),
            'document_type' => 'payment',
            'document_no' => 'BANK-'.$bankAccount->id.'-001',
            'external_document_no' => 'REF-'.$bankAccount->id.'-001',
            'description' => 'Fixture bank ledger entry',
            'entry_type' => BankAccountLedgerEntryType::TRANSFER,
            'amount' => (float) $amount,
            'amount_lcy' => (float) $amount,
            'debit_amount' => 0,
            'credit_amount' => $amount,
            'currency_code' => 'NGN',
            'currency_factor' => 1,
            'balance' => $amount,
            'balance_lcy' => $amount,
            'status' => BankAccountLedgerEntryStatus::OPEN,
            'open' => true,
            'source_type' => 'bank',
            'source_no' => 'BANK-'.$bankAccount->id.'-001',
            'user_id' => $user->id,
        ]);

        $reconciliation = BankReconciliation::query()->create([
            'bank_account_id' => $bankAccount->id,
            'statement_no' => 'STM-'.$bankAccount->id.'-001',
            'statement_date' => now(),
            'statement_ending_balance' => $amount,
            'bank_balance_at_reconciliation' => $amount,
            'uncleared_deposits' => 0,
            'uncleared_withdrawals' => 0,
            'adjusted_bank_balance' => $amount,
            'reconciled' => false,
        ]);

        $statementLine = BankAccountStatementLine::query()->create([
            'bank_account_id' => $bankAccount->id,
            'statement_no' => $reconciliation->statement_no,
            'statement_line_no' => 1,
            'transaction_date' => now(),
            'description' => 'Fixture bank statement line',
            'reference_no' => 'REF-'.$bankAccount->id.'-001',
            'statement_amount' => $amount,
            'debit_amount' => 0,
            'credit_amount' => $amount,
            'reconciled' => false,
            'difference' => 0,
        ]);

        return compact('bankAccount', 'reconciliation', 'ledgerEntry', 'statementLine', 'user');
    }

    protected function ensureOpenAccountingPeriod(\DateTimeInterface $date): AccountingPeriod
    {
        $startDate = Carbon::parse($date)->startOfMonth()->toDateString();
        $endDate = Carbon::parse($date)->endOfMonth()->toDateString();

        $existingPeriod = AccountingPeriod::query()
            ->whereDate('start_date', $startDate)
            ->whereDate('end_date', $endDate)
            ->first();

        if ($existingPeriod instanceof AccountingPeriod) {
            return $existingPeriod;
        }

        return AccountingPeriod::query()->create([
            'start_date' => $startDate,
            'end_date' => $endDate,
            'name' => Carbon::parse($date)->format('F Y'),
            'is_closed' => false,
        ]);
    }
}
