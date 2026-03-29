<?php
// app/Models/CustomerPostingGroup.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CustomerPostingGroup extends Model
{
    use HasFactory;

    protected $table = 'customer_posting_groups';

    protected $fillable = [
        'code',
        'description',
        'receivables_account_id',
        'payment_disc_debit_account_id',
        'payment_disc_credit_account_id',
        'invoice_rounding_account_id',
        'debit_rounding_account_id',
        'credit_rounding_account_id',
        'blocked',
    ];

    protected $casts = [
        'blocked' => 'boolean',
    ];

    // Relationships
    public function receivablesAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'receivables_account_id');
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    // Get A/R account (shortcut)
    public function getPayablesAccount(): ChartOfAccount
    {
        return $this->receivablesAccount;
    }

    // Scope
    public function scopeActive($query)
    {
        return $query->where('blocked', false);
    }
}
