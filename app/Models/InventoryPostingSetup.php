<?php

// app/Models/InventoryPostingSetup.php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryPostingSetup extends Model
{
    use HasFactory;

    protected $table = 'inventory_posting_setups';

    protected $fillable = [
        'location_id',
        'inventory_posting_group_id',
        'inventory_account_id',
        'inventory_account_interim_id',
        'wip_account_id',
    ];

    // Relationships
    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }

    public function inventoryPostingGroup(): BelongsTo
    {
        return $this->belongsTo(InventoryPostingGroup::class);
    }

    public function inventoryAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'inventory_account_id');
    }

    public function inventoryAccountInterim(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'inventory_account_interim_id');
    }

    public function wipAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'wip_account_id');
    }

    // Scope for location (including default)
    public function scopeForLocation($query, ?int $locationId)
    {
        return $query->where(function ($q) use ($locationId) {
            $q->where('location_id', $locationId)
                ->orWhereNull('location_id');
        })->orderByRaw('location_id IS NOT NULL DESC'); // Specific first, then default
    }

    // Get most specific setup
    public static function getFor(int $inventoryPostingGroupId, ?int $locationId = null): ?self
    {
        $query = self::where('inventory_posting_group_id', $inventoryPostingGroupId);

        if ($locationId) {
            $specific = $query->where('location_id', $locationId)->first();
            if ($specific) {
                return $specific;
            }
        }

        return $query->whereNull('location_id')->first();
    }
}
