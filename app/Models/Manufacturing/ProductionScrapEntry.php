<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Enums\ProductionScrapPostingTreatment;
use App\Enums\ProductionScrapStage;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionScrapEntry extends Model
{
    protected $fillable = [
        'production_operation_execution_id',
        'production_scrap_reason_id',
        'stage',
        'posting_treatment',
        'item_id',
        'quantity',
        'unit_of_measure_code',
        'requires_approval',
        'approved_by',
        'approved_at',
        'notes',
        'idempotency_key',
    ];

    protected $casts = [
        'stage' => ProductionScrapStage::class,
        'posting_treatment' => ProductionScrapPostingTreatment::class,
        'quantity' => 'decimal:8',
        'requires_approval' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ProductionOperationExecution::class, 'production_operation_execution_id');
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ProductionScrapReason::class, 'production_scrap_reason_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
