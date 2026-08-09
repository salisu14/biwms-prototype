<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Enums\ProductionOperationExecutionStatus;
use App\Models\Employee;
use App\Models\EmployeeShift;
use App\Models\Location;
use App\Models\ProductionJournalBatch;
use App\Models\ProductionJournalLine;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionOperationExecution extends Model
{
    protected $fillable = [
        'business_id',
        'production_order_id',
        'routing_line_id',
        'operation_no',
        'work_center_id',
        'machine_center_id',
        'operator_employee_id',
        'operator_user_id',
        'shift_id',
        'location_id',
        'status',
        'planned_quantity',
        'good_quantity',
        'scrap_quantity',
        'rework_quantity',
        'setup_seconds',
        'run_seconds',
        'labour_seconds',
        'machine_seconds',
        'downtime_seconds',
        'execution_date',
        'posting_date',
        'source_device',
        'idempotency_key',
        'created_by',
        'submitted_by',
        'submitted_at',
        'approved_by',
        'approved_at',
        'posted_by',
        'posted_at',
        'reversed_by',
        'reversed_at',
        'reason_code',
        'notes',
        'original_execution_id',
        'reversal_execution_id',
        'production_journal_batch_id',
    ];

    protected $casts = [
        'status' => ProductionOperationExecutionStatus::class,
        'planned_quantity' => 'decimal:8',
        'good_quantity' => 'decimal:8',
        'scrap_quantity' => 'decimal:8',
        'rework_quantity' => 'decimal:8',
        'setup_seconds' => 'integer',
        'run_seconds' => 'integer',
        'labour_seconds' => 'integer',
        'machine_seconds' => 'integer',
        'downtime_seconds' => 'integer',
        'execution_date' => 'date',
        'posting_date' => 'date',
        'submitted_at' => 'datetime',
        'approved_at' => 'datetime',
        'posted_at' => 'datetime',
        'reversed_at' => 'datetime',
    ];

    public function productionOrder(): BelongsTo
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }

    public function routingLine(): BelongsTo
    {
        return $this->belongsTo(ProductionOrderRoutingLine::class, 'routing_line_id');
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function machineCenter(): BelongsTo
    {
        return $this->belongsTo(MachineCenter::class, 'machine_center_id');
    }

    public function operatorEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'operator_employee_id');
    }

    public function operatorUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'operator_user_id');
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(EmployeeShift::class, 'shift_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function journalBatch(): BelongsTo
    {
        return $this->belongsTo(ProductionJournalBatch::class, 'production_journal_batch_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(ProductionOperationExecutionEvent::class, 'production_operation_execution_id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(ProductionOperatorAssignment::class, 'production_operation_execution_id');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(ProductionTimeEntry::class, 'production_operation_execution_id');
    }

    public function journalLines(): HasMany
    {
        return $this->hasMany(ProductionJournalLine::class, 'production_operation_execution_id');
    }

    public function scrapEntries(): HasMany
    {
        return $this->hasMany(ProductionScrapEntry::class, 'production_operation_execution_id');
    }

    public function downtimeEntries(): HasMany
    {
        return $this->hasMany(ProductionDowntimeEntry::class, 'production_operation_execution_id');
    }

    public function reworkEntries(): HasMany
    {
        return $this->hasMany(ProductionReworkEntry::class, 'production_operation_execution_id');
    }

    public function qualityChecks(): HasMany
    {
        return $this->hasMany(ProductionQualityCheck::class, 'production_operation_execution_id');
    }

    public function qualityHolds(): HasMany
    {
        return $this->hasMany(ProductionQualityHold::class, 'production_operation_execution_id');
    }

    public function activeQualityHolds(): HasMany
    {
        return $this->qualityHolds()->where('status', 'active');
    }

    public function downstreamDependencies(): HasMany
    {
        return $this->hasMany(ProductionOperationDependency::class, 'downstream_routing_line_id', 'routing_line_id');
    }
}
