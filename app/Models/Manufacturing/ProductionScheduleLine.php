<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionScheduleLine extends Model
{
    protected $fillable = [
        'production_schedule_id',
        'production_order_id',
        'production_hierarchy_id',
        'root_production_order_id',
        'line_number',
        'priority',
        'due_date',
        'scheduled_start_at',
        'scheduled_finish_at',
        'quantity_base',
        'status',
        'late',
        'lateness_minutes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'priority' => 'integer',
            'due_date' => 'date',
            'scheduled_start_at' => 'datetime',
            'scheduled_finish_at' => 'datetime',
            'quantity_base' => 'decimal:8',
            'late' => 'boolean',
            'lateness_minutes' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ProductionSchedule::class, 'production_schedule_id');
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function productionHierarchy(): BelongsTo
    {
        return $this->belongsTo(ProductionHierarchy::class, 'production_hierarchy_id');
    }

    public function operationSchedules(): HasMany
    {
        return $this->hasMany(ProductionOperationSchedule::class, 'production_schedule_line_id');
    }
}
