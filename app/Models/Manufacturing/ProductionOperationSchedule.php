<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Enums\ProductionOperationScheduleStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionOperationSchedule extends Model
{
    protected $fillable = [
        'production_schedule_id',
        'production_schedule_line_id',
        'production_order_id',
        'production_order_routing_line_id',
        'production_hierarchy_id',
        'root_production_order_id',
        'work_center_id',
        'machine_center_id',
        'predecessor_operation_schedule_id',
        'production_operation_dependency_id',
        'scheduled_start_at',
        'scheduled_finish_at',
        'setup_duration_minutes',
        'run_duration_minutes',
        'wait_duration_minutes',
        'queue_duration_minutes',
        'quantity_base',
        'capacity_required_minutes',
        'sequence',
        'priority',
        'status',
        'planning_source',
        'uses_alternate_resource',
        'frozen',
        'late',
        'lateness_minutes',
        'exception_state',
        'idempotency_key',
        'assignment_reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'scheduled_start_at' => 'datetime',
            'scheduled_finish_at' => 'datetime',
            'setup_duration_minutes' => 'decimal:4',
            'run_duration_minutes' => 'decimal:4',
            'wait_duration_minutes' => 'decimal:4',
            'queue_duration_minutes' => 'decimal:4',
            'quantity_base' => 'decimal:8',
            'capacity_required_minutes' => 'decimal:4',
            'sequence' => 'integer',
            'priority' => 'integer',
            'status' => ProductionOperationScheduleStatus::class,
            'uses_alternate_resource' => 'boolean',
            'frozen' => 'boolean',
            'late' => 'boolean',
            'lateness_minutes' => 'integer',
            'assignment_reason' => 'array',
            'metadata' => 'array',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ProductionSchedule::class, 'production_schedule_id');
    }

    public function scheduleLine(): BelongsTo
    {
        return $this->belongsTo(ProductionScheduleLine::class, 'production_schedule_line_id');
    }

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function routingLine(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderRoutingLine::class, 'production_order_routing_line_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function machineCenter(): BelongsTo
    {
        return $this->belongsTo(MachineCenter::class, 'machine_center_id');
    }

    public function dependency(): BelongsTo
    {
        return $this->belongsTo(ProductionOperationDependency::class, 'production_operation_dependency_id');
    }
}
