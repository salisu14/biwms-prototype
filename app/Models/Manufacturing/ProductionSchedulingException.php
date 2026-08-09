<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Enums\ProductionSchedulingExceptionSeverity;
use App\Enums\ProductionSchedulingExceptionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionSchedulingException extends Model
{
    protected $fillable = [
        'production_schedule_id',
        'production_operation_schedule_id',
        'production_order_id',
        'production_order_routing_line_id',
        'work_center_id',
        'machine_center_id',
        'exception_type',
        'severity',
        'status',
        'message',
        'suggested_action',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'exception_type' => ProductionSchedulingExceptionType::class,
            'severity' => ProductionSchedulingExceptionSeverity::class,
            'metadata' => 'array',
        ];
    }

    public function schedule(): BelongsTo
    {
        return $this->belongsTo(ProductionSchedule::class, 'production_schedule_id');
    }

    public function operationSchedule(): BelongsTo
    {
        return $this->belongsTo(ProductionOperationSchedule::class, 'production_operation_schedule_id');
    }
}
