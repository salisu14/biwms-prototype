<?php

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkCenterCalendar extends Model
{
    use HasFactory;

    protected $fillable = [
        'work_center_id',
        'date',
        'is_working_day',
        'start_time',
        'end_time',
        'capacity',
        'efficiency',
        'absence_code',
    ];

    protected $casts = [
        'date' => 'date',
        'is_working_day' => 'boolean',
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'capacity' => 'decimal:4',
        'efficiency' => 'decimal:2',
    ];

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(WorkCenter::class);
    }

    /**
     * Calculate available capacity in minutes for this calendar day
     */
    public function getAvailableMinutes(): float
    {
        if (!$this->is_working_day || !$this->start_time || !$this->end_time) {
            return 0;
        }

        $start = \Carbon\Carbon::parse($this->start_time);
        $end = \Carbon\Carbon::parse($this->end_time);

        return $end->diffInMinutes($start) * ($this->efficiency / 100);
    }
}
