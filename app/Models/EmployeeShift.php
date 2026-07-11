<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmployeeShift extends Model
{
    protected $fillable = [
        'business_id',
        'code',
        'name',
        'start_time',
        'end_time',
        'crosses_midnight',
        'break_minutes',
        'grace_minutes',
        'early_departure_grace_minutes',
        'overtime_threshold_minutes',
        'is_weekend',
        'is_active',
    ];

    protected $casts = [
        'crosses_midnight' => 'boolean',
        'break_minutes' => 'integer',
        'grace_minutes' => 'integer',
        'early_departure_grace_minutes' => 'integer',
        'overtime_threshold_minutes' => 'integer',
        'is_weekend' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function scheduleAssignments(): HasMany
    {
        return $this->hasMany(EmployeeWorkScheduleAssignment::class);
    }
}
