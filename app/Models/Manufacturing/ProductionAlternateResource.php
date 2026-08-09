<?php

declare(strict_types=1);

namespace App\Models\Manufacturing;

use App\Models\Business;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductionAlternateResource extends Model
{
    protected $fillable = [
        'business_id',
        'primary_work_center_id',
        'primary_machine_center_id',
        'alternate_work_center_id',
        'alternate_machine_center_id',
        'priority',
        'efficiency_factor',
        'effective_from',
        'effective_to',
        'is_active',
        'reason',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'efficiency_factor' => 'decimal:4',
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
            'metadata' => 'array',
        ];
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function primaryWorkCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'primary_work_center_id');
    }

    public function primaryMachineCenter(): BelongsTo
    {
        return $this->belongsTo(MachineCenter::class, 'primary_machine_center_id');
    }

    public function alternateWorkCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'alternate_work_center_id');
    }

    public function alternateMachineCenter(): BelongsTo
    {
        return $this->belongsTo(MachineCenter::class, 'alternate_machine_center_id');
    }
}
