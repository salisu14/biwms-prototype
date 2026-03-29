<?php
// app/Models/VendorPostingGroup.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VendorPostingGroup extends Model
{
    use HasFactory;

    protected $table = 'vendor_posting_groups';

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

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    // Scope
    public function scopeActive($query)
    {
        return $query->where('blocked', false);
    }
}
