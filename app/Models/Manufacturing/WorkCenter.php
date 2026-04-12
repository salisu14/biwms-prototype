<?php

namespace App\Models\Manufacturing;

use App\Models\Vendor;
use App\Models\WorkCenterBin;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkCenter extends Model
{
    use HasFactory;

    protected $table = 'work_centers';

    protected $fillable = [
        'code',
        'name',
        'work_center_group_id',

        // Capacity
        'unit_of_measure_code', // MINUTES, HOURS
        'capacity', // Available per period
        'efficiency', // Percentage
        'maximum_efficiency',
        'minimum_efficiency',

        // Costs
        'direct_unit_cost',
        'indirect_cost_percent',
        'overhead_rate',

        // Scheduling
        'queue_time',
        'queue_time_unit',

        // Location
        'location_code',

        // Posting
        'work_center_account_no', // G/L Account for WIP
        'subcontractor_id', // If outsourced work center
    ];

    protected $casts = [
        'capacity' => 'decimal:4',
        'efficiency' => 'decimal:2',
        'maximum_efficiency' => 'decimal:2',
        'minimum_efficiency' => 'decimal:2',
        'direct_unit_cost' => 'decimal:4',
        'indirect_cost_percent' => 'decimal:2',
        'overhead_rate' => 'decimal:4',
        'queue_time' => 'decimal:4',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(WorkCenterGroup::class, 'work_center_group_id');
    }

    public function machineCenters(): HasMany
    {
        return $this->hasMany(MachineCenter::class, 'work_center_id');
    }

    public function calendarEntries(): HasMany
    {
        return $this->hasMany(WorkCenterCalendar::class, 'work_center_id');
    }

    public function subcontractor(): BelongsTo
    {
        // Fixed: lowercase method name and lowercase belongsTo call
        return $this->belongsTo(Vendor::class, 'subcontractor_id');
    }

    /**
     * Define as 'bins' and 'HasMany' to ensure compatibility with
     * BinsRelationManager. The manager's UI will limit this to one entry.
     */
    public function bins(): HasMany
    {
        return $this->hasMany(WorkCenterBin::class, 'work_center_id');
    }

    /**
     * Get available capacity for date range
     */
    public function getAvailableCapacity($startDate, $endDate): float
    {
        return (float) $this->calendarEntries()
            ->whereBetween('date', [$startDate, $endDate])
            ->where('is_working_day', true)
            ->sum('capacity');
    }
}
