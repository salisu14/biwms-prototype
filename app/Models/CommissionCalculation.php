<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CommissionCalculationStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CommissionCalculation extends Model
{
    protected $fillable = [
        'business_id',
        'source_type',
        'source_id',
        'source_number',
        'source_posting_date',
        'customer_id',
        'customer_referral_id',
        'referrer_id',
        'commission_plan_id',
        'currency_code',
        'calculation_status',
        'calculated_base_amount',
        'calculated_commission_amount',
        'eligible_line_count',
        'ineligible_line_count',
        'calculated_at',
        'calculated_by',
        'calculation_version',
        'idempotency_key',
        'metadata',
    ];

    protected $casts = [
        'source_posting_date' => 'date',
        'calculation_status' => CommissionCalculationStatus::class,
        'calculated_base_amount' => 'decimal:4',
        'calculated_commission_amount' => 'decimal:4',
        'eligible_line_count' => 'integer',
        'ineligible_line_count' => 'integer',
        'calculated_at' => 'datetime',
        'calculation_version' => 'integer',
        'metadata' => 'array',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function customerReferral(): BelongsTo
    {
        return $this->belongsTo(CustomerReferral::class);
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Referrer::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(ReferralCommissionPlan::class, 'commission_plan_id');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(CommissionCalculationLine::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CommissionLedgerEntry::class);
    }

    public function postedSalesInvoice(): BelongsTo
    {
        return $this->belongsTo(PostedSalesInvoice::class, 'source_id');
    }
}
