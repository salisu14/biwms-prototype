<?php

namespace App\Models\Manufacturing;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Models\Item;

class Routing extends Model
{
    use HasFactory;

    protected $table = 'routings';

    protected $fillable = [
        'code',
        'description',
        'item_id', // Can be linked to specific item
        'status',
        'version',
        'starting_date',
        'ending_date',
        'type', // SERIAL, PARALLEL

        // Costing
        'cost_rollup',

        // User tracking
        'created_by',
        'last_modified_by',
    ];

    protected $casts = [
        'starting_date' => 'date',
        'ending_date' => 'date',
        'cost_rollup' => 'decimal:4',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(RoutingLine::class, 'routing_id');
    }

    public function versions(): HasMany
    {
        return $this->hasMany(RoutingVersion::class, 'routing_id');
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
