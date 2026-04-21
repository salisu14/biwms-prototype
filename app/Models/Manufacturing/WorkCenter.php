<?php

namespace App\Models\Manufacturing;

use App\Models\ChartOfAccount;
use App\Models\Employee;
use App\Models\FixedAsset;
use App\Models\Location;
use App\Models\UnitOfMeasure;
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
        'work_center_account_no', // Legacy string (kept for reference)
        'work_center_gl_account_id', // FK to chart_of_accounts
        'subcontractor_id', // If outsourced work center
        'fixed_asset_id',
        'operator_employee_id',
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
        'fixed_asset_id' => 'integer',
        'operator_employee_id' => 'integer',
    ];

    public function group(): BelongsTo
    {
        return $this->belongsTo(WorkCenterGroup::class, 'work_center_group_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_code', 'code');
    }

    public function unitOfMeasure(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'unit_of_measure_code', 'uom_code');
    }

    public function machineCenters(): HasMany
    {
        return $this->hasMany(MachineCenter::class, 'work_center_id');
    }

    public function calendarEntries(): HasMany
    {
        return $this->hasMany(WorkCenterCalendar::class, 'work_center_id');
    }

    /** The G/L account used to post WIP and capacity costs for this work center. */
    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'work_center_gl_account_id');
    }

    public function subcontractor(): BelongsTo
    {
        // Fixed: lowercase method name and lowercase belongsTo call
        return $this->belongsTo(Vendor::class, 'subcontractor_id');
    }

    public function fixedAsset(): BelongsTo
    {
        return $this->belongsTo(FixedAsset::class, 'fixed_asset_id');
    }

    public function operatorEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'operator_employee_id');
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

    /**
     * Get calendar entry for a specific date
     */
    public function getCalendarForDate($date): ?WorkCenterCalendar
    {
        $dateStr = ($date instanceof \DateTime) ? $date->format('Y-m-d') : $date;
        
        return $this->calendarEntries()
            ->whereDate('date', $dateStr)
            ->first();
    }

    /**
     * Get the next available working date/time starting from a given point
     */
    public function getNextWorkingDateTime(\DateTime $startFrom, bool $forward = true): ?\DateTime
    {
        $current = \Carbon\Carbon::instance($startFrom);

        // Limit search to 365 days to prevent infinite loops
        for ($i = 0; $i < 365; $i++) {
            $dateStr = $current->format('Y-m-d');
            $calendar = $this->getCalendarForDate($dateStr);

            if ($calendar && $calendar->is_working_day) {
                // If we are looking forward and the start time is before the end of the shift
                if ($forward) {
                    $shiftEnd = \Carbon\Carbon::parse($calendar->end_time)->setDate($current->year, $current->month, $current->day);
                    if ($current->lt($shiftEnd)) {
                        $shiftStart = \Carbon\Carbon::parse($calendar->start_time)->setDate($current->year, $current->month, $current->day);
                        return $current->lt($shiftStart) ? $shiftStart->toDateTime() : $current->toDateTime();
                    }
                } else {
                    // Backward
                    $shiftStart = \Carbon\Carbon::parse($calendar->start_time)->setDate($current->year, $current->month, $current->day);
                    if ($current->gt($shiftStart)) {
                        $shiftEnd = \Carbon\Carbon::parse($calendar->end_time)->setDate($current->year, $current->month, $current->day);
                        return $current->gt($shiftEnd) ? $shiftEnd->toDateTime() : $current->toDateTime();
                    }
                }
            }

            if ($forward) {
                $current->addDay()->startOfDay();
            } else {
                $current->subDay()->endOfDay();
            }
        }

        return null;
    }
}
