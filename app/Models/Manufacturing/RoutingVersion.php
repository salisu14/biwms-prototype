<?php

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoutingVersion extends Model
{
    use HasFactory;

    protected $fillable = [
        'routing_id',
        'version_code',
        'description',
        'status', // CERTIFIED, UNDER_DEVELOPMENT, CLOSED
        'starting_date',
        'ending_date',
        'type', // SERIAL, PARALLEL
        'cost_rollup',
        'created_by',
        'last_modified_by',
    ];

    protected $casts = [
        'starting_date' => 'date',
        'ending_date' => 'date',
        'cost_rollup' => 'decimal:4',
    ];

    public function routing(): BelongsTo
    {
        return $this->belongsTo(Routing::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RoutingVersionLine::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function lastModifier(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'last_modified_by');
    }

    /**
     * Check if version is active
     */
    public function isActive(?\DateTime $date = null): bool
    {
        $checkDate = $date ?? now();

        return $this->status === 'CERTIFIED'
            && ($this->starting_date === null || $this->starting_date <= $checkDate)
            && ($this->ending_date === null || $this->ending_date >= $checkDate);
    }

    /**
     * Calculate total routing time for a given quantity
     */
    public function calculateTotalTime(float $quantity): float
    {
        $totalTime = 0;

        foreach ($this->lines as $line) {
            $setupTime = $line->setup_time;
            $runTime = $line->run_time * ($quantity / ($line->lot_size ?: 1));
            $waitTime = $line->wait_time;
            $moveTime = $line->move_time;

            if ($this->type === 'SERIAL') {
                $totalTime += $setupTime + $runTime + $waitTime + $moveTime;
            } else {
                // Parallel: take the maximum time across operations
                $totalTime = max($totalTime, $setupTime + $runTime + $waitTime + $moveTime);
            }
        }

        return $totalTime;
    }
    protected static function booted(): void
    {
        static::creating(function ($routing) {
            $routing->created_by = auth()->id();
        });

        static::updating(function ($routing) {
            $routing->last_modified_by = auth()->id();
        });
    }
}
