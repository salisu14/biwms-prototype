<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CustomerReferralStatus;
use Database\Factories\CustomerReferralFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerReferral extends Model
{
    /** @use HasFactory<CustomerReferralFactory> */
    use HasFactory;

    protected $fillable = [
        'business_id',
        'customer_id',
        'referrer_id',
        'status',
        'is_primary',
        'referred_at',
        'effective_from',
        'effective_to',
        'referral_source',
        'reference',
        'notes',
        'approved_by',
        'approved_at',
        'suspended_by',
        'suspended_at',
        'suspension_reason',
        'ended_by',
        'ended_at',
        'end_reason',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => CustomerReferralStatus::class,
        'is_primary' => 'boolean',
        'referred_at' => 'date',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'approved_at' => 'datetime',
        'suspended_at' => 'datetime',
        'ended_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Referrer::class);
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function suspendedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'suspended_by');
    }

    public function endedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ended_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
