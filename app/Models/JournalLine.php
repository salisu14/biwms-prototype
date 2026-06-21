<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Journal Line — BC "Gen. Journal Line" base record.
 *
 * This is the shared base table (journal_lines) that stores the
 * core financial data common to every journal type:
 *   - Posting date, document type & number
 *   - Account type & account number (G/L, Customer, Vendor, …)
 *   - Debit / Credit / Amount in local and foreign currency
 *   - Balancing account
 *   - Dimensions (shortcut + full set)
 *   - Source and reason codes for the audit trail
 *
 * Domain-specific journal extensions (e.g. JobJournalLine) hang off
 * this record via a one-to-one relationship, adding their specialised
 * fields without duplicating the financial core.
 *
 * Status lifecycle:  Open → Posted | Reversed
 *
 * @property string $status 'Open' | 'Posted' | 'Reversed'
 */
class JournalLine extends Model
{
    use HasFactory;

    protected $table = 'journal_lines';

    protected $fillable = [
        'journal_batch_id',
        'line_no',
        'posting_date',
        'document_date',
        'document_type',
        'document_no',
        'external_document_no',
        'account_type',
        'account_no',
        'description',
        'amount',
        'debit_amount',
        'credit_amount',
        'bal_account_type',
        'bal_account_no',
        'currency_code',
        'currency_factor',
        'amount_lcy',
        'dimensions',
        'shortcut_dim_1',
        'shortcut_dim_2',
        'source_code',
        'reason_code',
        'status',
        'posted_at',
        'posted_document_no',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'document_date' => 'date',
        'dimensions' => 'array',
        'amount' => 'decimal:4',
        'debit_amount' => 'decimal:4',
        'credit_amount' => 'decimal:4',
        'amount_lcy' => 'decimal:4',
        'currency_factor' => 'decimal:8',
        'posted_at' => 'datetime',
    ];

    // --- Relationships ---

    public function batch(): BelongsTo
    {
        return $this->belongsTo(JournalBatch::class, 'journal_batch_id');
    }

    /**
     * Convenience accessor — reaches up through batch to the template.
     * Use sparingly; prefer $line->batch->template to avoid N+1.
     */
    public function getTemplateAttribute(): ?JournalTemplate
    {
        return $this->batch?->template;
    }

    /** One-to-one extension for Job journal lines. */
    public function jobJournalLine(): HasOne
    {
        return $this->hasOne(JobJournalLine::class, 'journal_line_id');
    }

    // --- Computed attributes ---

    /** Net amount: positive = debit-side, negative = credit-side. */
    public function getNetAmountAttribute(): float
    {
        return (float) $this->debit_amount - (float) $this->credit_amount;
    }

    public function isOpen(): bool
    {
        return $this->status === 'Open';
    }

    public function isPosted(): bool
    {
        return $this->status === 'Posted';
    }

    // --- Scopes ---

    public function scopeOpen(Builder $query): Builder
    {
        return $query->where('status', 'Open');
    }

    public function scopePosted(Builder $query): Builder
    {
        return $query->where('status', 'Posted');
    }

    // --- Validation ---

    /**
     * Validate the line satisfies BC journal posting rules.
     *
     * @throws \InvalidArgumentException
     */
    public function validate(): bool
    {
        if ($this->debit_amount == 0 && $this->credit_amount == 0 && $this->amount == 0) {
            throw new \InvalidArgumentException("Journal line {$this->line_no}: amount must be non-zero.");
        }

        if ($this->debit_amount > 0 && $this->credit_amount > 0) {
            throw new \InvalidArgumentException("Journal line {$this->line_no}: cannot have both debit and credit amounts.");
        }

        if (! $this->posting_date) {
            throw new \InvalidArgumentException("Journal line {$this->line_no}: posting date is required.");
        }

        return true;
    }
}
