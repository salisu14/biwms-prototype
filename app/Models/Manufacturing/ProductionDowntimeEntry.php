<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Enums\ProductionDowntimeCategory;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionDowntimeEntry extends Model
{
    protected $fillable = [
        'production_operation_execution_id',
        'production_downtime_reason_id',
        'category',
        'started_at',
        'ended_at',
        'duration_seconds',
        'planned',
        'requires_approval',
        'approved_by',
        'approved_at',
        'notes',
        'idempotency_key',
    ];

    protected $casts = [
        'category' => ProductionDowntimeCategory::class,
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_seconds' => 'integer',
        'planned' => 'boolean',
        'requires_approval' => 'boolean',
        'approved_at' => 'datetime',
    ];

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ProductionOperationExecution::class, 'production_operation_execution_id');
    }

    public function reason(): BelongsTo
    {
        return $this->belongsTo(ProductionDowntimeReason::class, 'production_downtime_reason_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
