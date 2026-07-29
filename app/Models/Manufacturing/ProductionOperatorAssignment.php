<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Enums\ProductionOperatorAssignmentStatus;
use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOperatorAssignment extends Model
{
    protected $fillable = [
        'production_operation_execution_id',
        'employee_id',
        'user_id',
        'status',
        'assigned_at',
        'accepted_at',
        'completed_at',
        'assigned_by',
        'notes',
    ];

    protected $casts = [
        'status' => ProductionOperatorAssignmentStatus::class,
        'assigned_at' => 'datetime',
        'accepted_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ProductionOperationExecution::class, 'production_operation_execution_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
