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
        'default_location_code',
        'default_bin_code',
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
        return $this->belongsTo(Location::class, 'default_location_code', 'code');
    }

    /**
     * Relationship to track usage in Adjustment Journals
     */
    public function journals(): HasMany
    {
        return $this->hasMany(InventoryAdjustmentJournal::class, 'reason_code', 'code');
    }

    public function bin(): BelongsTo
    {
        return $this->belongsTo(Bin::class, 'default_bin_code', 'bin_code');
    }

    public function scopeActive($query)
    {
        return $query->where('blocked', false);
    }

    public function scopeBlocked($query)
    {
        return $query->where('blocked', true);
    }

    public function scopeForLocation($query, string $locationCode)
    {
        return $query->where('default_location_code', $locationCode);
    }
}
