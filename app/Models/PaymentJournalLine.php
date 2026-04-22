<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
     * Apply this payment to the vendor ledger and post to G/L.
     *
     * Creates:
     *   - VendorLedgerEntry (Payment type, positive = reduces AP liability)
     *   - VendLedgerEntryApplication (if applies_to_id is set)
     *   - G/L entries: Dr Accounts Payable, Cr Bank Account
     *
     * @throws \Throwable
     */
    public function applyPayment(): void
    {
        DB::transaction(function () {
            // 1. Create vendor ledger payment entry
            $vendLedgerEntry = VendorLedgerEntry::create([
                'vendor_id' => $this->vendor_id,
                'entry_type' => 'Payment',
                'amount' => $this->amount_paid,
                'remaining_amount' => $this->amount_paid,
                'posting_date' => $this->journalLine->posting_date,
                'document_no' => $this->journalLine->document_no,
                'external_document_no' => $this->check_no,
            ]);

            // 2. Apply to specific invoice when "Applies-to" is set
            if ($this->applies_to_id) {
                $invoiceEntry = VendorLedgerEntry::findOrFail($this->applies_to_id);
                $appliedAmount = min(abs($invoiceEntry->remaining_amount), $this->amount_paid);

                VendLedgerEntryApplication::create([
                    'vendor_id' => $this->vendor_id,
                    'entry_type' => 'Application',
                    'amount' => $appliedAmount,
                    'applied_entry_id' => $invoiceEntry->id,
                    'applying_entry_id' => $vendLedgerEntry->id,
                ]);

                $invoiceEntry->decrement('remaining_amount', $appliedAmount);
                $vendLedgerEntry->decrement('remaining_amount', $appliedAmount);
                $this->update(['remaining_amount' => $this->amount_paid - $appliedAmount]);
            }

            // 3. Post to G/L: Dr Accounts Payable, Cr Bank Account
            $this->postToGL();

            $this->update(['payment_processed' => true]);
        });
    }

    protected function postToGL(): void
    {
        $vendorPostingGroup = $this->vendor->vendorPostingGroup;
        $date = $this->journalLine->posting_date;
        $docNo = $this->journalLine->document_no;

        // Debit Accounts Payable
        GlEntry::create([
            'account_no' => $vendorPostingGroup->payables_account,
            'debit_amount' => $this->amount_paid,
            'credit_amount' => 0,
            'posting_date' => $date,
            'document_no' => $docNo,
        ]);

        // Credit Bank Account
        GlEntry::create([
            'account_no' => $this->bankAccount->gl_account_no,
            'debit_amount' => 0,
            'credit_amount' => $this->amount_paid,
            'posting_date' => $date,
            'document_no' => $docNo,
        ]);
    }
}
