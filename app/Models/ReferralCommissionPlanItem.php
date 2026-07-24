<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralCommissionPlanItem extends Model
{
    protected $fillable = [
        'referral_commission_plan_id',
        'item_id',
        'is_included',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_included' => 'boolean',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ReferralCommissionPlan::class, 'referral_commission_plan_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
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
