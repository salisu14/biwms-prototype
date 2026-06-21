<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentTermsCalculation;
use App\Enums\PaymentTermsDiscountCalculation;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class PaymentTerm extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code', 'description', 'search_description', 'calculation_type',
        'due_date_net_days', 'due_date_day_of_month', 'due_date_months_ahead',
        'discount_allowed', 'discount_percent', 'discount_calculation_type',
        'discount_net_days', 'payment_tolerance_enabled', 'payment_tolerance_percent',
        'max_payment_tolerance_amount', 'late_payment_penalty_percent',
        'late_payment_grace_days', 'discount_account_id', 'payment_tolerance_account_id',
        'shortcut_dimension_1_code', 'shortcut_dimension_2_code',
        'is_active', 'blocked', 'notes', 'extended_fields',
    ];

    protected $casts = [
        'calculation_type' => PaymentTermsCalculation::class,
        'discount_calculation_type' => PaymentTermsDiscountCalculation::class,
        'due_date_net_days' => 'integer',
        'due_date_day_of_month' => 'integer',
        'due_date_months_ahead' => 'integer',
        'discount_allowed' => 'boolean',
        'discount_percent' => 'decimal:2',
        'discount_net_days' => 'integer',
        'payment_tolerance_enabled' => 'boolean',
        'payment_tolerance_percent' => 'decimal:2',
        'max_payment_tolerance_amount' => 'decimal:4',
        'late_payment_penalty_percent' => 'decimal:2',
        'late_payment_grace_days' => 'integer',
        'is_active' => 'boolean',
        'blocked' => 'boolean',
        'extended_fields' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function ($term) {
            if (empty($term->search_description)) {
                $term->search_description = $term->description;
            }
        });
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'payment_terms_code', 'code');
    }

    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class, 'payment_terms_code', 'code');
    }

    public function salesOrders(): HasMany
    {
        return $this->hasMany(SalesOrder::class, 'payment_terms_code', 'code');
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrder::class, 'payment_terms_code', 'code');
    }

    public function discountAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'discount_account_id');
    }

    public function toleranceAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'payment_tolerance_account_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true)->where('blocked', false);
    }

    public function canUse(): bool
    {
        return $this->is_active && ! $this->blocked;
    }

    /**
     * Calculate due date from posting date (BC: CalcDueDate)
     */
    public function calculateDueDate(\DateTime $postingDate): \DateTime
    {
        return match ($this->calculation_type) {
            PaymentTermsCalculation::NET => (clone $postingDate)->modify("+{$this->due_date_net_days} days"),

            PaymentTermsCalculation::END_OF_MONTH => (clone $postingDate)
                ->modify('last day of this month')
                ->modify("+{$this->due_date_net_days} days"),

            PaymentTermsCalculation::END_OF_NEXT_MONTH => (clone $postingDate)
                ->modify('last day of next month')
                ->modify("+{$this->due_date_net_days} days"),

            PaymentTermsCalculation::DUE_DATE => (clone $postingDate)
                ->modify('first day of next month')
                ->setDate((int) $postingDate->format('Y'), (int) $postingDate->format('m'), $this->due_date_day_of_month ?? 1),

            PaymentTermsCalculation::DUE_DAY => (clone $postingDate)
                ->modify("+{$this->due_date_months_ahead} months")
                ->setDate((int) $postingDate->format('Y'), (int) $postingDate->format('m'), $this->due_date_day_of_month ?? 1),

            PaymentTermsCalculation::CASH_RECEIPT => $postingDate, // Immediate

            default => (clone $postingDate)->modify("+{$this->due_date_net_days} days"),
        };
    }

    /**
     * Calculate discount date (BC: CalcDiscountDate)
     */
    public function calculateDiscountDate(\DateTime $postingDate): ?\DateTime
    {
        if (! $this->discount_allowed || $this->discount_percent <= 0) {
            return null;
        }

        return match ($this->discount_calculation_type) {
            PaymentTermsDiscountCalculation::NET_DAYS => (clone $postingDate)->modify("+{$this->discount_net_days} days"),
            PaymentTermsDiscountCalculation::END_OF_MONTH => (clone $postingDate)->modify('last day of this month'),
            PaymentTermsDiscountCalculation::DUE_DATE => $this->calculateDueDate($postingDate),
            default => (clone $postingDate)->modify("+{$this->discount_net_days} days"),
        };
    }

    /**
     * Calculate discount amount
     */
    public function calculateDiscount(float $amount, \DateTime $postingDate, ?\DateTime $paymentDate = null): float
    {
        if (! $this->discount_allowed || $this->discount_percent <= 0) {
            return 0;
        }

        $paymentDate = $paymentDate ?? now();
        $discountDate = $this->calculateDiscountDate($postingDate);

        // Payment must be before or on discount date
        if ($paymentDate > $discountDate) {
            return 0;
        }

        return round($amount * ($this->discount_percent / 100), 4);
    }

    /**
     * Check if payment is within tolerance
     */
    public function isWithinTolerance(float $paymentAmount, float $expectedAmount): bool
    {
        if (! $this->payment_tolerance_enabled) {
            return abs($paymentAmount - $expectedAmount) < 0.01;
        }

        $difference = abs($paymentAmount - $expectedAmount);

        // Check percentage tolerance
        if ($this->payment_tolerance_percent > 0) {
            $maxTolerance = $expectedAmount * ($this->payment_tolerance_percent / 100);
            if ($difference <= $maxTolerance) {
                return true;
            }
        }

        // Check fixed amount tolerance
        if ($this->max_payment_tolerance_amount && $difference <= $this->max_payment_tolerance_amount) {
            return true;
        }

        return false;
    }

    /**
     * Get formatted description (e.g., "2/10 Net 30")
     */
    public function getFormattedDescription(): string
    {
        if ($this->discount_allowed && $this->discount_percent > 0) {
            return sprintf(
                '%s/%s Net %s',
                $this->discount_percent,
                $this->discount_net_days,
                $this->due_date_net_days
            );
        }

        if ($this->calculation_type === PaymentTermsCalculation::NET) {
            return "Net {$this->due_date_net_days} Days";
        }

        return $this->description;
    }
}
