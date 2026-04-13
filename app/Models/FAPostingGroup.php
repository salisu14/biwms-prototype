<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FAPostingGroup extends Model
{
    use HasFactory;

    protected $table = 'fa_posting_groups';

    protected $fillable = [
        'code',
        'description',
        'acquisition_cost_account_id',
        'acquisition_cost_account_id_lcy',
        'depreciation_expense_account_id',
        'accumulated_depreciation_account_id',
        'revaluation_account_id',
        'reversal_of_revaluation_id',
        'disposal_proceeds_account_id',
        'disposal_gain_account_id',
        'disposal_loss_account_id',
        'maintenance_expense_account_id',
        'capitalization_account_id',
        'tax_depreciation_account_id',
        'deferred_tax_account_id',
        'auto_depreciate_acquisition_year',
        'depreciation_calculation',
        'depreciation_start',
        'is_active',
    ];

    protected $casts = [
        'auto_depreciate_acquisition_year' => 'boolean',
        'is_active' => 'boolean',
    ];

    // --- Acquisition accounts ---

    public function acquisitionCostAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'acquisition_cost_account_id');
    }

    public function acquisitionCostAccountLcy(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'acquisition_cost_account_id_lcy');
    }

    // --- Depreciation accounts ---

    public function depreciationExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'depreciation_expense_account_id');
    }

    public function accumulatedDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'accumulated_depreciation_account_id');
    }

    // --- Revaluation accounts ---

    public function revaluationAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'revaluation_account_id');
    }

    public function reversalOfRevaluation(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'reversal_of_revaluation_id');
    }

    // --- Disposal accounts ---

    public function disposalProceedsAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'disposal_proceeds_account_id');
    }

    public function disposalGainAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'disposal_gain_account_id');
    }

    public function disposalLossAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'disposal_loss_account_id');
    }

    // --- Maintenance & Capitalization accounts ---

    public function maintenanceExpenseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'maintenance_expense_account_id');
    }

    public function capitalizationAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'capitalization_account_id');
    }

    // --- Tax accounts ---

    public function taxDepreciationAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'tax_depreciation_account_id');
    }

    public function deferredTaxAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'deferred_tax_account_id');
    }
}
