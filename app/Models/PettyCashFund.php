<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PettyCashFund extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'location',
        'custodian_id',
        'imprest_amount',
        'current_balance',
        'currency',
        'is_active',
        'notes',
        'chart_of_account_id',
        'transaction_number',
        'date',
        'type',
        'amount',
        'running_balance',
        'description',
    ];

    protected $casts = [
        'imprest_amount' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'chart_of_account_id');
    }
    public function custodian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function vouchers(): HasMany
    {
        return $this->hasMany(PettyCashVoucher::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PettyCashTransaction::class);
    }

    public function replenish(float $amount): void
    {
        $this->increment('current_balance', $amount);
    }

    public function deduct(float $amount): void
    {
        $this->decrement('current_balance', $amount);
    }
}
