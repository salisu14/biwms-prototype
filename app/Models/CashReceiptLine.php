<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
     * Apply this receipt to the customer ledger and post to G/L.
     *
     * Creates:
     *   - CustomerLedgerEntry (Payment type, negative = reduces AR)
     *   - CustLedgerEntryApplication (if applies_to_id is set)
     *   - G/L entries: Dr Bank Account, Cr Accounts Receivable
     *
     * @throws \Throwable
     */
    public function applyPayment(): void
    {
        DB::transaction(function () {
            // 1. Create customer ledger payment entry
            $custLedgerEntry = CustomerLedgerEntry::create([
                'customer_id' => $this->customer_id,
                'entry_type' => 'Payment',
                'amount' => -$this->amount_received,
                'remaining_amount' => -$this->amount_received,
                'posting_date' => $this->journalLine->posting_date,
                'document_no' => $this->journalLine->document_no,
                'external_document_no' => $this->check_no,
            ]);

            // 2. Apply to specific invoice when "Applies-to" is set
            if ($this->applies_to_id) {
                $invoiceEntry = CustomerLedgerEntry::findOrFail($this->applies_to_id);
                $appliedAmount = min(abs($invoiceEntry->remaining_amount), $this->amount_received);

                CustLedgerEntryApplication::create([
                    'customer_id' => $this->customer_id,
                    'entry_type' => 'Application',
                    'amount' => $appliedAmount,
                    'applied_entry_id' => $invoiceEntry->id,
                    'applying_entry_id' => $custLedgerEntry->id,
                ]);

                $invoiceEntry->decrement('remaining_amount', $appliedAmount);
                $custLedgerEntry->increment('remaining_amount', $appliedAmount);
                $this->update(['remaining_amount' => $this->amount_received - $appliedAmount]);
            }

            // 3. Post to G/L: Dr Bank Account, Cr Accounts Receivable
            $this->postToGL();
        });
    }

    protected function postToGL(): void
    {
        $customerPostingGroup = $this->customer->customerPostingGroup;
        $date = $this->journalLine->posting_date;
        $docNo = $this->journalLine->document_no;

        // Debit Bank Account
        GlEntry::create([
            'account_no' => $this->bankAccount->gl_account_no,
            'debit_amount' => $this->amount_received,
            'credit_amount' => 0,
            'posting_date' => $date,
            'document_no' => $docNo,
        ]);

        // Credit Accounts Receivable
        GlEntry::create([
            'account_no' => $customerPostingGroup->receivables_account,
            'debit_amount' => 0,
            'credit_amount' => $this->amount_received,
            'posting_date' => $date,
            'document_no' => $docNo,
        ]);
    }
}
