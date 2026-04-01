<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorPostingGroup extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'payables_account_id',
        'payment_disc_debit_account_id',
        'payment_disc_credit_account_id',
        'invoice_rounding_account_id',
        'blocked',
    ];

    protected $casts = [
        'blocked' => 'boolean',
    ];

    // Relationships
    public function payablesAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'payables_account_id');
    }

    public function paymentDiscDebitAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'payment_disc_debit_account_id');
    }

    public function paymentDiscCreditAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'payment_disc_credit_account_id');
    }

    public function invoiceRoundingAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'invoice_rounding_account_id');
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('blocked', false);
    }
}
