<?php

// app/Models/GeneralPostingSetup.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class GeneralPostingSetup extends Model
{
    use HasFactory;

    protected $table = 'general_posting_setups';

    protected $fillable = [
        'general_business_posting_group_id',
        'general_product_posting_group_id',
        'blocked',
    ];

    protected $casts = [
        'blocked' => 'boolean',
    ];

    // Relationships
    public function businessGroup(): BelongsTo
    {
        return $this->belongsTo(
            GeneralBusinessPostingGroup::class,
            'general_business_posting_group_id'
        );
    }

    public function productGroup(): BelongsTo
    {
        return $this->belongsTo(
            GeneralProductPostingGroup::class,
            'general_product_posting_group_id'
        );
    }

    public function lines(): HasMany
    {
        return $this->hasMany(GeneralPostingSetupLine::class);
    }

    // Helper methods to get specific accounts
    public function getSalesAccount(): ?ChartOfAccount
    {
        return $this->getAccountByType('SALES');
    }

    public function getCogsAccount(): ?ChartOfAccount
    {
        return $this->getAccountByType('COGS');
    }

    public function getPurchaseAccount(): ?ChartOfAccount
    {
        return $this->getAccountByType('PURCHASE');
    }

    public function getInventoryAdjustmentAccount(): ?ChartOfAccount
    {
        return $this->getAccountByType('INVENTORY_ADJUSTMENT');
    }

    public function getDirectCostAppliedAccount(): ?ChartOfAccount
    {
        return $this->getAccountByType('DIRECT_COST_APPLIED');
    }

    public function getPurchaseVarianceAccount(): ?ChartOfAccount
    {
        return $this->getAccountByType('PURCHASE_VARIANCE');
    }

    public function getOverheadAppliedAccount(): ?ChartOfAccount
    {
        return $this->getAccountByType('OVERHEAD_APPLIED');
    }

    private function getAccountByType(string $type): ?ChartOfAccount
    {
        $line = $this->lines()->where('line_type', $type)->first();

        return $line?->chartOfAccount;
    }

    // Validate that all required accounts are configured
    public function isComplete(): bool
    {
        $requiredTypes = ['SALES', 'COGS', 'PURCHASE', 'INVENTORY_ADJUSTMENT'];

        foreach ($requiredTypes as $type) {
            if (! $this->getAccountByType($type)) {
                return false;
            }
        }

        return true;
    }

    // Get missing account types
    public function getMissingAccounts(): array
    {
        $requiredTypes = ['SALES', 'COGS', 'PURCHASE', 'INVENTORY_ADJUSTMENT'];
        $missing = [];

        foreach ($requiredTypes as $type) {
            if (! $this->getAccountByType($type)) {
                $missing[] = $type;
            }
        }

        return $missing;
    }

    // Scope
    public function scopeActive($query)
    {
        return $query->where('blocked', false);
    }

    public function scopeForCombination($query, $businessGroupId, $productGroupId)
    {
        return $query->where([
            'general_business_posting_group_id' => $businessGroupId,
            'general_product_posting_group_id' => $productGroupId,
        ]);
    }
}
