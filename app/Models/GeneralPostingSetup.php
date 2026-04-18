<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GeneralPostingSetup extends Model
{
    use HasFactory;

    protected $table = 'general_posting_setups';

    protected $fillable = [
        'general_business_posting_group_id',
        'general_product_posting_group_id',
        'blocked',

        // Sales accounts
        'sales_account_id',
        'sales_credit_memo_account_id',
        'sales_prepayment_account_id',

        // COGS accounts
        'cogs_account_id',
        'cogs_credit_memo_account_id',
        'cogs_prepayment_account_id',

        // Purchase/Inventory accounts
        'inventory_adj_account_id',
        'inventory_account_id',
        'direct_cost_applied_account_id',
        'overhead_applied_account_id',
        'purchase_variance_account_id',
        'material_variance_account_id',
        'capacity_variance_account_id',
        'capacity_overhead_variance_account_id',
        'manufacturing_overhead_variance_account_id',
        'purchase_account_id',
        'purchase_credit_memo_account_id',
    ];

    protected $casts = [
        'blocked' => 'boolean',
    ];

    // Relationships to Posting Groups
    public function generalBusinessPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralBusinessPostingGroup::class);
    }

    public function generalProductPostingGroup(): BelongsTo
    {
        return $this->belongsTo(GeneralProductPostingGroup::class);
    }

    // Sales Account Relationships
    public function salesAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'sales_account_id');
    }

    public function salesCreditMemoAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'sales_credit_memo_account_id');
    }

    public function salesPrepaymentAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'sales_prepayment_account_id');
    }

    // COGS Account Relationships
    public function cogsAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'cogs_account_id');
    }

    public function cogsCreditMemoAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'cogs_credit_memo_account_id');
    }

    public function cogsPrepaymentAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'cogs_prepayment_account_id');
    }

    // Inventory Account Relationships
    public function inventoryAdjAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'inventory_adj_account_id');
    }

    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'inventory_account_id');
    }

    public function directCostAppliedAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'direct_cost_applied_account_id');
    }

    public function overheadAppliedAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'overhead_applied_account_id');
    }

    public function purchaseVarianceAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'purchase_variance_account_id');
    }

    public function materialVarianceAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'material_variance_account_id');
    }

    public function capacityVarianceAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'capacity_variance_account_id');
    }

    public function capacityOverheadVarianceAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'capacity_overhead_variance_account_id');
    }

    public function manufacturingOverheadVarianceAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'manufacturing_overhead_variance_account_id');
    }

    public function purchaseAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'purchase_account_id');
    }

    public function purchaseCreditMemoAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'purchase_credit_memo_account_id');
    }

    // ============== HELPER METHODS FOR POSTINGSERVICE ==============

    /**
     * Get sales account for posting (used by PostingService)
     */
    public function getSalesAccount(): ?ChartOfAccount
    {
        return $this->salesAccount;
    }

    /**
     * Get COGS account for posting (used by PostingService)
     */
    public function getCogsAccount(): ?ChartOfAccount
    {
        return $this->cogsAccount;
    }

    /**
     * Get inventory account for posting (used by PostingService)
     */
    public function getInventoryAccount(): ?ChartOfAccount
    {
        return $this->inventoryAccount;
    }

    /**
     * Get purchase account for posting
     */
    public function getPurchaseAccount(): ?ChartOfAccount
    {
        return $this->purchaseAccount ?? $this->inventoryAccount;
    }

    /**
     * Get sales credit memo account
     */
    public function getSalesCreditMemoAccount(): ?ChartOfAccount
    {
        return $this->salesCreditMemoAccount;
    }

    /**
     * Get COGS credit memo account
     */
    public function getCogsCreditMemoAccount(): ?ChartOfAccount
    {
        return $this->cogsCreditMemoAccount;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('blocked', false);
    }

    public function scopeForCombination($query, int $businessGroupId, int $productGroupId)
    {
        return $query->where([
            'general_business_posting_group_id' => $businessGroupId,
            'general_product_posting_group_id' => $productGroupId,
        ]);
    }

    // Business Logic Methods

    /**
     * Check if setup is complete for sales posting
     */
    public function isSalesComplete(): bool
    {
        return ! is_null($this->sales_account_id) &&
            ! is_null($this->cogs_account_id);
    }

    /**
     * Check if setup is complete for inventory posting
     */
    public function isInventoryComplete(): bool
    {
        return ! is_null($this->inventory_account_id) &&
            ! is_null($this->inventory_adj_account_id);
    }

    /**
     * Validate all required accounts are set
     */
    public function validateComplete(): void
    {
        $missing = [];

        if (is_null($this->sales_account_id)) {
            $missing[] = 'Sales Account';
        }
        if (is_null($this->cogs_account_id)) {
            $missing[] = 'COGS Account';
        }
        if (is_null($this->inventory_account_id)) {
            $missing[] = 'Inventory Account';
        }
        if (is_null($this->inventory_adj_account_id)) {
            $missing[] = 'Inventory Adjustment Account';
        }

        if (! empty($missing)) {
            $businessGroup = $this->generalBusinessPostingGroup?->code ?? 'N/A';
            $productGroup = $this->generalProductPostingGroup?->code ?? 'N/A';
            throw new \RuntimeException(
                "General Posting Setup incomplete for {$businessGroup}/{$productGroup}. Missing: ".implode(', ', $missing)
            );
        }
    }

    /**
     * Get missing account types
     */
    public function getMissingAccounts(): array
    {
        $missing = [];

        if (is_null($this->sales_account_id)) {
            $missing[] = 'SALES';
        }
        if (is_null($this->cogs_account_id)) {
            $missing[] = 'COGS';
        }
        if (is_null($this->inventory_account_id)) {
            $missing[] = 'INVENTORY';
        }
        if (is_null($this->inventory_adj_account_id)) {
            $missing[] = 'INVENTORY_ADJUSTMENT';
        }

        return $missing;
    }

    /**
     * Check if setup is complete
     */
    public function isComplete(): bool
    {
        return empty($this->getMissingAccounts());
    }
}
