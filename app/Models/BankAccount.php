<?php
// app/Models/BankAccount.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_code',
        'account_name',
        'bank_name',
        'bank_branch',
        'account_number',
        'routing_number',
        'swift_code',
        'iban',
        'gl_account_id',
        'currency_code',
        'account_type',
        'current_balance',
        'available_balance',
        'last_reconciliation_date',
        'last_reconciliation_balance',
        'next_check_number',
        'check_form_id',
        'active',
        'allow_payments',
        'allow_receipts',
    ];

    protected $casts = [
        'current_balance' => 'decimal:4',
        'available_balance' => 'decimal:4',
        'last_reconciliation_balance' => 'decimal:4',
        'active' => 'boolean',
        'allow_payments' => 'boolean',
        'allow_receipts' => 'boolean',
    ];

    // ==================== RELATIONSHIPS ====================

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'gl_account_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function receipts(): HasMany
    {
        return $this->hasMany(Payment::class)->where('payment_direction', 'RECEIPT');
    }

    public function disbursements(): HasMany
    {
        return $this->hasMany(Payment::class)->where('payment_direction', 'DISBURSEMENT');
    }

    // ==================== SCOPES ====================

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeForPayments($query)
    {
        return $query->where('allow_payments', true);
    }

    public function scopeForReceipts($query)
    {
        return $query->where('allow_receipts', true);
    }

    // ==================== BUSINESS METHODS ====================

    public function updateBalance(float $amount, string $type): void
    {
        if ($type === 'RECEIPT') {
            $this->current_balance += $amount;
            $this->available_balance += $amount;
        } else {
            $this->current_balance -= $amount;
            $this->available_balance -= $amount;
        }

        $this->save();
    }

    public function getNextCheckNumber(): string
    {
        $number = $this->next_check_number ?? '1000';
        $this->next_check_number = (int) $number + 1;
        $this->save();

        return $number;
    }
}
