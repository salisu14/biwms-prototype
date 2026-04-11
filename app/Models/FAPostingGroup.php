<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class FAPostingGroup extends Model
{
    use HasFactory;

    protected $table = 'fa_posting_groups';

    protected $fillable = [
        'code', 'description',
        'acquisition_cost_account_id', 'acquisition_cost_offset_account_id',
        'depreciation_account_id', 'depreciation_expense_account_id',
        'maintenance_expense_account_id', 'maintenance_cost_account_id',
        'disposal_proceeds_account_id', 'gain_on_disposal_account_id', 'loss_on_disposal_account_id',
        'appreciation_account_id', 'revaluation_gain_account_id',
        'applicable_tangible_types', 'applicable_intangible_types', 'applicable_liquidity_types',
        'is_active',
    ];

    protected $casts = [
        'applicable_tangible_types' => 'array',
        'applicable_intangible_types' => 'array',
        'applicable_liquidity_types' => 'array',
        'is_active' => 'boolean',
    ];

    public function isApplicableTo(Asset $asset): bool
    {
        if ($asset->isTangible() && $asset->tangible_type) {
            return in_array($asset->tangible_type->value, $this->applicable_tangible_types ?? [], true);
        }

        if ($asset->isIntangible() && $asset->intangible_type) {
            return in_array($asset->intangible_type->value, $this->applicable_intangible_types ?? [], true);
        }

        if ($asset->isLiquidityAsset() && $asset->liquidity_type) {
            return in_array($asset->liquidity_type->value, $this->applicable_liquidity_types ?? [], true);
        }

        return false;
    }

    public function acquisitionAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'acquisition_cost_account_id');
    }

    public function depreciationAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'depreciation_account_id');
    }

    public function depExpenseAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'depreciation_expense_account_id');
    }

    public function appreciationAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'appreciation_account_id');
    }

    public function revaluationGainAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'revaluation_gain_account_id');
    }

    public function disposalProceedsAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'disposal_proceeds_account_id');
    }

    public function gainOnDisposalAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'gain_on_disposal_account_id');
    }

    public function lossOnDisposalAccount()
    {
        return $this->belongsTo(ChartOfAccount::class, 'loss_on_disposal_account_id');
    }
}
