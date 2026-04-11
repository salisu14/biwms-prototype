<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\BinType;
use App\Enums\WarehouseClass;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Bin extends Model
{
    use HasFactory;

    protected $fillable = [
        'location_id',
        'zone_id',
        'bin_code',
        'bin_name',
        'bin_type',
        'warehouse_class',
        'maximum_weight',
        'maximum_volume',
        'maximum_items',
        'dedicated',
        'dedicated_item_id',
        'blocked',
        'block_movement_in',
        'block_movement_out',
        'is_active',
        'barcode',
    ];

    protected $casts = [
        'bin_type' => BinType::class,
        'warehouse_class' => WarehouseClass::class,
        'dedicated' => 'boolean',
        'blocked' => 'boolean',
        'block_movement_in' => 'boolean',
        'block_movement_out' => 'boolean',
        'is_active' => 'boolean',
        'maximum_weight' => 'decimal:4',
        'maximum_volume' => 'decimal:4',
    ];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class, 'location_id');
    }

    public function zone(): BelongsTo
    {
        return $this->belongsTo(Zone::class, 'zone_id');
    }

    public function dedicatedItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'dedicated_item_id');
    }

    public function contents(): HasMany
    {
        return $this->hasMany(BinContent::class, 'bin_id');
    }

    public function isAvailableForInbound(): bool
    {
        return ! $this->blocked && ! $this->block_movement_in && $this->is_active;
    }

    public function isAvailableForOutbound(): bool
    {
        return ! $this->blocked && ! $this->block_movement_out && $this->is_active;
    }

    public function isDedicated(): bool
    {
        return $this->dedicated && $this->dedicated_item_id !== null;
    }

    public function acceptsItem(Item $item): bool
    {
        if (! $this->isAvailableForInbound()) {
            return false;
        }

        if ($this->isDedicated() && $this->dedicated_item_id !== $item->id) {
            return false;
        }

        // Check warehouse class compatibility
        if ($item->warehouse_class && $item->warehouse_class !== $this->warehouse_class) {
            return false;
        }

        return true;
    }
}
