<?php

declare(strict_types=1);

namespace App\Services\Finance;

use App\Events\PaymentApplied;
use App\Events\PaymentUnapplied;
use App\Exceptions\BusinessException;
use App\Exceptions\PostingSetupException;
use App\Models\BankAccountLedgerEntry;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\GlEntry;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\PostedPurchaseCreditMemo;
use App\Models\PostedPurchaseInvoice;
use App\Models\PostedSalesCreditMemo;
use App\Models\PostedSalesInvoice;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;
use App\Services\AuditTrailService;
use App\Services\BankAccountLedgerService;
use App\Services\CurrencyService;
use App\Services\PostingDateValidator;
use App\Services\PostingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class PaymentService
{
    public function __construct(
        protected PostingService $postingService,
        protected BankAccountLedgerService $bankAccountLedgerService,
        protected CurrencyService $currencyService,
        protected PostingDateValidator $postingDateValidator,
        protected AuditTrailService $auditTrailService
    ) {}

    /**
     * Post a payment (creates ledger entries and G/L entries)
     */
    public function post(Payment $payment, int $userId): void
    {
        Gate::forUser(User::query()->findOrFail($userId))->authorize('post', $payment);

        DB::transaction(function () use ($payment, $userId) {
            /** @var Payment $payment */
            $payment = Payment::query()
                ->with(['bankAccount', 'currency'])
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($payment->status === 'POSTED') {
                throw new BusinessException('Payment is already posted.', title: 'Payment was not posted');
            }

            if ($payment->status !== 'APPROVED') {
                throw new BusinessException('Only approved payments can be posted.', title: 'Payment was not posted');
            }

            if ((float) $payment->payment_amount <= 0) {
                throw new BusinessException('Payment amount must be greater than zero.', title: 'Payment was not posted');
            }

            if (! $payment->bankAccount) {
                throw new BusinessException('A bank account is required before posting this payment.', title: 'Payment was not posted');
            }

            $this->postingDateValidator->validate($payment->posting_date ?? now());

            if ($payment->payment_direction === 'RECEIPT' && ! $payment->bankAccount->allow_receipts) {
                throw new BusinessException('The selected bank account is not enabled for receipts.', title: 'Payment was not posted');
            }

            if ($payment->payment_direction !== 'RECEIPT' && ! $payment->bankAccount->allow_payments) {
                throw new BusinessException('The selected bank account is not enabled for payments.', title: 'Payment was not posted');
            }

            // 1. Create Ledger Entries
            $partyLedgerEntry = null;
            if ($payment->payment_direction === 'RECEIPT') {
                $partyLedgerEntry = $this->postCustomerReceipt($payment, $userId);
            } else {
                $partyLedgerEntry = $this->postVendorPayment($payment, $userId);
            }

            // 2. Create Bank Ledger Entry
            $bankLedgerEntry = $this->postBankLedgerEntry($payment, $userId);

            // 3. Create G/L Entries via PostingService
            $glEntries = $this->postGlEntries($payment, $partyLedgerEntry);
            $this->linkPaymentLedgerEntries($payment, $bankLedgerEntry, $partyLedgerEntry, $glEntries);

            // 4. Update status
            $payment->update([
                'status' => 'POSTED',
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);

            $this->auditTrailService->recordGeneric(
                eventType: 'posting',
                action: $payment->payment_direction === 'RECEIPT' ? 'customer_receipt_posted' : 'vendor_payment_posted',
                auditable: $payment,
                documentType: 'PAYMENT',
                documentNo: $payment->payment_number,
                userId: $userId,
                description: "Payment {$payment->payment_number} posted",
                metadata: [
                    'amount' => $payment->payment_amount,
                    'bank_account_id' => $payment->bank_account_id,
                    'payment_direction' => $payment->payment_direction,
                ],
            );
        });
    }

    /**
     * Apply payment to a specific document (invoice or credit memo)
     */
    public function applyToDocument(Payment $payment, array $applicationData, ?int $userId = null): PaymentApplication
    {
        $userId = $userId ?? auth()->id();
        Gate::forUser(User::query()->findOrFail($userId))->authorize('apply', $payment);

        $application = DB::transaction(function () use ($payment, $applicationData, $userId): PaymentApplication {
            /** @var Payment $payment */
            $payment = Payment::query()
                ->with(['bankAccount', 'currency'])
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($payment->status !== 'POSTED') {
                throw new \Exception('Only posted payments can be applied to documents.');
            }

            $this->postingDateValidator->validate($payment->posting_date ?? now());

            /** @var PostedSalesInvoice|PostedSalesCreditMemo|PostedPurchaseInvoice|PostedPurchaseCreditMemo|null $document */
            $document = $this->lockDocumentForApplication(
                $applicationData['document_type'],
                $applicationData['document_id']
            );

            if (! $document) {
                throw new \Exception('Document not found');
            }

            $this->assertDocumentIsPostable($document, (string) $applicationData['document_type']);

            // Validate party
            $documentPartyId = $document->customer_id ?? $document->vendor_id;
            if ($documentPartyId !== $payment->party_id) {
                throw new \Exception('Document does not belong to this party');
            }

            $expectedDocumentType = $payment->party_type === 'CUSTOMER' ? 'SALES_INVOICE' : 'PURCHASE_INVOICE';
            if (($applicationData['document_type'] ?? null) !== $expectedDocumentType) {
                throw new \Exception('Document type does not match payment party type.');
            }

            $paymentBusinessId = $payment->business_id;
            $documentBusinessId = $document->business_id ?? null;
            if ($paymentBusinessId !== null && $documentBusinessId !== null && (int) $paymentBusinessId !== (int) $documentBusinessId) {
                throw new \Exception('Payment and document must belong to the same business.');
            }

            if (filled($payment->currency_code) && filled($document->currency_code)
                && strtoupper((string) $payment->currency_code) !== strtoupper((string) $document->currency_code)) {
                throw new \Exception('Payment and document currencies must match.');
            }

            $precision = $this->resolvePrecision($payment->currency ?? null, $payment->currency_code);
            $tolerance = $this->resolveTolerance($precision);

            $requestedAmount = (float) ($applicationData['amount'] ?? $document->remaining_amount);
            if ($requestedAmount <= 0) {
                throw new \Exception('No amount to apply');
            }

            if ($requestedAmount - (float) $payment->unapplied_amount > $tolerance) {
                throw new \Exception('Payment does not have enough unapplied amount.');
            }

            if ($requestedAmount - (float) $document->remaining_amount > $tolerance) {
                throw new \Exception('Cannot apply more than the document remaining amount.');
            }

            $amountToApply = min(
                $requestedAmount,
                (float) $payment->unapplied_amount,
                (float) $document->remaining_amount
            );
            $amountToApply = $this->roundMoney($amountToApply, $precision);

            if ($amountToApply <= 0) {
                throw new \Exception('No amount to apply');
            }

            // --- Multi-Currency & Gain/Loss Logic (Business Central Style) ---

            // Convert applied amount to LCY using both rates
            $ratePayment = $payment->currency_factor ?? 1.0;
            $rateDocument = $document->currency_factor ?? 1.0;

            $appliedLCYPayment = $amountToApply * $ratePayment;
            $appliedLCYDocument = $amountToApply * $rateDocument;

            // Realized gain/loss is the LCY value difference for the same FCY amount.
            $gainLossAmount = $appliedLCYPayment - $appliedLCYDocument;

            // Create application record
            $remainingBefore = $this->roundMoney((float) $document->remaining_amount, $precision);
            $discountApplied = $this->roundMoney((float) ($applicationData['discount'] ?? 0), $precision);
            $writeOffAmount = $this->roundMoney((float) ($applicationData['write_off'] ?? 0), $precision);

            if ($discountApplied < 0 || $writeOffAmount < 0) {
                throw new \Exception('Discounts and write-offs cannot be negative.');
            }

            if ($amountToApply + $discountApplied + $writeOffAmount - (float) $document->remaining_amount > $tolerance) {
                throw new \Exception('The payment, discount, and write-off exceed the document remaining amount.');
            }

            $documentRemainingAfter = $this->roundMoney($remainingBefore - $amountToApply - $discountApplied - $writeOffAmount, $precision);
            if (abs($documentRemainingAfter) <= $tolerance) {
                $documentRemainingAfter = 0.0;
            }

            $application = PaymentApplication::create([
                'payment_id' => $payment->id,
                'business_id' => $paymentBusinessId ?? $documentBusinessId,
                'document_type' => $applicationData['document_type'],
                'document_id' => $document->id,
                'document_number' => $document->document_number,
                'document_original_amount' => $document->grand_total,
                'document_remaining_before' => $remainingBefore,
                'amount_applied' => $amountToApply,
                'amount_applied_lcy' => $appliedLCYPayment,
                'gain_loss_amount' => $gainLossAmount,
                'discount_applied' => $discountApplied,
                'write_off_amount' => $writeOffAmount,
                'document_remaining_after' => $documentRemainingAfter,
                'full_payment' => $documentRemainingAfter <= $tolerance,
                'currency_id' => $payment->currency_id,
                'applied_by' => $userId,
                'applied_at' => now(),
            ]);

            // Post Realized Gain/Loss if applicable
            if (abs($gainLossAmount) > 0.001) {
                $this->postingService->postRealizedGainLoss($application);
            }

            // Update document balances through Payment flow only.
            $documentSettleAmount = $this->roundMoney($amountToApply + $discountApplied + $writeOffAmount, $precision);
            $newAmountPaid = $this->roundMoney((float) ($document->amount_paid ?? 0) + $documentSettleAmount, $precision);
            $newRemaining = $this->roundMoney((float) ($document->grand_total ?? 0) - $newAmountPaid, $precision);
            if (abs($newRemaining) <= $tolerance) {
                $newRemaining = 0.0;
            }
            $isPaidInFull = $newRemaining <= $tolerance;

            $document->update([
                'amount_paid' => $newAmountPaid,
                'remaining_amount' => max(0, $newRemaining),
                'paid_in_full' => $isPaidInFull,
                'paid_in_full_date' => $isPaidInFull ? now() : null,
            ]);

            if ($document instanceof PostedSalesInvoice) {
                $this->syncCustomerInvoiceLedgerStatus($document, max(0, $newRemaining), $tolerance);
            }

            if ($document instanceof PostedPurchaseInvoice) {
                $this->syncVendorInvoiceLedgerStatus($document, max(0, $newRemaining), $tolerance);
            }

            if ($document instanceof PostedSalesInvoice && ! empty($document->order_id)) {
                SalesOrder::query()->find($document->order_id)?->refreshLifecycleStatus();
            }

            if ($document instanceof PostedPurchaseInvoice && ! empty($document->order_id)) {
                PurchaseOrder::query()->find($document->order_id)?->refreshLifecycleStatus();
            }

            // Update payment totals
            $payment->applied_amount = $this->roundMoney((float) $payment->applied_amount + $amountToApply, $precision);
            $payment->unapplied_amount = $this->roundMoney((float) $payment->payment_amount - (float) $payment->applied_amount, $precision);
            if (abs((float) $payment->unapplied_amount) <= $tolerance) {
                $payment->unapplied_amount = 0;
            }
            $payment->discount_taken = $this->roundMoney((float) $payment->discount_taken + $discountApplied, $precision);
            $payment->save();

            if ($payment->party_type === 'CUSTOMER') {
                $this->syncCustomerPaymentLedgerFromPayment($payment, $tolerance);
            }

            if ($payment->party_type === 'VENDOR') {
                $this->syncVendorPaymentLedgerFromPayment($payment, $tolerance);
            }

            return $application;
        });

        PaymentApplied::dispatch($application);

        return $application;
    }

    /**
     * Auto-apply payment to oldest open documents (FIFO)
     */
    public function autoApply(Payment $payment): void
    {
        $openDocuments = $this->getOpenDocuments($payment)
            ->sortBy('due_date');

        foreach ($openDocuments as $doc) {
            $payment = $payment->fresh(['bankAccount', 'currency']);

            if (! $payment || (float) $payment->unapplied_amount <= 0) {
                break;
            }

            $amount = min((float) $payment->unapplied_amount, (float) $doc->remaining_amount);

            $this->applyToDocument($payment, [
                'document_type' => $this->getDocumentType($doc),
                'document_id' => $doc->id,
                'amount' => $amount,
            ]);
        }
    }

    /**
     * Unapply a specific application
     */
    public function unapply(PaymentApplication $application, int $userId): void
    {
        $payment = $application->payment;
        Gate::forUser(User::query()->findOrFail($userId))->authorize('unapply', $payment);

        if ($application->reversed) {
            throw new \Exception('Payment application is already reversed.');
        }

        DB::transaction(function () use ($application, $userId) {
            /** @var Payment $payment */
            $payment = Payment::query()
                ->with(['bankAccount', 'currency'])
                ->lockForUpdate()
                ->findOrFail($application->payment_id);

            /** @var PaymentApplication $lockedApplication */
            $lockedApplication = PaymentApplication::query()
                ->lockForUpdate()
                ->findOrFail($application->id);

            if ($lockedApplication->reversed) {
                throw new \Exception('Payment application is already reversed.');
            }

            $this->postingDateValidator->validate($payment->posting_date ?? now());

            // Reverse document application
            $document = $this->lockDocumentForApplication($lockedApplication->document_type, $lockedApplication->document_id);
            if ($document && method_exists($document, 'reversePayment')) {
                $document->reversePayment($lockedApplication->amount_applied + $lockedApplication->discount_applied);
            } elseif ($document instanceof PostedSalesInvoice || $document instanceof PostedPurchaseInvoice) {
                $precision = $this->resolvePrecision($lockedApplication->currency ?? null, $document->currency_code);
                $tolerance = $this->resolveTolerance($precision);
                $reversalAmount = $this->roundMoney((float) $lockedApplication->amount_applied + (float) $lockedApplication->discount_applied + (float) $lockedApplication->write_off_amount, $precision);
                $newAmountPaid = $this->roundMoney((float) $document->amount_paid - $reversalAmount, $precision);
                $newRemaining = $this->roundMoney((float) $document->grand_total - $newAmountPaid, $precision);
                if (abs($newRemaining) <= $tolerance) {
                    $newRemaining = 0.0;
                }
                $document->update([
                    'amount_paid' => max(0, $newAmountPaid),
                    'remaining_amount' => max(0, $newRemaining),
                    'paid_in_full' => $newRemaining <= $tolerance,
                    'paid_in_full_date' => $newRemaining <= $tolerance ? $document->paid_in_full_date : null,
                ]);

                if ($document instanceof PostedSalesInvoice) {
                    $this->syncCustomerInvoiceLedgerStatus($document, max(0, $newRemaining), $tolerance);
                }

                if ($document instanceof PostedPurchaseInvoice) {
                    $this->syncVendorInvoiceLedgerStatus($document, max(0, $newRemaining), $tolerance);
                }
            }

            // Mark application reversed
            $lockedApplication->update([
                'reversed' => true,
                'reversed_at' => now(),
                'reversed_by' => $userId,
            ]);

            // Update payment totals
            $payment->applied_amount -= $lockedApplication->amount_applied;
            $payment->unapplied_amount += $lockedApplication->amount_applied;
            $payment->discount_taken -= $lockedApplication->discount_applied;
            $payment->save();

            if ($payment->party_type === 'CUSTOMER') {
                $precision = $this->resolvePrecision($payment->currency ?? null, $payment->currency_code);
                $tolerance = $this->resolveTolerance($precision);
                $this->syncCustomerPaymentLedgerFromPayment($payment, $tolerance);
            }

            if ($payment->party_type === 'VENDOR') {
                $precision = $this->resolvePrecision($payment->currency ?? null, $payment->currency_code);
                $tolerance = $this->resolveTolerance($precision);
                $this->syncVendorPaymentLedgerFromPayment($payment, $tolerance);
            }

            // Reverse Gain/Loss G/L entries if they exist
            if (abs((float) $lockedApplication->gain_loss_amount) > 0.001) {
                $this->postingService->reverseRealizedGainLoss($lockedApplication);
            }
        });

        PaymentUnapplied::dispatch($application->fresh());
    }

    private function resolvePrecision(?Currency $currency, ?string $currencyCode): int
    {
        if ($currency?->decimal_places !== null) {
            return (int) $currency->decimal_places;
        }

        if ($currencyCode) {
            $resolved = Currency::query()->where('code', $currencyCode)->value('decimal_places');
            if ($resolved !== null) {
                return (int) $resolved;
            }
        }

        return 2;
    }

    private function resolveTolerance(int $precision): float
    {
        return $precision >= 4 ? 0.0001 : 0.01;
    }

    private function roundMoney(float $value, int $precision): float
    {
        return round($value, $precision);
    }

    private function syncCustomerInvoiceLedgerStatus(PostedSalesInvoice $invoice, float $remaining, float $tolerance): void
    {
        $ledgerEntry = CustomerLedgerEntry::query()
            ->where('document_type', 'SALES_INVOICE')
            ->where('document_number', $invoice->document_number)
            ->where('customer_id', $invoice->customer_id)
            ->orderByDesc('id')
            ->first();

        if (! $ledgerEntry) {
            return;
        }

        $ledgerEntry->update([
            'remaining_amount' => max(0, $remaining),
            'open' => $remaining > $tolerance,
            'fully_applied' => $remaining <= $tolerance,
        ]);
    }

    private function syncCustomerPaymentLedgerFromPayment(Payment $payment, float $tolerance): void
    {
        $ledgerEntry = CustomerLedgerEntry::query()
            ->where('document_type', 'PAYMENT')
            ->where('document_number', $payment->payment_number)
            ->where('customer_id', $payment->party_id)
            ->orderByDesc('id')
            ->first();

        if (! $ledgerEntry) {
            return;
        }

        $remaining = $this->roundMoney((float) $payment->unapplied_amount, 4);
        if (abs($remaining) <= $tolerance) {
            $remaining = 0.0;
        }

        $ledgerEntry->update([
            'remaining_amount' => max(0, $remaining),
            'open' => $remaining > $tolerance,
            'fully_applied' => $remaining <= $tolerance,
        ]);
    }

    private function syncVendorInvoiceLedgerStatus(PostedPurchaseInvoice $invoice, float $remaining, float $tolerance): void
    {
        $ledgerEntry = VendorLedgerEntry::query()
            ->where('document_type', 'PURCHASE_INVOICE')
            ->where('document_number', $invoice->document_number)
            ->where('vendor_id', $invoice->vendor_id)
            ->orderByDesc('id')
            ->first();

        if (! $ledgerEntry) {
            return;
        }

        $ledgerEntry->update([
            'remaining_amount' => max(0, $remaining),
            'open' => $remaining > $tolerance,
            'fully_applied' => $remaining <= $tolerance,
        ]);
    }

    private function syncVendorPaymentLedgerFromPayment(Payment $payment, float $tolerance): void
    {
        $ledgerEntry = VendorLedgerEntry::query()
            ->where('document_type', 'PAYMENT')
            ->where('document_number', $payment->payment_number)
            ->where('vendor_id', $payment->party_id)
            ->orderByDesc('id')
            ->first();

        if (! $ledgerEntry) {
            return;
        }

        $remaining = $this->roundMoney((float) $payment->unapplied_amount, 4);
        if (abs($remaining) <= $tolerance) {
            $remaining = 0.0;
        }

        $ledgerEntry->update([
            'remaining_amount' => max(0, $remaining),
            'open' => $remaining > $tolerance,
            'fully_applied' => $remaining <= $tolerance,
        ]);
    }

    /**
     * Void a payment
     */
    public function void(Payment $payment, string $reason, int $userId): void
    {
        DB::transaction(function () use ($payment, $reason, $userId) {
            /** @var Payment $payment */
            $payment = Payment::query()
                ->with(['applications', 'ledgerEntries'])
                ->lockForUpdate()
                ->findOrFail($payment->id);

            if ($payment->reconciled) {
                throw new \Exception('Cannot void reconciled payment');
            }

            if ($payment->status !== 'POSTED') {
                throw new \Exception('Only posted payments can be voided.');
            }

            // 1. Reverse all applications
            foreach ($payment->applications()->where('reversed', false)->get() as $app) {
                $this->unapply($app, $userId);
            }

            // 2. Reverse Ledger Entries
            foreach ($payment->ledgerEntries as $entry) {
                if (method_exists($entry, 'reverse')) {
                    $entry->reverse($userId, "Void payment {$payment->payment_number}");
                }
            }

            // 3. Update status
            $payment->update([
                'status' => 'VOIDED',
                'voided_at' => now(),
                'voided_by' => $userId,
                'void_reason' => $reason,
            ]);

            $this->auditTrailService->recordGeneric(
                eventType: 'reversal',
                action: 'payment_voided',
                auditable: $payment,
                documentType: 'PAYMENT',
                documentNo: $payment->payment_number,
                userId: $userId,
                description: "Payment {$payment->payment_number} voided",
                metadata: [
                    'reason' => $reason,
                    'amount' => $payment->payment_amount,
                ],
            );
        });
    }

    // --- Internal Posting Helpers ---

    protected function postCustomerReceipt(Payment $payment, int $userId): CustomerLedgerEntry
    {
        $customer = $this->resolveCustomerForReceipt($payment);

        $lastEntry = CustomerLedgerEntry::query()
            ->where('customer_id', $customer->id)
            ->orderByDesc('entry_number')
            ->first();

        $nextEntryNumber = ((int) ($lastEntry?->entry_number ?? 0)) + 1;
        $runningBalance = (float) ($lastEntry?->running_balance ?? 0) - (float) $payment->payment_amount;

        return CustomerLedgerEntry::create([
            'entry_number' => $nextEntryNumber,
            'customer_id' => $customer->id,
            'business_id' => $payment->business_id,
            'document_type' => 'PAYMENT',
            'document_number' => $payment->payment_number,
            'external_document_number' => $payment->external_reference,
            'description' => "Payment {$payment->payment_number}",
            'posting_date' => $payment->posting_date,
            'document_date' => $payment->payment_date,
            'debit_amount' => 0,
            'credit_amount' => $payment->payment_amount,
            'amount' => -$payment->payment_amount,
            'running_balance' => $runningBalance,
            'remaining_amount' => $payment->unapplied_amount,
            'open' => $payment->unapplied_amount > 0.01,
            'fully_applied' => ((float) $payment->unapplied_amount) <= 0.01,
            'currency_id' => $payment->currency_id,
            'currency_code' => $payment->currency_code, // Keeping for compat
            'currency_factor' => $payment->currency_factor,
            'original_credit_amount' => $payment->payment_amount, // FCY
            'general_business_posting_group_id' => $customer->general_business_posting_group_id,
            'customer_posting_group_id' => $customer->customer_posting_group_id,
            'source_id' => $payment->id,
            'source_type' => Payment::class,
            'created_by' => $userId,
        ]);
    }

    protected function postVendorPayment(Payment $payment, int $userId): VendorLedgerEntry
    {
        $vendor = $this->resolveVendorForPayment($payment);

        $lastEntry = VendorLedgerEntry::query()
            ->where('vendor_id', $vendor->id)
            ->orderByDesc('entry_number')
            ->first();

        $nextEntryNumber = ((int) ($lastEntry?->entry_number ?? 0)) + 1;
        $runningBalance = (float) ($lastEntry?->running_balance ?? 0) - (float) $payment->payment_amount;

        return VendorLedgerEntry::create([
            'entry_number' => $nextEntryNumber,
            'vendor_id' => $vendor->id,
            'document_type' => 'PAYMENT',
            'document_number' => $payment->payment_number,
            'external_document_number' => $payment->external_reference,
            'description' => "Payment {$payment->payment_number}",
            'posting_date' => $payment->posting_date,
            'document_date' => $payment->payment_date,
            'debit_amount' => 0,
            'credit_amount' => $payment->payment_amount,
            'amount' => -$payment->payment_amount,
            'running_balance' => $runningBalance,
            'remaining_amount' => $payment->unapplied_amount,
            'open' => ((float) $payment->unapplied_amount) > 0.01,
            'fully_applied' => ((float) $payment->unapplied_amount) <= 0.01,
            'currency_id' => $payment->currency_id,
            'currency_code' => $payment->currency_code,
            'currency_factor' => $payment->currency_factor,
            'original_debit_amount' => 0,
            'original_credit_amount' => $payment->payment_amount,
            'general_business_posting_group_id' => $vendor->general_business_posting_group_id,
            'vendor_posting_group_id' => $vendor->vendor_posting_group_id,
            'source_id' => $payment->id,
            'source_type' => Payment::class,
            'created_by' => $userId,
        ]);
    }

    protected function resolveCustomerForReceipt(Payment $payment): Customer
    {
        $customer = Customer::query()
            ->with('customerPostingGroup')
            ->find($payment->party_id);

        if (! $customer) {
            throw new PostingSetupException('Customer could not be resolved for this receipt.', [
                'payment_id' => $payment->id,
                'party_id' => $payment->party_id,
            ]);
        }

        if (! $customer->customer_posting_group_id) {
            throw new PostingSetupException('Customer posting group is not configured for this customer.', [
                'payment_id' => $payment->id,
                'customer_id' => $customer->id,
            ]);
        }

        if (! $customer->general_business_posting_group_id) {
            throw new PostingSetupException('General business posting group is not configured for this customer.', [
                'payment_id' => $payment->id,
                'customer_id' => $customer->id,
            ]);
        }

        if (! $customer->customerPostingGroup?->receivables_account_id) {
            throw new PostingSetupException('Customer posting group receivables account is not configured.', [
                'payment_id' => $payment->id,
                'customer_id' => $customer->id,
                'customer_posting_group_id' => $customer->customer_posting_group_id,
            ]);
        }

        return $customer;
    }

    protected function resolveVendorForPayment(Payment $payment): Vendor
    {
        $vendor = Vendor::query()
            ->with('vendorPostingGroup')
            ->find($payment->party_id);

        if (! $vendor) {
            throw new PostingSetupException('Vendor could not be resolved for this payment.', [
                'payment_id' => $payment->id,
                'party_id' => $payment->party_id,
            ]);
        }

        if (! $vendor->vendor_posting_group_id) {
            throw new PostingSetupException('Vendor posting group is not configured for this vendor.', [
                'payment_id' => $payment->id,
                'vendor_id' => $vendor->id,
            ]);
        }

        if (! $vendor->general_business_posting_group_id) {
            throw new PostingSetupException('General business posting group is not configured for this vendor.', [
                'payment_id' => $payment->id,
                'vendor_id' => $vendor->id,
            ]);
        }

        if (! $vendor->vendorPostingGroup?->payables_account_id) {
            throw new PostingSetupException('Vendor posting group payables account is not configured.', [
                'payment_id' => $payment->id,
                'vendor_id' => $vendor->id,
                'vendor_posting_group_id' => $vendor->vendor_posting_group_id,
            ]);
        }

        return $vendor;
    }

    protected function postGlEntries(Payment $payment, CustomerLedgerEntry|VendorLedgerEntry|null $partyLedgerEntry = null): array
    {
        if ($payment->payment_direction === 'RECEIPT') {
            return $this->postingService->postPaymentReceipt(
                customer: $payment->customer,
                amount: (float) $payment->payment_amount,
                bankAccount: $payment->bankAccount,
                discount: (float) $payment->discount_taken,
                postingDate: $payment->posting_date->toDateTime(),
                documentNumber: $payment->payment_number,
                currencyId: $payment->currency_id,
                exchangeRate: (float) $payment->currency_factor,
                customerLedgerEntryId: $partyLedgerEntry instanceof CustomerLedgerEntry ? $partyLedgerEntry->id : null,
            );
        }

        return $this->postingService->postPaymentDisbursement(
            vendor: $payment->vendor,
            amount: (float) $payment->payment_amount,
            bankAccount: $payment->bankAccount,
            discount: (float) $payment->discount_taken,
            postingDate: $payment->posting_date->toDateTime(),
            documentNumber: $payment->payment_number,
            currencyId: $payment->currency_id,
            exchangeRate: (float) $payment->currency_factor,
            vendorLedgerEntryId: $partyLedgerEntry instanceof VendorLedgerEntry ? $partyLedgerEntry->id : null,
        );
    }

    protected function postBankLedgerEntry(Payment $payment, int $userId): BankAccountLedgerEntry
    {
        $data = [
            'amount' => (float) $payment->payment_amount,
            'posting_date' => $payment->posting_date,
            'document_date' => $payment->payment_date,
            'document_no' => $payment->payment_number,
            'external_document_no' => $payment->external_reference,
            'description' => $payment->payment_direction === 'RECEIPT'
                ? "Receipt from {$payment->party_name}"
                : "Payment to {$payment->party_name}",
            'currency_code' => $payment->currency_code,
            'currency_factor' => $payment->currency_factor,
            'source_type' => Payment::class,
            'source_id' => $payment->id,
            'source_no' => $payment->payment_number,
            'user_id' => $userId,
            'dimensions' => $payment->dimensions,
            'post_gl' => false,
        ];

        if ($payment->payment_direction === 'RECEIPT') {
            return $this->bankAccountLedgerService->postDeposit($payment->bankAccount, $data);
        }

        return $this->bankAccountLedgerService->postPayment($payment->bankAccount, $data);
    }

    /**
     * @param  array<int, GlEntry>  $glEntries
     */
    protected function linkPaymentLedgerEntries(
        Payment $payment,
        BankAccountLedgerEntry $bankLedgerEntry,
        CustomerLedgerEntry|VendorLedgerEntry|null $partyLedgerEntry,
        array $glEntries,
    ): void {
        $bankGlAccountId = $payment->bankAccount?->gl_account_id;
        $bankGlEntry = collect($glEntries)
            ->first(fn (GlEntry $entry): bool => $bankGlAccountId !== null && (int) $entry->chart_of_account_id === (int) $bankGlAccountId);

        $partyGlEntry = collect($glEntries)
            ->first(function (GlEntry $entry) use ($partyLedgerEntry): bool {
                if ($partyLedgerEntry instanceof CustomerLedgerEntry) {
                    return (int) ($entry->cust_ledger_entry_id ?? 0) === $partyLedgerEntry->id;
                }

                if ($partyLedgerEntry instanceof VendorLedgerEntry) {
                    return (int) ($entry->vendor_ledger_entry_id ?? 0) === $partyLedgerEntry->id;
                }

                return false;
            });

        $bankLedgerEntry->forceFill([
            'gl_entry_id' => $bankGlEntry?->id,
            'customer_ledger_entry_id' => $partyLedgerEntry instanceof CustomerLedgerEntry ? $partyLedgerEntry->id : null,
            'vendor_ledger_entry_id' => $partyLedgerEntry instanceof VendorLedgerEntry ? $partyLedgerEntry->id : null,
        ])->save();

        if ($partyLedgerEntry && $partyGlEntry) {
            $partyLedgerEntry->forceFill([
                'gl_entry_id' => $partyGlEntry->id,
            ])->save();
        }
    }

    protected function findDocument(string $type, int $id): ?Model
    {
        return match ($type) {
            'SALES_INVOICE' => PostedSalesInvoice::find($id),
            'SALES_CREDIT_MEMO' => PostedSalesCreditMemo::find($id),
            'PURCHASE_INVOICE' => PostedPurchaseInvoice::find($id),
            'PURCHASE_CREDIT_MEMO' => PostedPurchaseCreditMemo::find($id),
            default => null,
        };
    }

    /**
     * @return PostedSalesInvoice|PostedSalesCreditMemo|PostedPurchaseInvoice|PostedPurchaseCreditMemo|null
     */
    protected function lockDocumentForApplication(string $type, int $id): ?Model
    {
        return match ($type) {
            'SALES_INVOICE' => PostedSalesInvoice::query()->lockForUpdate()->find($id),
            'SALES_CREDIT_MEMO' => PostedSalesCreditMemo::query()->lockForUpdate()->find($id),
            'PURCHASE_INVOICE' => PostedPurchaseInvoice::query()->lockForUpdate()->find($id),
            'PURCHASE_CREDIT_MEMO' => PostedPurchaseCreditMemo::query()->lockForUpdate()->find($id),
            default => null,
        };
    }

    private function assertDocumentIsPostable(Model $document, string $documentType): void
    {
        if ($documentType === 'SALES_INVOICE' && (! $document instanceof PostedSalesInvoice || $document->cancelled)) {
            throw new \Exception('Only an active posted sales invoice can receive a payment.');
        }

        if ($documentType === 'PURCHASE_INVOICE' && (! $document instanceof PostedPurchaseInvoice || $document->cancelled)) {
            throw new \Exception('Only an active posted purchase invoice can receive a payment.');
        }
    }

    protected function getDocumentType(Model $document): string
    {
        return match (get_class($document)) {
            PostedSalesInvoice::class => 'SALES_INVOICE',
            PostedSalesCreditMemo::class => 'SALES_CREDIT_MEMO',
            PostedPurchaseInvoice::class => 'PURCHASE_INVOICE',
            PostedPurchaseCreditMemo::class => 'PURCHASE_CREDIT_MEMO',
            default => 'UNKNOWN',
        };
    }

    protected function getOpenDocuments(Payment $payment)
    {
        if ($payment->party_type === 'CUSTOMER') {
            return PostedSalesInvoice::forCustomer($payment->party_id)
                ->when($payment->business_id !== null, fn (Builder $query) => $query->where('business_id', $payment->business_id))
                ->where(fn (Builder $query) => $query
                    ->where('paid_in_full', false)
                    ->orWhereNull('paid_in_full'))
                ->get();
        } else {
            return PostedPurchaseInvoice::forVendor($payment->party_id)
                ->when($payment->business_id !== null, fn (Builder $query) => $query->where('business_id', $payment->business_id))
                ->where(fn (Builder $query) => $query
                    ->where('paid_in_full', false)
                    ->orWhereNull('paid_in_full'))
                ->get();
        }
    }
}
