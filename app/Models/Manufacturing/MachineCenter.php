<?php

namespace App\Models\Manufacturing;

use App\Models\Location;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MachineCenter extends Model
{
    use HasFactory;

    protected $table = 'machine_centers';

    protected $fillable = [
        'code',
        'name',
        'work_center_id',

        // Capacity
        'capacity',
        'efficiency',

        // Costs
        'direct_unit_cost',
        'indirect_cost_percent',
        'overhead_rate',

        // Setup
        'setup_time',
        'wait_time',
        'move_time',

        // Location
        'location_code',
    ];

    protected $casts = [
        'capacity' => 'decimal:4',
        'efficiency' => 'decimal:2',
        'direct_unit_cost' => 'decimal:4',
        'indirect_cost_percent' => 'decimal:2',
        'overhead_rate' => 'decimal:4',
        'setup_time' => 'decimal:4',
        'wait_time' => 'decimal:4',
        'move_time' => 'decimal:4',
    ];

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class, 'work_center_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_code', 'code');
    }
}
