<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReferralCommissionBasis;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralCommissionSetting extends Model
{
    protected $fillable = [
        'business_id',
        'is_enabled',
        'default_commission_basis',
        'default_plan_id',
        'require_plan_assignment',
        'include_tax_in_commission_base',
        'include_shipping_in_commission_base',
        'deduct_line_discounts',
        'deduct_invoice_discounts',
        'allow_commission_on_zero_value_lines',
        'allow_commission_on_free_items',
        'allow_commission_for_inactive_referrer',
        'commission_currency_id',
        'minimum_eligible_sale_amount',
        'commission_decimal_places',
        'rounding_mode',
        'notes',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'default_commission_basis' => ReferralCommissionBasis::class,
        'require_plan_assignment' => 'boolean',
        'include_tax_in_commission_base' => 'boolean',
        'include_shipping_in_commission_base' => 'boolean',
        'deduct_line_discounts' => 'boolean',
        'deduct_invoice_discounts' => 'boolean',
        'allow_commission_on_zero_value_lines' => 'boolean',
        'allow_commission_on_free_items' => 'boolean',
        'allow_commission_for_inactive_referrer' => 'boolean',
        'minimum_eligible_sale_amount' => 'decimal:4',
        'commission_decimal_places' => 'integer',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function defaultPlan(): BelongsTo
    {
        return $this->belongsTo(ReferralCommissionPlan::class, 'default_plan_id');
    }

    public function commissionCurrency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'commission_currency_id');
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
