<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionTimeEntry extends Model
{
    protected $fillable = [
        'production_operation_execution_id',
        'employee_id',
        'machine_center_id',
        'time_type',
        'started_at',
        'ended_at',
        'duration_seconds',
        'manual',
        'exclusive_machine',
        'created_by',
        'idempotency_key',
        'reason',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'duration_seconds' => 'integer',
        'manual' => 'boolean',
        'exclusive_machine' => 'boolean',
    ];

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ProductionOperationExecution::class, 'production_operation_execution_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function machineCenter(): BelongsTo
    {
        return $this->belongsTo(MachineCenter::class, 'machine_center_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
