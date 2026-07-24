<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReferralCommissionAssignmentStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferrerCommissionPlanAssignment extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'referrer_id',
        'referral_commission_plan_id',
        'status',
        'effective_from',
        'effective_to',
        'is_primary',
        'assignment_reason',
        'end_reason',
        'assigned_by',
        'assigned_at',
        'ended_by',
        'ended_at',
        'cancelled_by',
        'cancelled_at',
        'cancellation_reason',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => ReferralCommissionAssignmentStatus::class,
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_primary' => 'boolean',
        'assigned_at' => 'datetime',
        'ended_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Referrer::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ReferralCommissionPlan::class, 'referral_commission_plan_id');
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
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
