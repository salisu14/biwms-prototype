<?php

namespace App\Services\Finance;

use App\Models\Currency;
use App\Models\CustomerLedgerEntry;
use App\Models\Payment;
use App\Models\PaymentApplication;
use App\Models\PostedPurchaseCreditMemo;
use App\Models\PostedSalesCreditMemo;
use App\Models\PostedSalesInvoice;
use App\Models\PurchaseInvoice;
use App\Models\PurchaseOrder;
use App\Models\SalesOrder;
use App\Models\VendorLedgerEntry;
use App\Services\CurrencyService;
use App\Services\PostingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class PaymentService
{
    public function __construct(
        protected PostingService $postingService,
        protected CurrencyService $currencyService
    ) {}

    /**
     * Post a payment (creates ledger entries and G/L entries)
     */
    public function post(Payment $payment, int $userId): void
    {
        if ($payment->status !== 'PENDING') {
            throw new \Exception('Payment is not pending');
        }

        DB::transaction(function () use ($payment, $userId) {
            // 1. Create Ledger Entries
            if ($payment->payment_direction === 'RECEIPT') {
                $this->postCustomerReceipt($payment, $userId);
            } else {
                $this->postVendorPayment($payment, $userId);
            }

            // 2. Create G/L Entries via PostingService
            $this->postGlEntries($payment);

            // 3. Update status
            $payment->update([
                'status' => 'POSTED',
                'posted_by' => $userId,
                'posted_at' => now(),
            ]);
        });
    }

    /**
     * Apply payment to a specific document (invoice or credit memo)
     */
    public function applyToDocument(Payment $payment, array $applicationData, ?int $userId = null): PaymentApplication
    {
        $userId = $userId ?? auth()->id();

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

        $amountToApply = min(
            $applicationData['amount'] ?? $document->remaining_amount,
            $payment->unapplied_amount,
            $document->remaining_amount
        );

        if ($amountToApply <= 0) {
            throw new \Exception('No amount to apply');
        }

        // --- Multi-Currency & Gain/Loss Logic (Business Central Style) ---

        $paymentCurrency = $payment->currency;
        $docCurrencyCode = $document->currency_code;

        // Convert applied amount to LCY using both rates
        $ratePayment = $payment->currency_factor ?? 1.0;
        $rateDocument = $document->currency_factor ?? 1.0;

        $appliedLCYPayment = $amountToApply * $ratePayment;
        $appliedLCYDocument = $amountToApply * $rateDocument;

        // Gain/Loss is the difference in LCY value of the same FCY amount
        // If I pay a $100 invoice that was booked at 1.4, but I pay it at 1.5
        // Invoice LCY = 140, Payment LCY = 150 -> Loss of 10 (for buyer) or Gain (for receiver)
        // Actually, BC logic: Realized Gain/Loss = LCY(Payment) - LCY(Invoice)
        $gainLossAmount = $appliedLCYPayment - $appliedLCYDocument;

        // Create application record
        $application = PaymentApplication::create([
            'payment_id' => $payment->id,
            'document_type' => $applicationData['document_type'],
            'document_id' => $document->id,
            'document_number' => $document->document_number,
            'document_original_amount' => $document->grand_total,
            'document_remaining_before' => $document->remaining_amount,
            'amount_applied' => $amountToApply,
            'amount_applied_lcy' => $appliedLCYPayment,
            'gain_loss_amount' => $gainLossAmount,
            'discount_applied' => $applicationData['discount'] ?? 0,
            'write_off_amount' => $applicationData['write_off'] ?? 0,
            'document_remaining_after' => $document->remaining_amount - $amountToApply - ($applicationData['discount'] ?? 0),
            'full_payment' => ($amountToApply + ($applicationData['discount'] ?? 0)) >= ($document->remaining_amount - 0.01),
            'currency_id' => $payment->currency_id,
            'applied_by' => $userId,
            'applied_at' => now(),
        ]);

        // Post Realized Gain/Loss if applicable
        if (abs($gainLossAmount) > 0.001) {
            $this->postingService->postRealizedGainLoss($application);
        }

        // Update document balances through Payment flow only.
        $documentSettleAmount = $amountToApply + ($applicationData['discount'] ?? 0) + $application->write_off_amount;
        $newAmountPaid = (float) ($document->amount_paid ?? 0) + $documentSettleAmount;
        $newRemaining = max(0, (float) ($document->grand_total ?? 0) - $newAmountPaid);

        $document->update([
            'amount_paid' => $newAmountPaid,
            'remaining_amount' => $newRemaining,
            'paid_in_full' => $newRemaining <= 0.01,
            'paid_in_full_date' => $newRemaining <= 0.01 ? now() : null,
        ]);

        if ($document instanceof PostedSalesInvoice && ! empty($document->order_id)) {
            SalesOrder::query()->find($document->order_id)?->refreshLifecycleStatus();
        }

        if ($document instanceof PurchaseInvoice && ! empty($document->order_id)) {
            PurchaseOrder::query()->find($document->order_id)?->refreshLifecycleStatus();
        }

        // Update payment totals
        $payment->applied_amount += $amountToApply;
        $payment->unapplied_amount = $payment->payment_amount - $payment->applied_amount;
        $payment->discount_taken += ($applicationData['discount'] ?? 0);
        $payment->save();

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
        DB::transaction(function () use ($application, $userId) {
            $payment = $application->payment;

            // Reverse document application
            $document = $this->findDocument($application->document_type, $application->document_id);
            if ($document && method_exists($document, 'reversePayment')) {
                $document->reversePayment($application->amount_applied + $application->discount_applied);
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

            // Reverse Gain/Loss G/L entries if they exist
            if (abs($application->gain_loss_amount) > 0.001) {
                $this->postingService->reverseRealizedGainLoss($application);
            }
        });
    }

    /**
     * Void a payment
     */
    public function void(Payment $payment, string $reason, int $userId): void
    {
        if ($payment->reconciled) {
            throw new \Exception('Cannot void reconciled payment');
        }

        DB::transaction(function () use ($payment, $reason, $userId) {
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
        });
    }

    // --- Internal Posting Helpers ---

    protected function postCustomerReceipt(Payment $payment, int $userId): void
    {
        CustomerLedgerEntry::create([
            'entry_number' => CustomerLedgerEntry::getNextEntryNumber($payment->party_id),
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
            'remaining_amount' => $payment->unapplied_amount,
            'open' => $payment->unapplied_amount > 0.01,
            'currency_id' => $payment->currency_id,
            'currency_code' => $payment->currency_code, // Keeping for compat
            'currency_factor' => $payment->currency_factor,
            'original_credit_amount' => $payment->payment_amount, // FCY
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

    protected function findDocument(string $type, int $id): ?Model
    {
        return match ($type) {
            'SALES_INVOICE' => PostedSalesInvoice::find($id),
            'SALES_CREDIT_MEMO' => PostedSalesCreditMemo::find($id),
            'PURCHASE_INVOICE' => PurchaseInvoice::find($id),
            'PURCHASE_CREDIT_MEMO' => PostedPurchaseCreditMemo::find($id),
            default => null,
        };
    }

    protected function getDocumentType(Model $document): string
    {
        return match (get_class($document)) {
            PostedSalesInvoice::class => 'SALES_INVOICE',
            PostedSalesCreditMemo::class => 'SALES_CREDIT_MEMO',
            PurchaseInvoice::class => 'PURCHASE_INVOICE',
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
            return PurchaseInvoice::forVendor($payment->party_id)
                ->where(fn (Builder $query) => $query
                    ->where('paid_in_full', false)
                    ->orWhereNull('paid_in_full'))
                ->get();
        }
    }
}
