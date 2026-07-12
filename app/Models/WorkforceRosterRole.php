<?php

declare(strict_types=1);

namespace App\Models;

use App\Models\Manufacturing\WorkCenter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkforceRosterRole extends Model
{
    use HasFactory;

    protected $fillable = [
        'business_id',
        'code',
        'name',
        'description',
        'department_id',
        'work_center_id',
        'is_critical',
        'is_active',
    ];

    protected $casts = [
        'is_critical' => 'boolean',
        'is_active' => 'boolean',
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function workCenter(): BelongsTo
    {
        return $this->belongsTo(
            WorkCenter::class,
            'work_center_id'
        );
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('is_critical', true);
    }
}
