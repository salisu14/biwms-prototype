<?php

declare(strict_types=1);

namespace App\Models;

use App\Services\Finance\PaymentService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Cash Receipt Line — BC "Cash Receipt Journal Line" equivalent.
 *
 * This model represents a single line in the Cash Receipt Journal,
 * which is the pre-posting working document used by accountants to
 * record customer payments received. It extends the base JournalLine
 * with customer-payment-specific fields:
 *
 *   - Customer identification (customer_id, customer_no)
 *   - Amount received in local and foreign currency
 *   - Bank account the payment is deposited to
 *   - "Applies-to" document linkage (ties payment to a specific invoice)
 *   - Payment method (Cash, Check, Bank Transfer, etc.)
 *   - Cheque details (check_no, check_date)
 *
 * Posting workflow (BC-standard):
 *   1. Accountant enters lines in the Cash Receipt Journal
 *   2. "Apply Entries" dialog links each line to an open invoice
 *   3. Post → creates Customer Ledger Entry + G/L entries (Dr Bank, Cr AR)
 *   4. A Payment record is created representing the posted receipt
 *
 * @see JournalLine   — base financial data (date, account, amounts, dims)
 * @see Payment       — the posted receipt record created after posting
 * @see PaymentApplication — the closed invoice application record
 */
class CashReceiptLine extends Model
{
    use HasFactory;

    protected $table = 'cash_receipt_lines';

    protected $fillable = [
        'journal_line_id',
        'customer_id',
        'customer_no',
        'amount_received',
        'amount_received_lcy',
        'remaining_amount',
        'bank_account_id',
        'bank_account_no',
        'applies_to_doc_type',
        'applies_to_doc_no',
        'applies_to_id',
        'applies_to_amount',
        'calculate_vat',
        'payment_method_code',
        'check_no',
        'check_date',
        'exported_to_payment_jnl',
    ];

    protected $casts = [
        'amount_received' => 'decimal:4',
        'amount_received_lcy' => 'decimal:4',
        'remaining_amount' => 'decimal:4',
        'applies_to_amount' => 'decimal:4',
        'calculate_vat' => 'boolean',
        'check_date' => 'date',
        'exported_to_payment_jnl' => 'boolean',
    ];

    // --- Relationships ---

    /**
     * The base journal line carrying financial data
     * (posting date, document no., G/L account, dimensions).
     */
    public function journalLine(): BelongsTo
    {
        return $this->belongsTo(JournalLine::class, 'journal_line_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    // --- Helpers ---

    /** Amount still unallocated to any invoice. */
    public function getUnappliedAmountAttribute(): float
    {
        return (float) $this->amount_received - (float) $this->applies_to_amount;
    }

    public function isFullyApplied(): bool
    {
        return $this->unapplied_amount <= 0.01;
    }

    public function requiresCheckDetails(): bool
    {
        return $this->payment_method_code === 'Check';
    }

    // --- Posting Action ---

    /**
     * @deprecated Legacy UI entry point. Do not create financial side effects
     * directly from journal-line models; route through PaymentService.
     *
     * @throws \Throwable
     */
    public function applyPayment(): void
    {
        DB::transaction(function () {
            $journalLine = $this->journalLine()->firstOrFail();
            $customer = $this->customer()->firstOrFail();
            $bankAccount = $this->bankAccount()->firstOrFail();
            $userId = Auth::id() ?? (int) ($journalLine->created_by ?? 1);

            $payment = Payment::query()->create([
                'payment_number' => $journalLine->document_no,
                'external_reference' => $this->check_no,
                'payment_direction' => 'RECEIPT',
                'party_type' => 'CUSTOMER',
                'party_id' => $customer->id,
                'party_name' => $customer->name,
                'payment_method' => $this->normalizedPaymentMethod(),
                'bank_account_id' => $bankAccount->id,
                'currency_code' => $journalLine->currency_code,
                'currency_factor' => $journalLine->currency_factor ?: 1,
                'payment_amount' => (float) $this->amount_received,
                'payment_amount_lcy' => (float) ($this->amount_received_lcy ?: $this->amount_received),
                'applied_amount' => 0,
                'unapplied_amount' => (float) $this->amount_received,
                'payment_date' => $journalLine->document_date ?? $journalLine->posting_date,
                'posting_date' => $journalLine->posting_date,
                'status' => 'APPROVED',
                'created_by' => $userId,
            ]);

            app(PaymentService::class)->post($payment, $userId);

            $this->update([
                'remaining_amount' => $payment->fresh()->unapplied_amount,
                'exported_to_payment_jnl' => true,
            ]);

            $journalLine->update([
                'status' => 'Posted',
                'posted_at' => now(),
                'posted_document_no' => $payment->payment_number,
            ]);
        });
    }

    private function normalizedPaymentMethod(): string
    {
        return match ($this->payment_method_code) {
            'Cash' => 'CASH',
            'Check' => 'CHECK',
            'Bank Transfer' => 'BANK_TRANSFER',
            'Credit Card' => 'CREDIT_CARD',
            'Electronic' => 'ACH',
            default => 'BANK_TRANSFER',
        };
    }
}
