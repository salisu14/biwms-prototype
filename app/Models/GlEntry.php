<?php

// app/Models/GlEntry.php

namespace App\Models;

use App\Enums\SourceType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class GlEntry extends Model
{
    use HasFactory;

    protected $table = 'gl_entries';

    protected $fillable = [
        'entry_number',
        'transaction_number',
        'chart_of_account_id',
        'debit_amount',
        'debit_amount_lcy',
        'credit_amount',
        'credit_amount_lcy',
        'amount',
        'amount_lcy',
        'currency_id',
        'exchange_rate',
        'source_type',
        'source_number',
        'document_type',
        'document_number',
        'document_date',
        'posting_date',
        'user_id',
        'description',
        'comment',
        'dimensions',
        'reconciled',
        'is_closing_entry',
        'closing_fiscal_year',
        'reconciliation_date',
        'item_ledger_entry_id',
        'cust_ledger_entry_id',
        'vendor_ledger_entry_id',
        'sourceable_id',
        'sourceable_type',
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
    ];

    protected $casts = [
        'source_type' => SourceType::class,
        'debit_amount' => 'decimal:2',
        'debit_amount_lcy' => 'decimal:2',
        'credit_amount' => 'decimal:2',
        'credit_amount_lcy' => 'decimal:2',
        'amount' => 'decimal:2',
        'amount_lcy' => 'decimal:2',
        'currency_id' => 'integer',
        'exchange_rate' => 'decimal:6',
        'dimensions' => 'array',
        'document_date' => 'date',
        'posting_date' => 'date',
        'reconciliation_date' => 'date',
        'reconciled' => 'boolean',
        'is_closing_entry' => 'boolean',
        'closing_fiscal_year' => 'integer',
    ];

    // Relationships
    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class);
    }

    public function itemLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(ItemLedgerEntry::class);
    }

    public function sourceable()
    {
        return $this->morphTo();
    }

    // Is debit entry
    public function isDebit(): bool
    {
        return $this->debit_amount > 0;
    }

    // Is credit entry
    public function isCredit(): bool
    {
        return $this->credit_amount > 0;
    }

    // Get net amount (debit - credit)
    public function netAmount(): float
    {
        return $this->debit_amount - $this->credit_amount;
    }

    // Reconcile this entry
    public function reconcile(): void
    {
        $this->update([
            'reconciled' => true,
            'reconciliation_date' => now(),
        ]);
    }

    // Scope
    public function scopeForAccount($query, int $accountId)
    {
        return $query->where('chart_of_account_id', $accountId);
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('posting_date', [$startDate, $endDate]);
    }

    public function scopeUnreconciled($query)
    {
        return $query->where('reconciled', false);
    }

    public function scopeForTransaction($query, int $transactionNumber)
    {
        return $query->where('transaction_number', $transactionNumber);
    }

    protected static function booted(): void
    {
        static::creating(function ($entry) {
            if (Auth::check()) {
                $entry->user_id = $entry->user_id ?? Auth::id();
            }

            if (! $entry->entry_number) {
                $entry->entry_number = (static::max('entry_number') ?? 0) + 1;
            }
        });
    }
}
