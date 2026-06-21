<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccountStatementLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_account_id',
        'statement_no',
        'statement_line_no',
        'transaction_date',
        'description',
        'reference_no',
        'statement_amount',
        'debit_amount',
        'credit_amount',
        'bank_account_ledger_entry_id',
        'reconciled',
        'difference',
    ];

    protected $casts = [
        'transaction_date' => 'date',
        'statement_amount' => 'decimal:4',
        'debit_amount' => 'decimal:4',
        'credit_amount' => 'decimal:4',
        'difference' => 'decimal:4',
        'reconciled' => 'boolean',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function ledgerEntry(): BelongsTo
    {
        return $this->belongsTo(BankAccountLedgerEntry::class, 'bank_account_ledger_entry_id');
    }

    public function scopeUnreconciled($query)
    {
        return $query->where('reconciled', false);
    }

    public function scopeForStatement($query, string $statementNo)
    {
        return $query->where('statement_no', $statementNo);
    }
}
