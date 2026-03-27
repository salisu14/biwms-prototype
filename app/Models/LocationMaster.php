<?php
// app/Models/LocationMaster.php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'parent_id',
    'location_code',
    'location_name',
    'location_type',
    'temperature_zone',
    'sort_order',
    'is_active'
])]
class LocationMaster extends Model
{
    use HasFactory;

    protected $primaryKey = 'id';
    protected $table = 'location_masters';
    protected $casts = [
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    /**
     * Ledger entries at this location
     */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(ItemLedger::class);
    }

    /**
     * SKUs stored at this location
     */
    public function skus(): HasMany
    {
        return $this->hasMany(ItemSku::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(LocationMaster::class, 'parent_id');
    }

    /**
     * Get all items currently at this location with quantities
     */
    public function inventory(): \Illuminate\Support\Collection
    {
        return ItemLedger::where('location_id', $this->location_id)
            ->select('item_id')
            ->selectRaw('
                SUM(CASE
                    WHEN entry_type IN (\'RECEIPT\', \'TRANSFER_IN\', \'ADJUSTMENT_POS\') THEN quantity
                    WHEN entry_type IN (\'ISSUE\', \'TRANSFER_OUT\', \'SALE\', \'ADJUSTMENT_NEG\') THEN -quantity
                    ELSE 0
                END) as quantity
            ')
            ->groupBy('item_id')
            ->with('item')
            ->get();
    }
}
