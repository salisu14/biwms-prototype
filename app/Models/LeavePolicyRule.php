<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeavePolicyRule extends Model
{
    protected $fillable = [
        'leave_policy_id',
        'leave_type_id',
        'annual_entitlement',
        'accrual_frequency',
        'accrual_amount',
        'maximum_balance',
        'maximum_consecutive_days',
        'carry_forward_allowed',
        'maximum_carry_forward',
        'carry_forward_expiry_months',
        'minimum_service_months',
        'notice_days',
        'allow_negative_balance',
        'requires_manager_approval',
        'requires_hr_approval',
    ];

    protected $casts = [
        'annual_entitlement' => 'decimal:2',
        'accrual_amount' => 'decimal:2',
        'maximum_balance' => 'decimal:2',
        'maximum_consecutive_days' => 'decimal:2',
        'carry_forward_allowed' => 'boolean',
        'allow_negative_balance' => 'boolean',
        'requires_manager_approval' => 'boolean',
        'requires_hr_approval' => 'boolean',
    ];

    public function policy(): BelongsTo
    {
        return $this->belongsTo(LeavePolicy::class, 'leave_policy_id');
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }
}
