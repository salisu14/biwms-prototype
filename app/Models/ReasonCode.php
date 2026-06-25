<?php

// app/Models/ReasonCode.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReasonCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'default_location_id',      // ✅ CHANGED: was default_location_code
        'default_bin_id',           // ✅ CHANGED: was default_bin_code
        'inventory_adjustment_account',
        'inventory_account',
        'blocked',
        'comment',
    ];

    protected $casts = [
        'blocked' => 'boolean',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'default_location_id', 'id');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'default_bin_id', 'id');
    }

    /**
     * Relationship to track usage in Adjustment Journals
     */
    public function journals(): HasMany
    {
        return $this->hasMany(InventoryAdjustmentJournal::class, 'reason_code', 'code');
    }

    public function scopeActive($query)
    {
        return $query->where('blocked', false);
    }

    public function scopeBlocked($query)
    {
        return $query->where('blocked', true);
    }

    public function scopeForLocation($query, int $locationId)
    {
        return $query->where('default_location_id', $locationId);
    }
    public function scopeForBin($query, int $binId)
    {
        return $query->where('default_bin_id', $binId);
    }

    /**
     * Scope for reason codes that have a default location set
     */
    public function scopeWithDefaultLocation($query)
    {
        return $query->whereNotNull('default_location_id');
    }

    /**
     * Scope for reason codes that have a default bin set
     */
    public function scopeWithDefaultBin($query)
    {
        return $query->whereNotNull('default_bin_id');
    }
}
