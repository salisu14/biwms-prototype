<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValueEntry extends Model
{
    use HasFactory;

    protected $table = 'value_entries';

    protected $fillable = [
        'entry_no',
        'item_ledger_entry_no',
        'item_ledger_entry_type',
        'source_type',
        'source_no',
        'source_line_no',
        'source_batch_name',
        'item_no',
        'variant_code',
        'location_code',
        'bin_code',
        'posting_date',
        'valuation_date',
        'document_type',
        'document_no',
        'document_line_no',
        'description',
        'quantity',
        'invoiced_quantity',
        'costing_method',
        'cost_amount_actual',
        'cost_amount_actual_acy',
        'cost_amount_expected',
        'cost_amount_expected_acy',
        'direct_cost_amount',
        'indirect_cost_amount',
        'overhead_amount',
        'variance_amount',
        'purchase_variance_amount',
        'material_variance_amount',
        'capacity_variance_amount',
        'capacity_overhead_variance_amount',
        'manufacturing_overhead_variance_amount',
        'unit_cost',
        'unit_cost_acy',
        'single_level_material_cost',
        'single_level_capacity_cost',
        'single_level_subcontracted_cost',
        'single_level_overhead_cost',
        'single_level_mfg_ovhd_cost',
        'rollover_amount',
        'capacity_type',
        'capacity_no',
        'routing_no',
        'routing_reference_no',
        'operation_no',
        'work_center_purch_capacity',
        'work_center_purch_oh_capacity',
        'work_center_purch_direct_cost',
        'work_center_purch_ovhd_cost',
        'production_order_no',
        'production_order_line_no',
        'production_order_component_line_no',
        'prod_order_line_item_no',
        'purchase_order_no',
        'purchase_order_line_no',
        'sales_order_no',
        'sales_order_line_no',
        'vendor_no',
        'customer_no',
        'serial_no',
        'lot_no',
        'expiration_date',
        'gl_posted',
        'gl_posting_date',
        'gl_entry_no',
        'gl_account_no',
        'balancing_account_no',
        'cost_adjusted',
        'cost_adjustment_date',
        'cost_adjustment_entry_no',
        'cost_is_adjusted',
        'cost_is_changed_by_user',
        'global_dimension_1_code',
        'global_dimension_2_code',
        'shortcut_dimension_codes',
        'dimension_set_id',
        'user_id',
        'source_code',
        'reason_code',
        'completely_invoiced',
        'last_invoice',
        'expected_cost',
        'partial_posted',
        'entry_type',
        'adjustment_entry_no',
        'original_entry_no',
        'original_document_no',
        'original_posting_date',
        'job_no',
        'job_task_no',
        'job_line_type',
        'warehouse_activity_no',
        'warehouse_line_no',
        'registering_no',
    ];

    protected $casts = [
        'posting_date' => 'date',
        'valuation_date' => 'date',
        'gl_posting_date' => 'date',
        'cost_adjustment_date' => 'date',
        'original_posting_date' => 'date',
        'expiration_date' => 'date',
        'quantity' => 'decimal:4',
        'invoiced_quantity' => 'decimal:4',
        'cost_amount_actual' => 'decimal:4',
        'cost_amount_actual_acy' => 'decimal:4',
        'cost_amount_expected' => 'decimal:4',
        'cost_amount_expected_acy' => 'decimal:4',
        'direct_cost_amount' => 'decimal:4',
        'indirect_cost_amount' => 'decimal:4',
        'overhead_amount' => 'decimal:4',
        'variance_amount' => 'decimal:4',
        'purchase_variance_amount' => 'decimal:4',
        'material_variance_amount' => 'decimal:4',
        'capacity_variance_amount' => 'decimal:4',
        'capacity_overhead_variance_amount' => 'decimal:4',
        'manufacturing_overhead_variance_amount' => 'decimal:4',
        'unit_cost' => 'decimal:4',
        'unit_cost_acy' => 'decimal:4',
        'single_level_material_cost' => 'decimal:4',
        'single_level_capacity_cost' => 'decimal:4',
        'single_level_subcontracted_cost' => 'decimal:4',
        'single_level_overhead_cost' => 'decimal:4',
        'single_level_mfg_ovhd_cost' => 'decimal:4',
        'rollover_amount' => 'decimal:4',
        'work_center_purch_capacity' => 'decimal:4',
        'work_center_purch_oh_capacity' => 'decimal:4',
        'work_center_purch_direct_cost' => 'decimal:4',
        'work_center_purch_ovhd_cost' => 'decimal:4',
        'shortcut_dimension_codes' => 'array',
        'dimension_set_id' => 'array',
        'gl_posted' => 'boolean',
        'cost_adjusted' => 'boolean',
        'cost_is_adjusted' => 'boolean',
        'cost_is_changed_by_user' => 'boolean',
        'completely_invoiced' => 'boolean',
        'last_invoice' => 'boolean',
        'expected_cost' => 'boolean',
        'partial_posted' => 'boolean',
    ];

    // Auto-generate entry number
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->entry_no)) {
                $model->entry_no = static::max('entry_no') + 1;
            }
        });
    }

    // ==================== RELATIONSHIPS ====================

    public function itemLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(ItemLedgerEntry::class, 'item_ledger_entry_no');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_no', 'no');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_code', 'code');
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_no', 'no');
    }

    public function capacityLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(CapacityLedgerEntry::class, 'source_no', 'production_order_no')
            ->where('source_type', 'PRODUCTION_ORDER');
    }

    public function glEntry(): BelongsTo
    {
        return $this->belongsTo(GLEntry::class, 'gl_entry_no');
    }

    public function chartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'gl_account_no', 'account_number');
    }

    public function balancingChartOfAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'balancing_account_no', 'account_number');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'capacity_no', 'code')
            ->where('capacity_type', 'WORK_CENTER');
    }

    public function machineCenter(): BelongsTo
    {
        return $this->belongsTo(MachineCenter::class, 'capacity_no', 'code')
            ->where('capacity_type', 'MACHINE_CENTER');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_no', 'no');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_no', 'no');
    }

    public function originalEntry(): BelongsTo
    {
        return $this->belongsTo(static::class, 'original_entry_no');
    }

    public function adjustmentEntries()
    {
        return $this->hasMany(static::class, 'original_entry_no');
    }

    // ==================== SCOPES ====================

    public function scopePosted($query)
    {
        return $query->where('gl_posted', true);
    }

    public function scopeUnposted($query)
    {
        return $query->where('gl_posted', false);
    }

    public function scopeAdjusted($query)
    {
        return $query->where('cost_adjusted', true);
    }

    public function scopeUnadjusted($query)
    {
        return $query->where('cost_adjusted', false);
    }

    public function scopeForItem($query, string $itemNo)
    {
        return $query->where('item_no', $itemNo);
    }

    public function scopeForLocation($query, string $locationCode)
    {
        return $query->where('location_code', $locationCode);
    }

    public function scopeForProductionOrder($query, string $productionOrderNo)
    {
        return $query->where('production_order_no', $productionOrderNo);
    }

    public function scopeForPeriod($query, $startDate, $endDate)
    {
        return $query->whereBetween('posting_date', [$startDate, $endDate]);
    }

    public function scopeOfType($query, $entryType)
    {
        return $query->where('item_ledger_entry_type', $entryType);
    }

    public function scopeConsumption($query)
    {
        return $query->where('item_ledger_entry_type', 'CONSUMPTION');
    }

    public function scopeOutput($query)
    {
        return $query->where('item_ledger_entry_type', 'OUTPUT');
    }

    public function scopeCapacity($query)
    {
        return $query->where('item_ledger_entry_type', 'CAPACITY');
    }

    public function scopeWithVariance($query)
    {
        return $query->where('variance_amount', '!=', 0);
    }

    // ==================== BUSINESS LOGIC ====================

    /**
     * Get the total cost (actual + expected)
     */
    public function getTotalCostAttribute(): float
    {
        return $this->cost_amount_actual + $this->cost_amount_expected;
    }

    /**
     * Get cost component breakdown
     */
    public function getCostComponentsAttribute(): array
    {
        return [
            'direct_material' => $this->single_level_material_cost,
            'direct_labor' => $this->single_level_capacity_cost,
            'subcontracted' => $this->single_level_subcontracted_cost,
            'overhead' => $this->single_level_overhead_cost + $this->single_level_mfg_ovhd_cost,
            'variance' => $this->variance_amount,
            'total' => $this->cost_amount_actual,
        ];
    }

    /**
     * Determine G/L account based on transaction type and setup
     */
    public function determineGLAccount(): ?string
    {
        $item = $this->item;
        $location = $this->location_code;

        // Get inventory posting setup
        $setup = InventoryPostingSetup::where('location_code', $location)
            ->where('inventory_posting_group', $item?->inventory_posting_group_code)
            ->first();

        if (! $setup) {
            return null;
        }

        return match ($this->item_ledger_entry_type) {
            'PURCHASE' => $setup->inventory_account,
            'SALE' => $setup->cogs_account,
            'POSITIVE_ADJUSTMENT', 'NEGATIVE_ADJUSTMENT' => $setup->inventory_adj_account,
            'TRANSFER' => $setup->inventory_account,
            'CONSUMPTION' => $setup->wip_account, // Raw Material -> WIP
            'OUTPUT' => $setup->inventory_account, // WIP -> Finished Goods (credit WIP)
            'CAPACITY' => $setup->wip_account, // Labor/Machine -> WIP
            'OVERHEAD' => $setup->wip_account, // Overhead -> WIP
            default => $setup->inventory_account,
        };
    }

    /**
     * Determine balancing account for double-entry posting
     */
    public function determineBalancingAccount(): ?string
    {
        return match ($this->item_ledger_entry_type) {
            'PURCHASE' => $this->vendor?->payables_account,
            'SALE' => $this->getSalesAccount(),
            'CONSUMPTION' => $this->getAppliedAccount(), // Raw Materials account
            'OUTPUT' => $this->getWipAccount(), // WIP account (credit)
            'CAPACITY' => $this->getDirectCostAppliedAccount(),
            'OVERHEAD' => $this->getOverheadAppliedAccount(),
            default => null,
        };
    }

    /**
     * Post this value entry to General Ledger
     */
    public function postToGL(): GLEntry
    {
        if ($this->gl_posted) {
            throw new \Exception("Value Entry {$this->entry_no} already posted to G/L");
        }

        $debitAccount = $this->determineGLAccount();
        $creditAccount = $this->determineBalancingAccount();
        $amount = abs($this->cost_amount_actual);

        // Determine debit/credit based on entry type
        $isDebit = in_array($this->item_ledger_entry_type, [
            'PURCHASE', 'POSITIVE_ADJUSTMENT', 'CONSUMPTION',
            'CAPACITY', 'OVERHEAD', 'TRANSFER_IN',
        ]);

        $glEntry = GLEntry::create([
            'posting_date' => $this->posting_date,
            'document_type' => $this->document_type ?? 'PRODUCTION',
            'document_no' => $this->document_no ?? $this->source_no,
            'description' => $this->getGLDescription(),
            'account_no' => $isDebit ? $debitAccount : $creditAccount,
            'debit_amount' => $isDebit ? $amount : 0,
            'credit_amount' => $isDebit ? 0 : $amount,
            'source_type' => 'VALUE_ENTRY',
            'source_no' => (string) $this->entry_no,
        ]);

        // Create balancing entry
        GLEntry::create([
            'posting_date' => $this->posting_date,
            'document_type' => $this->document_type ?? 'PRODUCTION',
            'document_no' => $this->document_no ?? $this->source_no,
            'description' => $this->getGLDescription().' (Balancing)',
            'account_no' => $isDebit ? $creditAccount : $debitAccount,
            'debit_amount' => $isDebit ? 0 : $amount,
            'credit_amount' => $isDebit ? $amount : 0,
            'source_type' => 'VALUE_ENTRY',
            'source_no' => (string) $this->entry_no,
        ]);

        $this->update([
            'gl_posted' => true,
            'gl_posting_date' => now(),
            'gl_entry_no' => $glEntry->id,
            'gl_account_no' => $debitAccount,
            'balancing_account_no' => $creditAccount,
        ]);

        return $glEntry;
    }

    /**
     * Reverse this value entry (for corrections)
     */
    public function reverse($postingDate = null): self
    {
        $reversal = $this->replicate();
        $reversal->entry_no = static::max('entry_no') + 1;
        $reversal->quantity = -$this->quantity;
        $reversal->invoiced_quantity = -$this->invoiced_quantity;
        $reversal->cost_amount_actual = -$this->cost_amount_actual;
        $reversal->cost_amount_expected = -$this->cost_amount_expected;
        $reversal->direct_cost_amount = -$this->direct_cost_amount;
        $reversal->indirect_cost_amount = -$this->indirect_cost_amount;
        $reversal->overhead_amount = -$this->overhead_amount;
        $reversal->posting_date = $postingDate ?? now();
        $reversal->description = 'Reversal of Entry '.$this->entry_no;
        $reversal->original_entry_no = $this->id;
        $reversal->entry_type = 'REVERSAL';
        $reversal->gl_posted = false;
        $reversal->cost_adjusted = false;
        $reversal->save();

        return $reversal;
    }

    /**
     * Adjust cost (for standard cost variances or revaluation)
     */
    public function adjustCost(float $newCostAmount, string $reason = ''): self
    {
        $adjustment = $this->replicate();
        $adjustment->entry_no = static::max('entry_no') + 1;
        $adjustment->cost_amount_actual = $newCostAmount - $this->cost_amount_actual;
        $adjustment->cost_amount_expected = 0;
        $adjustment->entry_type = 'REVALUATION';
        $adjustment->original_entry_no = $this->id;
        $adjustment->description = "Cost Adjustment: {$reason}";
        $adjustment->adjustment_entry_no = $this->id;
        $adjustment->gl_posted = false;
        $adjustment->save();

        $this->update([
            'cost_adjusted' => true,
            'cost_adjustment_date' => now(),
            'cost_adjustment_entry_no' => $adjustment->id,
        ]);

        return $adjustment;
    }

    // ==================== PRIVATE HELPERS ====================

    private function getSalesAccount(): ?string
    {
        $setup = GeneralPostingSetup::where('gen_bus_posting_group', $this->customer?->gen_bus_posting_group_code)
            ->where('gen_prod_posting_group', $this->item?->gen_prod_posting_group_code)
            ->first();

        return $setup?->cogs_account;
    }

    private function getAppliedAccount(): ?string
    {
        $setup = GeneralPostingSetup::where('gen_bus_posting_group', 'MANUFACTURING')
            ->where('gen_prod_posting_group', $this->item?->gen_prod_posting_group_code)
            ->first();

        return $setup?->direct_cost_applied_account;
    }

    private function getWipAccount(): ?string
    {
        $setup = InventoryPostingSetup::where('location_code', $this->location_code)
            ->where('inventory_posting_group', 'WIP-PROD')
            ->first();

        return $setup?->wip_account;
    }

    private function getDirectCostAppliedAccount(): ?string
    {
        $setup = GeneralPostingSetup::where('gen_bus_posting_group', 'MANUFACTURING')
            ->where('gen_prod_posting_group', 'CAPACITY')
            ->first();

        return $setup?->direct_cost_applied_account;
    }

    private function getOverheadAppliedAccount(): ?string
    {
        $setup = GeneralPostingSetup::where('gen_bus_posting_group', 'MANUFACTURING')
            ->where('gen_prod_posting_group', 'FIN-GOODS')
            ->first();

        return $setup?->overhead_applied_account;
    }

    private function getGLDescription(): string
    {
        return match ($this->item_ledger_entry_type) {
            'CONSUMPTION' => "Consumption: {$this->item_no} -> PO {$this->production_order_no}",
            'OUTPUT' => "Output: PO {$this->production_order_no} -> {$this->item_no}",
            'CAPACITY' => "Capacity: {$this->capacity_type} {$this->capacity_no} -> PO {$this->production_order_no}",
            'OVERHEAD' => "Overhead: Applied to PO {$this->production_order_no}",
            default => "{$this->item_ledger_entry_type}: {$this->item_no}",
        };
    }
}
