<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CostingPeriod extends Model
{
    protected $fillable = [
        'start_date',
        'end_date',
        'name',
        'is_closed',
        'adjustment_allowed_through',
        'cost_adjustment_posting_date',
        'closed_at',
        'closed_by',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_closed' => 'boolean',
        'adjustment_allowed_through' => 'date',
        'cost_adjustment_posting_date' => 'date',
        'closed_at' => 'datetime',
    ];

    public function closer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'closed_by');
    }

    public function scopeContainingDate($query, mixed $date)
    {
        return $query
            ->whereDate('start_date', '<=', $date)
            ->whereDate('end_date', '>=', $date);
    }
}
