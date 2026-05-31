<?php

namespace App\Models;

use App\Models\Manufacturing\MachineCenter;
use App\Models\Manufacturing\ProductionOrder;
use App\Models\Manufacturing\WorkCenter;
use App\Services\Inventory\ValueEntryAccountingService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ValueEntry extends Model
{
    use HasFactory;

    protected $table = 'value_entries';

    private const FILLABLE_ENTRY_KEYS = [
        'entry_no',
        'item_ledger_entry_no',
        'item_ledger_entry_type',
    ];

    private const FILLABLE_SOURCE_KEYS = [
        'source_type',
        'source_no',
        'source_line_no',
        'source_batch_name',
    ];

    private const FILLABLE_ITEM_KEYS = [
        'item_no',
        'variant_code',
        'location_code',
        'bin_code',
        'serial_no',
        'lot_no',
        'expiration_date',
    ];

    private const FILLABLE_DOCUMENT_KEYS = [
        'posting_date',
        'valuation_date',
        'document_type',
        'document_no',
        'document_line_no',
        'description',
    ];

    private const FILLABLE_COST_KEYS = [
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
    ];

    private const FILLABLE_REFERENCE_KEYS = [
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
    ];

    private const FILLABLE_GL_KEYS = [
        'gl_posted',
        'gl_posting_date',
        'gl_entry_no',
        'gl_account_no',
        'balancing_account_no',
    ];

    private const FILLABLE_ADJUSTMENT_KEYS = [
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

    protected $fillable = [
        ...self::FILLABLE_ENTRY_KEYS,
        ...self::FILLABLE_SOURCE_KEYS,
        ...self::FILLABLE_ITEM_KEYS,
        ...self::FILLABLE_DOCUMENT_KEYS,
        ...self::FILLABLE_COST_KEYS,
        ...self::FILLABLE_REFERENCE_KEYS,
        ...self::FILLABLE_GL_KEYS,
        ...self::FILLABLE_ADJUSTMENT_KEYS,
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
        return app(ValueEntryAccountingService::class)->determineGLAccount($this);
    }

    /**
     * Determine balancing account for double-entry posting
     */
    public function determineBalancingAccount(): ?string
    {
        return app(ValueEntryAccountingService::class)->determineBalancingAccount($this);
    }

    /**
     * Post this value entry to General Ledger
     */
    public function postToGL(): GLEntry
    {
        return app(ValueEntryAccountingService::class)->postToGL($this);
    }

    /**
     * Reverse this value entry (for corrections)
     */
    public function reverse($postingDate = null): self
    {
        return app(ValueEntryAccountingService::class)->reverse($this, $postingDate);
    }

    /**
     * Adjust cost (for standard cost variances or revaluation)
     */
    public function adjustCost(float $newCostAmount, string $reason = ''): self
    {
        return app(ValueEntryAccountingService::class)->adjustCost($this, $newCostAmount, $reason);
    }
}
