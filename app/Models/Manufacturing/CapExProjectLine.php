<?php

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class CapExProjectLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'capex_project_id',
        'line_number',
        'line_type',
        'description',
        'budget_amount',
        'committed_amount',
        'actual_amount',
        'variance_amount',
        'source_document_type',
        'source_document_id',
        'source_document_no',
        'source_document_date',
        'production_order_id',
        'production_order_component_id',
        'capacity_ledger_entry_id',
        'vendor_id',
        'purchase_order_number',
        'eligible_for_capitalization',
        'non_capitalization_reason',
        'capitalized',
        'capitalized_at',
        'capitalized_by',
        'gl_entry_reference',
        'status',
    ];

    protected $casts = [
        'source_document_date' => 'date',
        'capitalized_at' => 'datetime',
        'budget_amount' => 'decimal:2',
        'committed_amount' => 'decimal:2',
        'actual_amount' => 'decimal:2',
        'variance_amount' => 'decimal:2',
        'eligible_for_capitalization' => 'boolean',
        'capitalized' => 'boolean',
    ];

    // Relationships

    public function project(): BelongsTo
    {
        return $this->belongsTo(CapExProject::class, 'capex_project_id');
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class);
    }

    public function productionOrderComponent(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderComponent::class);
    }

    public function capacityLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(CapacityLedgerEntry::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Vendor::class);
    }

    public function capitalizedByUser(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'capitalized_by');
    }

    // Scopes

    public function scopeEligible($query)
    {
        return $query->where('eligible_for_capitalization', true);
    }

    public function scopeUncapitalized($query)
    {
        return $query->where('capitalized', false);
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('line_type', $type);
    }

    public function scopeFromProduction($query)
    {
        return $query->whereNotNull('production_order_id');
    }

    public function scopeReadyToCapitalize($query)
    {
        return $query->where('eligible_for_capitalization', true)
            ->where('capitalized', false)
            ->where('status', 'INVOICED');
    }

    // Business Logic

    /**
     * Calculate variance from budget
     */
    public function calculateVariance(): float
    {
        return $this->actual_amount - $this->budget_amount;
    }

    /**
     * Mark as committed (PO placed)
     */
    public function commit(float $amount): void
    {
        $this->update([
            'committed_amount' => $amount,
            'status' => 'COMMITTED',
        ]);

        $this->project->increment('committed_amount', $amount);
    }

    /**
     * Record actual cost received/invoiced
     */
    public function recordActual(float $amount, ?string $documentNo = null, ?\DateTime $documentDate = null): void
    {
        $this->update([
            'actual_amount' => $amount,
            'variance_amount' => $amount - $this->budget_amount,
            'source_document_no' => $documentNo ?? $this->source_document_no,
            'source_document_date' => $documentDate ?? now(),
            'status' => 'RECEIVED',
        ]);

        $this->project->recalculateActualAmount();
    }

    /**
     * Capitalize this line
     */
    public function capitalize(int $userId): void
    {
        if (!$this->eligible_for_capitalization) {
            throw new \Exception('Line is not eligible for capitalization');
        }

        if ($this->capitalized) {
            throw new \Exception('Line is already capitalized');
        }

        $this->update([
            'capitalized' => true,
            'capitalized_at' => now(),
            'capitalized_by' => $userId,
            'status' => 'CAPITALIZED',
        ]);

        $this->project->increment('capitalized_amount', $this->actual_amount);
    }

    /**
     * Expense this line (move to OpEx instead of CapEx)
     */
    public function expense(int $userId, string $reason): void
    {
        $this->update([
            'eligible_for_capitalization' => false,
            'non_capitalization_reason' => $reason,
            'capitalized' => true, // Mark as processed
            'capitalized_at' => now(),
            'capitalized_by' => $userId,
            'status' => 'EXPENSED',
        ]);
    }

    /**
     * Get the source document instance (polymorphic)
     */
    public function getSourceDocument(): ?Model
    {
        return match($this->source_document_type) {
            'PRODUCTION_ORDER' => ProductionOrder::find($this->source_document_id),
            'PURCHASE_ORDER' => \App\Models\PurchaseOrder::find($this->source_document_id),
            'VENDOR_INVOICE' => \App\Models\VendorInvoice::find($this->source_document_id),
            default => null,
        };
    }

    /**
     * Check if line is from production order integration
     */
    public function isFromProduction(): bool
    {
        return $this->production_order_id !== null;
    }

    /**
     * Get detailed cost breakdown for production-originated lines
     */
    public function getProductionCostDetails(): ?array
    {
        if (!$this->isFromProduction()) {
            return null;
        }

        $details = [
            'production_order' => $this->productionOrder?->document_number,
        ];

        if ($this->productionOrderComponent) {
            $details['component'] = [
                'item' => $this->productionOrderComponent->item?->description,
                'quantity' => $this->productionOrderComponent->actual_quantity_consumed,
                'unit_cost' => $this->productionOrderComponent->unit_cost,
            ];
        }

        if ($this->capacityLedgerEntry) {
            $details['capacity'] = [
                'work_center' => $this->capacityLedgerEntry->workCenter?->name,
                'setup_time' => $this->capacityLedgerEntry->setup_time,
                'run_time' => $this->capacityLedgerEntry->run_time,
                'direct_cost' => $this->capacityLedgerEntry->direct_cost,
                'overhead_cost' => $this->capacityLedgerEntry->overhead_cost,
            ];
        }

        return $details;
    }
}
