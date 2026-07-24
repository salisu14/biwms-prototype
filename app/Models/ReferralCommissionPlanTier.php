<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralCommissionPlanTier extends Model
{
    use HasFactory;

    protected $fillable = [
        'referral_commission_plan_id',
        'sequence',
        'minimum_threshold',
        'maximum_threshold',
        'percentage_rate',
        'fixed_amount',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'sequence' => 'integer',
        'minimum_threshold' => 'decimal:4',
        'maximum_threshold' => 'decimal:4',
        'percentage_rate' => 'decimal:4',
        'fixed_amount' => 'decimal:4',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ReferralCommissionPlan::class, 'referral_commission_plan_id');
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
