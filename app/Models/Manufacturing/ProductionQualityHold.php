<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionQualityHold extends Model
{
    protected $fillable = [
        'production_operation_execution_id',
        'status',
        'reason',
        'placed_by',
        'placed_at',
        'released_by',
        'released_at',
        'release_reason',
    ];

    protected $casts = [
        'placed_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ProductionOperationExecution::class, 'production_operation_execution_id');
    }

    public function placer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'placed_by');
    }

    public function releaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
