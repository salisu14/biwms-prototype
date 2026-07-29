<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Enums\ProductionReworkStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionReworkEntry extends Model
{
    protected $fillable = [
        'production_operation_execution_id',
        'status',
        'quantity',
        'unit_of_measure_code',
        'approved_by',
        'approved_at',
        'completed_at',
        'reason',
        'notes',
        'idempotency_key',
    ];

    protected $casts = [
        'status' => ProductionReworkStatus::class,
        'quantity' => 'decimal:8',
        'approved_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ProductionOperationExecution::class, 'production_operation_execution_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
