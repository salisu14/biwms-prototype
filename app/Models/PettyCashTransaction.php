<?php

namespace App\Models;

use App\Enums\PettyCashTransactionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PettyCashTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'petty_cash_fund_id',
        'petty_cash_voucher_id',
        'transaction_number',
        'date',
        'type',
        'amount',
        'running_balance',
        'gl_entry_id',
        'description',
        'reference_number',
    ];

    protected $casts = [
        'date' => 'date',
        'type' => PettyCashTransactionType::class,
        'amount' => 'decimal:2',
        'running_balance' => 'decimal:2',
    ];

    public function fund(): BelongsTo
    {
        return $this->belongsTo(PettyCashFund::class, 'petty_cash_fund_id');
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(PettyCashVoucher::class, 'petty_cash_voucher_id');
    }

    public function glEntry(): BelongsTo
    {
        return $this->belongsTo(GlEntry::class, 'gl_entry_id');
    }

    public function scopePayments($query)
    {
        return $query->where('type', PettyCashTransactionType::PAYMENT);
    }

    public function scopeReplenishments($query)
    {
        return $query->where('type', PettyCashTransactionType::REPLENISHMENT);
    }
}
