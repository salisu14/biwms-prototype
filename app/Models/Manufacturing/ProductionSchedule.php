<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Enums\ProductionScheduleStatus;
use App\Enums\ProductionSchedulingMode;
use App\Models\Business;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ProductionSchedule extends Model
{
    protected $fillable = [
        'business_id',
        'location_id',
        'schedule_no',
        'name',
        'horizon_start_at',
        'horizon_end_at',
        'status',
        'scheduling_mode',
        'planning_version',
        'freeze_horizon_minutes',
        'supersedes_schedule_id',
        'superseded_by_schedule_id',
        'generated_by',
        'generated_at',
        'reviewed_by',
        'reviewed_at',
        'approved_by',
        'approved_at',
        'notes',
        'summary',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'horizon_start_at' => 'datetime',
            'horizon_end_at' => 'datetime',
            'status' => ProductionScheduleStatus::class,
            'scheduling_mode' => ProductionSchedulingMode::class,
            'planning_version' => 'integer',
            'freeze_horizon_minutes' => 'integer',
            'generated_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'approved_at' => 'datetime',
            'summary' => 'array',
            'metadata' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProductionScheduleLine::class);
    }

    public function operationSchedules(): HasMany
    {
        return $this->hasMany(ProductionOperationSchedule::class);
    }

    public function exceptions(): HasMany
    {
        return $this->hasMany(ProductionSchedulingException::class);
    }

    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}
