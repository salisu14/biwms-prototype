<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReferralCommissionAssignmentStatus;
use App\Enums\ReferralCommissionBasis;
use App\Enums\ReferralCommissionMethod;
use App\Enums\ReferralCommissionPlanStatus;
use App\Enums\ReferralCommissionScope;
use App\Enums\ReferralCommissionTierBasis;
use App\Enums\ReferralFixedAmountApplication;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReferralCommissionPlan extends Model
{
    use HasFactory;
    use SoftDeletes;

    protected $fillable = [
        'business_id',
        'code',
        'name',
        'description',
        'status',
        'commission_basis',
        'commission_method',
        'commission_scope',
        'tier_basis',
        'percentage_rate',
        'fixed_amount',
        'fixed_amount_application',
        'minimum_eligible_amount',
        'maximum_commission_amount',
        'effective_from',
        'effective_to',
        'currency_id',
        'is_default',
        'priority',
        'notes',
        'created_by',
        'updated_by',
        'activated_by',
        'activated_at',
        'inactivated_by',
        'inactivated_at',
        'archived_by',
        'archived_at',
    ];

    protected $casts = [
        'status' => ReferralCommissionPlanStatus::class,
        'commission_basis' => ReferralCommissionBasis::class,
        'commission_method' => ReferralCommissionMethod::class,
        'commission_scope' => ReferralCommissionScope::class,
        'tier_basis' => ReferralCommissionTierBasis::class,
        'fixed_amount_application' => ReferralFixedAmountApplication::class,
        'percentage_rate' => 'decimal:4',
        'fixed_amount' => 'decimal:4',
        'minimum_eligible_amount' => 'decimal:4',
        'maximum_commission_amount' => 'decimal:4',
        'effective_from' => 'date',
        'effective_to' => 'date',
        'is_default' => 'boolean',
        'priority' => 'integer',
        'activated_at' => 'datetime',
        'inactivated_at' => 'datetime',
        'archived_at' => 'datetime',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function tiers(): HasMany
    {
        return $this->hasMany(ReferralCommissionPlanTier::class)->orderBy('sequence');
    }

    public function eligibleItems(): HasMany
    {
        return $this->hasMany(ReferralCommissionPlanItem::class);
    }

    public function eligibleCategories(): HasMany
    {
        return $this->hasMany(ReferralCommissionPlanCategory::class);
    }

    public function referrerAssignments(): HasMany
    {
        return $this->hasMany(ReferrerCommissionPlanAssignment::class);
    }

    public function activeReferrerAssignments(): HasMany
    {
        return $this->referrerAssignments()
            ->where('status', ReferralCommissionAssignmentStatus::ACTIVE)
            ->whereNull('effective_to');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function activatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'activated_by');
    }

    public function inactivatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'inactivated_by');
    }

    public function archivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'archived_by');
    }

    public function isCurrentlyEffective(?CarbonInterface $date = null): bool
    {
        $dateString = ($date ?? today())->toDateString();

        return $this->effective_from?->toDateString() <= $dateString
            && ($this->effective_to === null || $this->effective_to->toDateString() >= $dateString);
    }
}
