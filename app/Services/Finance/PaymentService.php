<?php

namespace App\Services\Finance;

use App\Events\PaymentApplied;
use App\Events\PaymentUnapplied;
use App\Models\Currency;
use App\Models\CustomerLedgerEntry;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\PostedPurchaseCreditMemo;
use App\Models\PostedPurchaseInvoice;
use App\Models\PostedSalesCreditMemo;
use App\Models\PostedSalesInvoice;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\User;
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

            $this->postingDateValidator->validate($payment->posting_date ?? now());

            if ($payment->status === 'POSTED') {
                throw new \Exception('Payment is already posted.');
            }

            if ($payment->status !== 'APPROVED') {
                throw new \Exception('Only approved payments can be posted.');
            }

            if ((float) $payment->payment_amount <= 0) {
                throw new \Exception('Payment amount must be greater than zero.');
            }

            if (! $payment->bankAccount) {
                throw new \Exception('A bank account is required before posting this payment.');
            }

            if ($payment->payment_direction === 'RECEIPT' && ! $payment->bankAccount->allow_receipts) {
                throw new \Exception('The selected bank account is not enabled for receipts.');
            }

            if ($payment->payment_direction !== 'RECEIPT' && ! $payment->bankAccount->allow_payments) {
                throw new \Exception('The selected bank account is not enabled for payments.');
            }

            // 1. Create Ledger Entries
            if ($payment->payment_direction === 'RECEIPT') {
                $this->postCustomerReceipt($payment, $userId);
            } else {
                $this->postVendorPayment($payment, $userId);
            }

            // 2. Create Bank Ledger Entry
            $this->postBankLedgerEntry($payment, $userId);

            // 3. Create G/L Entries via PostingService
            $this->postGlEntries($payment);

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
        $this->postingDateValidator->validate($payment->posting_date ?? now());
        Gate::forUser(User::query()->findOrFail($userId))->authorize('apply', $payment);

        $application = DB::transaction(function () use ($payment, $applicationData, $userId): PaymentApplication {
            if ($payment->status !== 'POSTED') {
                throw new \Exception('Only posted payments can be applied to documents.');
            }

            $document = $this->findDocument(
                $applicationData['document_type'],
                $applicationData['document_id']
            );

            if (! $document) {
                throw new \Exception('Document not found');
            }

            // Validate party
            $documentPartyId = $document->customer_id ?? $document->vendor_id;
            if ($documentPartyId !== $payment->party_id) {
                throw new \Exception('Document does not belong to this party');
            }

            $expectedDocumentType = $payment->party_type === 'CUSTOMER' ? 'SALES_INVOICE' : 'PURCHASE_INVOICE';
            if (($applicationData['document_type'] ?? null) !== $expectedDocumentType) {
                throw new \Exception('Document type does not match payment party type.');
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
            $documentRemainingAfter = $this->roundMoney($remainingBefore - $amountToApply - $discountApplied - $writeOffAmount, $precision);
            if (abs($documentRemainingAfter) <= $tolerance) {
                $documentRemainingAfter = 0.0;
            }

            $application = PaymentApplication::create([
                'payment_id' => $payment->id,
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
            if ($payment->unapplied_amount <= 0) {
                break;
            }

            $amount = min($payment->unapplied_amount, $doc->remaining_amount);

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
        $this->postingDateValidator->validate($payment->posting_date ?? now());
        Gate::forUser(User::query()->findOrFail($userId))->authorize('unapply', $payment);

        if ($application->reversed) {
            throw new \Exception('Payment application is already reversed.');
        }

        DB::transaction(function () use ($application, $userId) {
            $payment = $application->payment;

            // Reverse document application
            $document = $this->findDocument($application->document_type, $application->document_id);
            if ($document && method_exists($document, 'reversePayment')) {
                $document->reversePayment($application->amount_applied + $application->discount_applied);
            } elseif ($document instanceof PostedSalesInvoice) {
                $precision = $this->resolvePrecision($application->currency ?? null, $document->currency_code);
                $tolerance = $this->resolveTolerance($precision);
                $reversalAmount = $this->roundMoney((float) $application->amount_applied + (float) $application->discount_applied + (float) $application->write_off_amount, $precision);
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
                $this->syncCustomerInvoiceLedgerStatus($document, max(0, $newRemaining), $tolerance);
            }

            // Mark application reversed
            $application->update([
                'reversed' => true,
                'reversed_at' => now(),
                'reversed_by' => $userId,
            ]);

            // Update payment totals
            $payment->applied_amount -= $application->amount_applied;
            $payment->unapplied_amount += $application->amount_applied;
            $payment->discount_taken -= $application->discount_applied;
            $payment->save();

            if ($payment->party_type === 'CUSTOMER') {
                $precision = $this->resolvePrecision($payment->currency ?? null, $payment->currency_code);
                $tolerance = $this->resolveTolerance($precision);
                $this->syncCustomerPaymentLedgerFromPayment($payment, $tolerance);
            }

            // Reverse Gain/Loss G/L entries if they exist
            if (abs($application->gain_loss_amount) > 0.001) {
                $this->postingService->reverseRealizedGainLoss($application);
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

    protected function postCustomerReceipt(Payment $payment, int $userId): void
    {
        $lastEntry = CustomerLedgerEntry::query()
            ->where('customer_id', $payment->party_id)
            ->orderByDesc('entry_number')
            ->first();

        $nextEntryNumber = ((int) ($lastEntry?->entry_number ?? 0)) + 1;
        $runningBalance = (float) ($lastEntry?->running_balance ?? 0) - (float) $payment->payment_amount;

        CustomerLedgerEntry::create([
            'entry_number' => $nextEntryNumber,
            'customer_id' => $payment->party_id,
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
            'source_id' => $payment->id,
            'source_type' => Payment::class,
            'created_by' => $userId,
        ]);
    }

    protected function postVendorPayment(Payment $payment, int $userId): void
    {
        VendorLedgerEntry::create([
            'entry_number' => VendorLedgerEntry::getNextEntryNumber($payment->party_id),
            'vendor_id' => $payment->party_id,
            'document_type' => 'PAYMENT',
            'document_number' => $payment->payment_number,
            'external_document_number' => $payment->external_reference,
            'description' => "Payment {$payment->payment_number}",
            'posting_date' => $payment->posting_date,
            'document_date' => $payment->payment_date,
            'debit_amount' => $payment->payment_amount,
            'credit_amount' => 0,
            'amount' => $payment->payment_amount,
            'remaining_amount' => 0,
            'open' => false,
            'currency_id' => $payment->currency_id,
            'currency_code' => $payment->currency_code,
            'currency_factor' => $payment->currency_factor,
            'original_debit_amount' => $payment->payment_amount,
            'created_by' => $userId,
        ]);
    }

    protected function postGlEntries(Payment $payment): void
    {
        if ($payment->payment_direction === 'RECEIPT') {
            $this->postingService->postPaymentReceipt(
                customer: $payment->customer,
                amount: $payment->payment_amount,
                bankAccount: $payment->bankAccount,
                discount: $payment->discount_taken,
                postingDate: $payment->posting_date->toDateTime(),
                documentNumber: $payment->payment_number,
                currencyId: $payment->currency_id,
                exchangeRate: $payment->currency_factor
            );
        } else {
            $this->postingService->postPaymentDisbursement(
                vendor: $payment->vendor,
                amount: $payment->payment_amount,
                bankAccount: $payment->bankAccount,
                discount: $payment->discount_taken,
                postingDate: $payment->posting_date->toDateTime(),
                documentNumber: $payment->payment_number,
                currencyId: $payment->currency_id,
                exchangeRate: $payment->currency_factor
            );
        }
    }

    protected function postBankLedgerEntry(Payment $payment, int $userId): void
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
            $this->bankAccountLedgerService->postDeposit($payment->bankAccount, $data);

            return;
        }

        $this->bankAccountLedgerService->postPayment($payment->bankAccount, $data);
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
                ->where(fn (Builder $query) => $query
                    ->where('paid_in_full', false)
                    ->orWhereNull('paid_in_full'))
                ->get();
        } else {
            return PostedPurchaseInvoice::forVendor($payment->party_id)
                ->where(fn (Builder $query) => $query
                    ->where('paid_in_full', false)
                    ->orWhereNull('paid_in_full'))
                ->get();
        }
    }
}
