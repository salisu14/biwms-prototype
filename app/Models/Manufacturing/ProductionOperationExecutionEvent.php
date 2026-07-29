<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOperationExecutionEvent extends Model
{
    protected $fillable = [
        'production_operation_execution_id',
        'event_type',
        'from_status',
        'to_status',
        'occurred_at',
        'user_id',
        'employee_id',
        'idempotency_key',
        'metadata',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ProductionOperationExecution::class, 'production_operation_execution_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
