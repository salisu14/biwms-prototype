<?php

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use App\Models\Item;
use App\Models\Vendor;
use App\Models\SalesOrder;
use App\Models\User;
use App\Models\GlEntry;

class ProductionOrder extends Model
{
    use HasFactory;

    protected $table = 'production_orders';

    // Production Order Statuses
    const STATUS_SIMULATED = 'SIMULATED';
    const STATUS_PLANNED = 'PLANNED';
    const STATUS_FIRM_PLANNED = 'FIRM_PLANNED';
    const STATUS_RELEASED = 'RELEASED';
    const STATUS_FINISHED = 'FINISHED';

    // Source Types
    const SOURCE_TYPE_ITEM = 'ITEM';
    const SOURCE_TYPE_FAMILY = 'FAMILY';
    const SOURCE_TYPE_SALES_HEADER = 'SALES_HEADER';

    protected $fillable = [
        'document_number',
        'status',
        'source_type',
        'source_id', // Item ID, Family ID, or Sales Order ID
        'source_no',
        'description',

        // Item Information
        'item_id',
        'variant_code',
        'quantity',
        'unit_of_measure_code',
        'quantity_base',

        // Dates
        'due_date',
        'starting_date_time',
        'ending_date_time',

        // Posting Groups (from WMS Posting Groups Setup)
        'inventory_posting_group_id',
        'general_product_posting_group_id',

        // BOM and Routing
        'production_bom_id',
        'routing_id',
        'production_bom_version_id',
        'routing_version_id',

        // Location/Warehouse
        'location_code',
        'bin_code',

        // Dimension Codes
        'shortcut_dimension_1_code',
        'shortcut_dimension_2_code',
        'dimension_set_id',

        // Costing
        'costing_method', // STANDARD, FIFO, LIFO, AVERAGE, SPECIFIC
        'unit_cost',
        'cost_rollup',

        // Flushing Method (from item/routing)
        'flushing_method', // MANUAL, FORWARD, BACKWARD, PICK + BACKWARD, PICK + FORWARD

        // Scrap
        'scrap_percent',

        // Planning
        'planning_level',
        'priority',

        // Posted Status
        'posted',
        'posted_at',
        'posted_by',

        // Finished Status
        'finished_at',
        'finished_by',

        // User Tracking
        'created_by',
        'last_modified_by',

        // Reservation
        'reserved_from_stock',
    ];

    protected $casts = [
        'status' => 'string',
        'source_type' => 'string',
        'quantity' => 'decimal:4',
        'quantity_base' => 'decimal:4',
        'due_date' => 'date',
        'starting_date_time' => 'datetime',
        'ending_date_time' => 'datetime',
        'unit_cost' => 'decimal:4',
        'cost_rollup' => 'decimal:4',
        'scrap_percent' => 'decimal:2',
        'posted' => 'boolean',
        'posted_at' => 'datetime',
        'finished_at' => 'datetime',
        'reserved_from_stock' => 'boolean',
    ];

    // ==================== RELATIONSHIPS ====================

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function productionBom(): BelongsTo
    {
        return $this->belongsTo(ProductionBom::class, 'production_bom_id');
    }

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProductionOrderLine::class, 'production_order_id');
    }

    public function components(): HasMany
    {
        return $this->hasMany(ProductionOrderComponent::class, 'production_order_id');
    }

    public function routingLines(): HasMany
    {
        return $this->hasMany(ProductionOrderRoutingLine::class, 'production_order_id');
    }

    public function capacityLedgerEntries(): HasMany
    {
        return $this->hasMany(CapacityLedgerEntry::class, 'production_order_id');
    }

    public function itemLedgerEntries(): MorphMany
    {
        return $this->morphMany(ItemLedgerEntry::class, 'source', 'source_type', 'source_id');
    }

    public function glEntries(): MorphMany
    {
        return $this->morphMany(GlEntry::class, 'source', 'source_type', 'source_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    public function finisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finished_by');
    }

    // ==================== SCOPES ====================

    public function scopeForStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopeSimulated($query)
    {
        return $query->where('status', self::STATUS_SIMULATED);
    }

    public function scopePlanned($query)
    {
        return $query->where('status', self::STATUS_PLANNED);
    }

    public function scopeFirmPlanned($query)
    {
        return $query->where('status', self::STATUS_FIRM_PLANNED);
    }

    public function scopeReleased($query)
    {
        return $query->where('status', self::STATUS_RELEASED);
    }

    public function scopeFinished($query)
    {
        return $query->where('status', self::STATUS_FINISHED);
    }

    public function scopeActive($query)
    {
        return $query->whereIn('status', [
            self::STATUS_FIRM_PLANNED,
            self::STATUS_RELEASED,
        ]);
    }

    public function scopeForItem($query, int $itemId)
    {
        return $query->where('item_id', $itemId);
    }

    public function scopeDueBefore($query, $date)
    {
        return $query->where('due_date', '<=', $date);
    }

    // ==================== CALCULATED ATTRIBUTES ====================

    public function getRemainingQuantityAttribute(): float
    {
        $produced = $this->itemLedgerEntries()
            ->where('entry_type', ItemLedgerEntry::ENTRY_TYPE_OUTPUT)
            ->sum('quantity');

        return $this->quantity - $produced;
    }

    public function getIsCompletedAttribute(): bool
    {
        return $this->remaining_quantity <= 0;
    }

    public function getTotalActualCostAttribute(): float
    {
        return $this->capacityLedgerEntries()->sum('total_cost') +
            $this->itemLedgerEntries()
                ->where('entry_type', ItemLedgerEntry::ENTRY_TYPE_CONSUMPTION)
                ->sum('cost_amount_actual');
    }

    public function getCostVarianceAttribute(): float
    {
        if (!$this->cost_rollup) return 0;
        return $this->total_actual_cost - ($this->cost_rollup * $this->quantity);
    }

    // ==================== STATUS TRANSITIONS ====================

    /**
     * Change status with validation
     */
    public function changeStatus(string $newStatus, ?int $userId = null): void
    {
        $validTransitions = [
            self::STATUS_SIMULATED => [self::STATUS_PLANNED, self::STATUS_FIRM_PLANNED],
            self::STATUS_PLANNED => [self::STATUS_FIRM_PLANNED, self::STATUS_FINISHED],
            self::STATUS_FIRM_PLANNED => [self::STATUS_RELEASED, self::STATUS_FINISHED],
            self::STATUS_RELEASED => [self::STATUS_FINISHED],
        ];

        if (!in_array($newStatus, $validTransitions[$this->status] ?? [])) {
            throw new \Exception("Invalid status transition from {$this->status} to {$newStatus}");
        }

        // Special validations
        if ($newStatus === self::STATUS_RELEASED) {
            $this->validateForRelease();
        }

        if ($newStatus === self::STATUS_FINISHED) {
            $this->validateForFinish();
        }

        $oldStatus = $this->status;
        $this->status = $newStatus;

        if ($newStatus === self::STATUS_FINISHED) {
            $this->finished_at = now();
            $this->finished_by = $userId;
        }

        $this->save();

        // Fire status changed event
        event(new ProductionOrderStatusChanged($this, $oldStatus, $newStatus));
    }

    protected function validateForRelease(): void
    {
        if ($this->components()->count() === 0) {
            throw new \Exception('Production order must have components before release');
        }
    }

    protected function validateForFinish(): void
    {
        if ($this->status !== self::STATUS_RELEASED) {
            throw new \Exception('Only released production orders can be finished');
        }
    }

    // ==================== BUSINESS METHODS ====================

    /**
     * Refresh production order - recalculate components and routing
     */
    public function refreshOrder(bool $calculateLines = true, bool $calculateRoutings = true, bool $calculateComponents = true): void
    {
        if ($this->status === self::STATUS_FINISHED) {
            throw new \Exception('Cannot refresh finished production order');
        }

        \DB::transaction(function () use ($calculateLines, $calculateRoutings, $calculateComponents) {
            if ($calculateLines) {
                $this->refreshLines();
            }

            if ($calculateRoutings) {
                $this->refreshRouting();
            }

            if ($calculateComponents) {
                $this->refreshComponents();
            }

            // Recalculate dates
            $this->scheduleOrder();
        });
    }

    /**
     * Create or update production order lines
     */
    protected function refreshLines(): void
    {
        // Delete existing lines if no ledger entries exist
        if ($this->itemLedgerEntries()->count() === 0) {
            $this->lines()->delete();
        }

        // Create main line
        $this->lines()->create([
            'line_number' => 10000,
            'item_id' => $this->item_id,
            'description' => $this->description,
            'quantity' => $this->quantity,
            'unit_of_measure_code' => $this->unit_of_measure_code,
            'quantity_base' => $this->quantity_base,
            'due_date' => $this->due_date,
            'production_bom_id' => $this->production_bom_id,
            'routing_id' => $this->routing_id,
        ]);
    }

    /**
     * Explode BOM and create components
     */
    protected function refreshComponents(): void
    {
        if (!$this->production_bom_id) return;

        // Clear existing components if not started
        if ($this->itemLedgerEntries()->where('entry_type', ItemLedgerEntry::ENTRY_TYPE_CONSUMPTION)->count() === 0) {
            $this->components()->delete();
        }

        $bom = $this->productionBom;
        if (!$bom) return;

        $lineNo = 10000;
        foreach ($bom->lines as $bomLine) {
            $expectedQty = $bomLine->quantity_per * $this->quantity * (1 + $bomLine->scrap_percent / 100);

            $this->components()->create([
                'line_number' => $lineNo,
                'item_id' => $bomLine->item_id,
                'description' => $bomLine->description,
                'unit_of_measure_code' => $bomLine->unit_of_measure_code,
                'quantity_per' => $bomLine->quantity_per,
                'expected_quantity' => $expectedQty,
                'expected_quantity_base' => $expectedQty * $bomLine->item->qty_per_unit_of_measure,
                'scrap_percent' => $bomLine->scrap_percent,
                'routing_link_code' => $bomLine->routing_link_code,
                'flushing_method' => $bomLine->flushing_method ?? $this->flushing_method,
                'location_code' => $bomLine->location_code ?? $this->location_code,
                'bin_code' => $bomLine->bin_code,
                'due_date' => $this->starting_date_time?->copy()->subDays($bomLine->lead_time_offset_days ?? 0),
            ]);

            $lineNo += 10000;
        }
    }

    /**
     * Create routing lines from routing
     */
    protected function refreshRouting(): void
    {
        if (!$this->routing_id) return;

        if ($this->capacityLedgerEntries()->count() === 0) {
            $this->routingLines()->delete();
        }

        $routing = $this->routing;
        if (!$routing) return;

        $lineNo = 10000;
        foreach ($routing->lines as $routingLine) {
            $this->routingLines()->create([
                'line_number' => $lineNo,
                'operation_no' => $routingLine->operation_no,
                'description' => $routingLine->description,
                'work_center_id' => $routingLine->work_center_id,
                'machine_center_id' => $routingLine->machine_center_id,
                'setup_time' => $routingLine->setup_time,
                'run_time' => $routingLine->run_time * $this->quantity,
                'wait_time' => $routingLine->wait_time,
                'move_time' => $routingLine->move_time,
                'setup_time_unit' => $routingLine->setup_time_unit,
                'run_time_unit' => $routingLine->run_time_unit,
                'routing_link_code' => $routingLine->routing_link_code,
                'scrap_factor_percent' => $routingLine->scrap_factor_percent,
            ]);

            $lineNo += 10000;
        }
    }

    /**
     * Schedule the production order (Forward or Backward)
     */
    protected function scheduleOrder(bool $forward = false): void
    {
        if ($forward) {
            // Forward scheduling: Start from starting_date_time
            $currentDate = $this->starting_date_time ?? now();

            foreach ($this->routingLines()->orderBy('line_number')->get() as $routingLine) {
                $routingLine->starting_date_time = $currentDate;
                $routingLine->ending_date_time = $currentDate->copy()->addMinutes(
                    $routingLine->total_time_minutes
                );
                $routingLine->save();

                $currentDate = $routingLine->ending_date_time->copy()->addMinutes($routingLine->move_time);
            }

            $this->ending_date_time = $currentDate;
        } else {
            // Backward scheduling: End before due_date
            $currentDate = $this->due_date?->copy()->subDay() ?? now();

            foreach ($this->routingLines()->orderByDesc('line_number')->get() as $routingLine) {
                $routingLine->ending_date_time = $currentDate;
                $routingLine->starting_date_time = $currentDate->copy()->subMinutes(
                    $routingLine->total_time_minutes
                );
                $routingLine->save();

                $currentDate = $routingLine->starting_date_time->copy()->subMinutes($routingLine->wait_time);
            }

            $this->starting_date_time = $currentDate;
        }

        $this->save();
    }

    /**
     * Post consumption of components
     */
    public function postConsumption(array $consumptions, int $userId, ?\DateTime $postingDate = null): void
    {
        // $consumptions = [['component_id' => 1, 'quantity' => 5.0, 'scrap_quantity' => 0.5], ...]

        $postingDate = $postingDate ?? now();

        \DB::transaction(function () use ($consumptions, $userId, $postingDate) {
            foreach ($consumptions as $consumption) {
                $component = $this->components()->find($consumption['component_id']);
                if (!$component) continue;

                $qty = $consumption['quantity'];
                $scrapQty = $consumption['scrap_quantity'] ?? 0;

                // Create item ledger entry for consumption
                ItemLedgerEntry::create([
                    'entry_type' => ItemLedgerEntry::ENTRY_TYPE_CONSUMPTION,
                    'item_id' => $component->item_id,
                    'quantity' => -$qty,
                    'remaining_quantity' => 0,
                    'open' => false,
                    'posting_date' => $postingDate,
                    'document_number' => $this->document_number,
                    'external_document_number' => $component->line_number,
                    'source_id' => $this->id,
                    'source_type' => self::class,
                    'location_code' => $component->location_code,
                    'unit_cost' => $component->item->unit_cost,
                    'cost_amount_actual' => $qty * $component->item->unit_cost,
                ]);

                // Update component actual consumption
                $component->actual_quantity_consumed += $qty;
                $component->actual_scrap_quantity += $scrapQty;
                $component->save();
            }

            // Create WIP G/L entries
            $this->createWipGlEntries($postingDate);
        });
    }

    /**
     * Post output (finished goods)
     */
    public function postOutput(float $quantity, int $userId, ?\DateTime $postingDate = null, ?int $routingLineId = null): void
    {
        $postingDate = $postingDate ?? now();

        \DB::transaction(function () use ($quantity, $userId, $postingDate, $routingLineId) {
            // Create item ledger entry for output
            ItemLedgerEntry::create([
                'entry_type' => ItemLedgerEntry::ENTRY_TYPE_OUTPUT,
                'item_id' => $this->item_id,
                'quantity' => $quantity,
                'remaining_quantity' => $quantity,
                'open' => true,
                'posting_date' => $postingDate,
                'document_number' => $this->document_number,
                'source_id' => $this->id,
                'source_type' => self::class,
                'location_code' => $this->location_code,
                'unit_cost' => 0, // Will be calculated at finish
            ]);

            // If routing line specified, update operation output
            if ($routingLineId) {
                $routingLine = $this->routingLines()->find($routingLineId);
                if ($routingLine) {
                    $routingLine->actual_output_quantity += $quantity;
                    $routingLine->save();
                }
            }
        });
    }

    /**
     * Post capacity (time/cost)
     */
    public function postCapacity(int $routingLineId, float $setupTime, float $runTime, float $cost, int $userId): void
    {
        $routingLine = $this->routingLines()->find($routingLineId);
        if (!$routingLine) return;

        CapacityLedgerEntry::create([
            'production_order_id' => $this->id,
            'routing_line_id' => $routingLineId,
            'work_center_id' => $routingLine->work_center_id,
            'machine_center_id' => $routingLine->machine_center_id,
            'posting_date' => now(),
            'setup_time' => $setupTime,
            'run_time' => $runTime,
            'setup_time_unit' => $routingLine->setup_time_unit,
            'run_time_unit' => $routingLine->run_time_unit,
            'direct_cost' => $cost,
            'overhead_cost' => $cost * 0.25, // Example overhead calculation
            'total_cost' => $cost * 1.25,
            'document_number' => $this->document_number,
        ]);
    }

    /**
     * Finish production order - calculate costs and post to inventory
     */
    public function finish(int $userId, ?\DateTime $postingDate = null): void
    {
        $postingDate = $postingDate ?? now();

        \DB::transaction(function () use ($userId, $postingDate) {
            // 1. Flush remaining components if backward flushing
            if ($this->flushing_method === 'BACKWARD') {
                $this->backwardFlushComponents($postingDate);
            }

            // 2. Calculate actual unit cost
            $totalCost = $this->total_actual_cost;
            $totalOutput = $this->itemLedgerEntries()
                ->where('entry_type', ItemLedgerEntry::ENTRY_TYPE_OUTPUT)
                ->sum('quantity');

            $unitCost = $totalOutput > 0 ? $totalCost / $totalOutput : 0;

            // 3. Update output entries with calculated cost
            $this->itemLedgerEntries()
                ->where('entry_type', ItemLedgerEntry::ENTRY_TYPE_OUTPUT)
                ->update([
                    'unit_cost' => $unitCost,
                    'cost_amount_actual' => \DB::raw("quantity * {$unitCost}"),
                ]);

            // 4. Create G/L entries for WIP to Finished Goods
            $this->createFinishGlEntries($totalCost, $postingDate);

            // 5. Change status
            $this->changeStatus(self::STATUS_FINISHED, $userId);

            // 6. Update item unit cost if needed
            if ($this->costing_method === 'STANDARD') {
                // Calculate variance
                $variance = $totalCost - ($this->unit_cost * $totalOutput);
                if (abs($variance) > 0.01) {
                    $this->createVarianceGlEntries($variance, $postingDate);
                }
            }
        });
    }

    /**
     * Backward flush components automatically
     */
    protected function backwardFlushComponents(\DateTime $postingDate): void
    {
        foreach ($this->components as $component) {
            if ($component->flushing_method !== 'BACKWARD') continue;

            $expectedQty = $component->expected_quantity;
            $consumedQty = $component->actual_quantity_consumed;
            $remainingQty = $expectedQty - $consumedQty;

            if ($remainingQty <= 0) continue;

            $this->postConsumption([[
                'component_id' => $component->id,
                'quantity' => $remainingQty,
                'scrap_quantity' => 0,
            ]], $this->finished_by ?? 1, $postingDate);
        }
    }

    protected function createWipGlEntries(\DateTime $postingDate): void
    {
        // Debit WIP Inventory, Credit Raw Materials Inventory
        // Implementation depends on your G/L account structure
    }

    protected function createFinishGlEntries(float $totalCost, \DateTime $postingDate): void
    {
        // Debit Finished Goods Inventory, Credit WIP Inventory
        // Implementation depends on your G/L account structure
    }

    protected function createVarianceGlEntries(float $variance, \DateTime $postingDate): void
    {
        // Debit/Credit Variance accounts based on favorable/unfavorable variance
    }

    // ==================== STATIC METHODS ====================

    public static function generateDocumentNumber(): string
    {
        $prefix = 'PROD';
        $year = date('Y');
        $count = self::whereYear('created_at', $year)->count() + 1;
        return sprintf('%s-%d-%06d', $prefix, $year, $count);
    }
}
