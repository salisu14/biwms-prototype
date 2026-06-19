<?php

namespace App\Models;

use App\Enums\PettyCashVoucherStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class PettyCashVoucher extends Model
{
    use HasFactory;

    protected $fillable = [
        'voucher_number',
        'petty_cash_fund_id',
        'date',
        'payee_name',
        'payee_description',
        'purpose',
        'total_amount',
        'status',
        'requested_by_id',
        'approved_by_id',
        'posted_by_id',
        'posted_at',
        'notes',
        'rejection_reason',
    ];

    protected $casts = [
        'date' => 'date',
        'total_amount' => 'decimal:2',
        'status' => PettyCashVoucherStatus::class,
        'posted_at' => 'datetime',
    ];

    public function fund(): BelongsTo
    {
        return $this->belongsTo(PettyCashFund::class, 'petty_cash_fund_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PettyCashVoucherLine::class);
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(PettyCashTransaction::class);
    }

    public function scopePending($query)
    {
        return $query->where('status', PettyCashVoucherStatus::PENDING);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', PettyCashVoucherStatus::APPROVED);
    }

    public function scopePosted($query)
    {
        return $query->where('status', PettyCashVoucherStatus::POSTED);
    }

    public function canApprove(): bool
    {
        return $this->status === PettyCashVoucherStatus::PENDING;
    }

    public function canPost(): bool
    {
        return $this->status === PettyCashVoucherStatus::APPROVED;
    }

    public function canCancel(): bool
    {
        return in_array($this->status, [
            PettyCashVoucherStatus::PENDING,
            PettyCashVoucherStatus::APPROVED,
        ]);
    }

    protected static function booted(): void
    {
        static::creating(function (PettyCashVoucher $voucher) {
            // Automatically set the requested_by_id to the currently logged-in user
            if (is_null($voucher->requested_by_id) && Auth::check()) {
                $voucher->requested_by_id = Auth::id();
            }
        });
    }
}
