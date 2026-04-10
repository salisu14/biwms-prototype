<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BankAccountLedgerEntryStatus;
use App\Enums\BankAccountLedgerEntryType;
use App\Enums\CheckType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class BankAccountLedgerEntry extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'entry_number',
        'bank_account_id',
        'bank_account_no',
        'posting_date',
        'document_date',
        'due_date',
        'document_type',
        'document_no',
        'external_document_no',
        'description',
        'description_2',
        'entry_type',
        'check_type',
        'check_no',
        'check_date',
        'amount',
        'amount_lcy',
        'debit_amount',
        'credit_amount',
        'currency_code',
        'currency_factor',
        'balance',
        'balance_lcy',
        'status',
        'open',
        'statement_no',
        'statement_line_no',
        'statement_date',
        'reconciled_at',
        'reconciled_by',
        'vendor_ledger_entry_id',
        'customer_ledger_entry_id',
        'gl_entry_id',
        'transfer_entry_id',
        'source_type',
        'source_id',
        'source_no',
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
        'dimensions',
        'user_id',
        'journal_batch_name',
        'journal_template_name',
        'journal_line_no',
        'voided_at',
        'voided_by',
        'void_reason',
        'comment',
        'additional_fields',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'document_date' => 'date',
        'due_date' => 'date',
        'check_date' => 'date',
        'statement_date' => 'date',
        'amount' => 'decimal:4',
        'amount_lcy' => 'decimal:4',
        'debit_amount' => 'decimal:4',
        'credit_amount' => 'decimal:4',
        'currency_factor' => 'decimal:6',
        'balance' => 'decimal:4',
        'balance_lcy' => 'decimal:4',
        'entry_type' => BankAccountLedgerEntryType::class,
        'check_type' => CheckType::class,
        'status' => BankAccountLedgerEntryStatus::class,
        'open' => 'boolean',
        'reconciled_at' => 'datetime',
        'voided_at' => 'datetime',
        'dimensions' => 'array',
        'additional_fields' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($entry) {
            // Auto-calculate debit/credit
            if ($entry->entry_type->isDebit()) {
                $entry->debit_amount = abs($entry->amount);
                $entry->credit_amount = 0;
            } elseif ($entry->entry_type->isCredit()) {
                $entry->debit_amount = 0;
                $entry->credit_amount = abs($entry->amount);
            }

            // Calculate LCY amount if foreign currency
            if ($entry->currency_code && $entry->currency_factor) {
                $entry->amount_lcy = $entry->amount * $entry->currency_factor;
            } else {
                $entry->amount_lcy = $entry->amount;
            }
        });
    }

    // Relationships
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function vendorLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(VendorLedgerEntry::class);
    }

    public function customerLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(CustomerLedgerEntry::class);
    }

    public function glEntry(): BelongsTo
    {
        return $this->belongsTo(GlEntry::class, 'gl_entry_id');
    }

    public function transferEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'transfer_entry_id');
    }

    public function reversedEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'reversed_entry_id');
    }

    public function reconciledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function voidedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'voided_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function statementLine(): BelongsTo
    {
        return $this->belongsTo(BankAccountStatementLine::class, 'bank_account_ledger_entry_id');
    }

    // Scopes
    public function scopeOpen($query)
    {
        return $query->where('open', true);
    }

    public function scopeReconciled($query)
    {
        return $query->where('status', BankAccountLedgerEntryStatus::RECONCILED);
    }

    public function scopeUnreconciled($query)
    {
        return $query->where('open', true)
            ->whereNull('statement_no');
    }

    public function scopeForPeriod($query, \DateTime $from, \DateTime $to)
    {
        return $query->whereBetween('posting_date', [$from, $to]);
    }

    public function scopeForBankAccount($query, int $bankAccountId)
    {
        return $query->where('bank_account_id', $bankAccountId);
    }

    public function scopeChecks($query)
    {
        return $query->whereIn('entry_type', [
            BankAccountLedgerEntryType::CHECK,
            BankAccountLedgerEntryType::WITHDRAWAL,
        ]);
    }

    public function scopeDeposits($query)
    {
        return $query->where('entry_type', BankAccountLedgerEntryType::DEPOSIT);
    }

    public function scopeTransfers($query)
    {
        return $query->where('entry_type', BankAccountLedgerEntryType::TRANSFER);
    }

    // Accessors
    public function getIsDebitAttribute(): bool
    {
        return $this->entry_type->isDebit();
    }

    public function getIsCreditAttribute(): bool
    {
        return $this->entry_type->isCredit();
    }

    public function getIsReconciledAttribute(): bool
    {
        return $this->status === BankAccountLedgerEntryStatus::RECONCILED;
    }

    public function getIsVoidAttribute(): bool
    {
        return $this->voided_at !== null;
    }

    // Methods
    public function canReconcile(): bool
    {
        return $this->status->canReconcile();
    }

    public function canVoid(): bool
    {
        return $this->status->canVoid() && ! $this->isVoid;
    }

    /**
     * Reconcile this entry with a statement line
     */
    public function reconcile(BankAccountStatementLine $statementLine, ?int $userId = null): void
    {
        if (! $this->canReconcile()) {
            throw new \InvalidArgumentException('Entry cannot be reconciled');
        }

        $this->update([
            'status' => BankAccountLedgerEntryStatus::RECONCILED,
            'open' => false,
            'statement_no' => $statementLine->statement_no,
            'statement_line_no' => $statementLine->statement_line_no,
            'statement_date' => $statementLine->transaction_date,
            'reconciled_at' => now(),
            'reconciled_by' => $userId ?? auth()->id(),
        ]);

        $statementLine->update([
            'bank_account_ledger_entry_id' => $this->id,
            'reconciled' => true,
            'difference' => $statementLine->statement_amount - $this->amount,
        ]);
    }

    /**
     * Unreconcile entry
     */
    public function unreconcile(): void
    {
        if ($this->status !== BankAccountLedgerEntryStatus::RECONCILED) {
            throw new \InvalidArgumentException('Entry is not reconciled');
        }

        $this->update([
            'status' => BankAccountLedgerEntryStatus::OPEN,
            'open' => true,
            'statement_no' => null,
            'statement_line_no' => null,
            'statement_date' => null,
            'reconciled_at' => null,
            'reconciled_by' => null,
        ]);
    }

    /**
     * Void a check or entry
     */
    public function void(string $reason, ?int $userId = null): void
    {
        if (! $this->canVoid()) {
            throw new \InvalidArgumentException('Entry cannot be voided');
        }

        $this->update([
            'status' => BankAccountLedgerEntryStatus::VOID,
            'open' => false,
            'voided_at' => now(),
            'voided_by' => $userId ?? auth()->id(),
            'void_reason' => $reason,
        ]);

        // Create reversing entry if needed
        if ($this->entry_type === BankAccountLedgerEntryType::CHECK) {
            $this->createVoidReversalEntry();
        }
    }

    /**
     * Create reversal entry for voided check
     */
    protected function createVoidReversalEntry(): void
    {
        // Implementation depends on your void reversal logic
        // Typically creates an offsetting entry
    }

    /**
     * Mark as closed (no longer open for application)
     */
    public function close(): void
    {
        $this->update([
            'status' => BankAccountLedgerEntryStatus::CLOSED,
            'open' => false,
        ]);
    }
}
