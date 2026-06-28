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
 * Payment Journal Line — BC "Payment Journal Line" equivalent.
 *
 * The vendor-side counterpart of CashReceiptLine. Represents a single
 * line in the Payment Journal, the pre-posting working document for
 * vendor disbursements (paying supplier invoices, expense claims, etc.).
 *
 * Posting workflow (BC-standard):
 *   1. AP team enters the vendor and the amount to pay
 *   2. "Apply Entries" links each line to an open vendor invoice
 *   3. Post → creates Vendor Ledger Entry + G/L entries (Dr AP, Cr Bank)
 *   4. A Payment record (direction = DISBURSEMENT) is created
 *
 * @see JournalLine — base financial data (date, account, amounts, dims)
 * @see Payment     — the posted disbursement record after posting
 */
class PaymentJournalLine extends Model
{
    use HasFactory;

    protected $table = 'payment_journal_lines';

    protected $fillable = [
        'journal_line_id',
        'vendor_id',
        'vendor_no',
        'amount_paid',
        'amount_paid_lcy',
        'remaining_amount',
        'bank_account_id',
        'bank_account_no',
        'applies_to_doc_type',
        'applies_to_doc_no',
        'applies_to_id',
        'applies_to_amount',
        'payment_method_code',
        'check_no',
        'check_date',
        'due_date',
        'exported_to_payment_jnl',
        'payment_processed',
    ];

    protected $casts = [
        'amount_paid' => 'decimal:4',
        'amount_paid_lcy' => 'decimal:4',
        'remaining_amount' => 'decimal:4',
        'applies_to_amount' => 'decimal:4',
        'check_date' => 'date',
        'due_date' => 'date',
        'exported_to_payment_jnl' => 'boolean',
        'payment_processed' => 'boolean',
    ];

    // --- Relationships ---

    public function journalLine(): BelongsTo
    {
        return $this->belongsTo(JournalLine::class, 'journal_line_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class, 'bank_account_id');
    }

    // --- Helpers ---

    public function getUnappliedAmountAttribute(): float
    {
        return (float) $this->amount_paid - (float) $this->applies_to_amount;
    }

    public function isFullyApplied(): bool
    {
        return $this->unapplied_amount <= 0.01;
    }

    public function isOverdue(): bool
    {
        return $this->due_date && $this->due_date->isPast() && ! $this->payment_processed;
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
            $vendor = $this->vendor()->firstOrFail();
            $bankAccount = $this->bankAccount()->firstOrFail();
            $userId = Auth::id() ?? (int) ($journalLine->created_by ?? 1);

            $payment = Payment::query()->create([
                'payment_number' => $journalLine->document_no,
                'external_reference' => $this->check_no,
                'payment_direction' => 'DISBURSEMENT',
                'party_type' => 'VENDOR',
                'party_id' => $vendor->id,
                'party_name' => $vendor->vendor_name,
                'payment_method' => $this->normalizedPaymentMethod(),
                'bank_account_id' => $bankAccount->id,
                'currency_code' => $journalLine->currency_code,
                'currency_factor' => $journalLine->currency_factor ?: 1,
                'payment_amount' => (float) $this->amount_paid,
                'payment_amount_lcy' => (float) ($this->amount_paid_lcy ?: $this->amount_paid),
                'applied_amount' => 0,
                'unapplied_amount' => (float) $this->amount_paid,
                'payment_date' => $journalLine->document_date ?? $journalLine->posting_date,
                'posting_date' => $journalLine->posting_date,
                'status' => 'APPROVED',
                'created_by' => $userId,
            ]);

            app(PaymentService::class)->post($payment, $userId);

            $this->update([
                'remaining_amount' => $payment->fresh()->unapplied_amount,
                'payment_processed' => true,
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
