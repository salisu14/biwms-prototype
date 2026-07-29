<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Employee;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionShiftHandover extends Model
{
    protected $fillable = [
        'production_operation_execution_id',
        'from_employee_id',
        'to_employee_id',
        'handed_over_at',
        'summary',
        'open_items',
        'created_by',
    ];

    protected $casts = [
        'handed_over_at' => 'datetime',
        'open_items' => 'array',
    ];

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ProductionOperationExecution::class, 'production_operation_execution_id');
    }

    public function fromEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'from_employee_id');
    }

    public function toEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'to_employee_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
