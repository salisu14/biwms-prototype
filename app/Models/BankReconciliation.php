<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankReconciliation extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_account_id',
        'statement_no',
        'statement_date',
        'statement_ending_balance',
        'bank_balance_at_reconciliation',
        'uncleared_deposits',
        'uncleared_withdrawals',
        'adjusted_bank_balance',
        'reconciled',
        'reconciled_at',
        'reconciled_by',
    ];

    protected $casts = [
        'statement_date' => 'date',
        'statement_ending_balance' => 'decimal:4',
        'bank_balance_at_reconciliation' => 'decimal:4',
        'uncleared_deposits' => 'decimal:4',
        'uncleared_withdrawals' => 'decimal:4',
        'adjusted_bank_balance' => 'decimal:4',
        'reconciled' => 'boolean',
        'reconciled_at' => 'datetime',
    ];

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    public function reconciledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function statementLines(): HasMany
    {
        return $this->hasMany(BankAccountStatementLine::class, 'statement_no', 'statement_no')
            ->where('bank_account_id', $this->bank_account_id);
    }
}
