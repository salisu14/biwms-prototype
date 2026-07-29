<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionCalculationBasis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionCalculationLine extends Model
{
    protected $fillable = [
        'commission_calculation_id',
        'source_line_type',
        'source_line_id',
        'source_line_number',
        'item_id',
        'description',
        'quantity',
        'unit_of_measure_id',
        'gross_amount',
        'discount_amount',
        'net_amount',
        'recognized_cost_amount',
        'gross_profit_amount',
        'eligible_base_amount',
        'commission_basis',
        'commission_rate',
        'fixed_commission_amount',
        'calculated_commission_amount',
        'commission_plan_rule_id',
        'commission_tier_id',
        'eligibility_status',
        'ineligibility_reason',
        'calculation_snapshot',
        'idempotency_key',
    ];

    protected $casts = [
        'quantity' => 'decimal:8',
        'gross_amount' => 'decimal:4',
        'discount_amount' => 'decimal:4',
        'net_amount' => 'decimal:4',
        'recognized_cost_amount' => 'decimal:4',
        'gross_profit_amount' => 'decimal:4',
        'eligible_base_amount' => 'decimal:4',
        'commission_basis' => CommissionCalculationBasis::class,
        'commission_rate' => 'decimal:4',
        'fixed_commission_amount' => 'decimal:4',
        'calculated_commission_amount' => 'decimal:4',
        'calculation_snapshot' => 'array',
    ];

    public function calculation(): BelongsTo
    {
        return $this->belongsTo(CommissionCalculation::class, 'commission_calculation_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function tier(): BelongsTo
    {
        return $this->belongsTo(ReferralCommissionPlanTier::class, 'commission_tier_id');
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CommissionLedgerEntry::class);
    }
}
