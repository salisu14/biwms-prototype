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
        'uom_id',
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

    /**
     * The unit of measure for capacity
     */
    public function uom(): BelongsTo
    {
        return $this->belongsTo(UnitOfMeasure::class, 'uom_id');
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

    /**
     * Calculate current total weight in the bin
     */
    public function currentWeight(): float
    {
        return $this->contents->sum(function ($content) {
            return (float) $content->quantity * (float) ($content->item?->net_weight ?? 0);
        });
    }

    /**
     * Calculate current total volume in the bin
     */
    public function currentVolume(): float
    {
        return $this->contents->sum(function ($content) {
            return (float) $content->quantity * (float) ($content->item?->unit_volume ?? 0);
        });
    }

    /**
     * Calculate current item count (distinct items)
     */
    public function currentItemCount(): int
    {
        return $this->contents()->where('quantity', '>', 0)->distinct('item_id')->count();
    }

    /**
     * Validate if adding this item/quantity exceeds any limits.
     * Returns: ['status' => 'ok'|'warning'|'error', 'message' => '...']
     */
    public function validateCapacity(Item $item, float $quantity): array
    {
        $newWeight = $this->currentWeight() + ($quantity * (float) ($item->net_weight ?? 0));
        $newVolume = $this->currentVolume() + ($quantity * (float) ($item->unit_volume ?? 0));

        // Error conditions (Hard Limits - Optional depending on configuration, but per user request: "error at last")
        if ($this->maximum_weight > 0 && $newWeight > $this->maximum_weight) {
            return [
                'status' => 'error',
                'message' => "Capacity Exceeded: Weight limit is {$this->maximum_weight}, new weight would be {$newWeight}.",
            ];
        }

        if ($this->maximum_volume > 0 && $newVolume > $this->maximum_volume) {
            return [
                'status' => 'error',
                'message' => "Capacity Exceeded: Volume limit is {$this->maximum_volume}, new volume would be {$newVolume}.",
            ];
        }

        // Warning conditions (Standard BC approach)
        if ($this->maximum_weight > 0 && $newWeight > ($this->maximum_weight * 0.9)) {
            return [
                'status' => 'warning',
                'message' => 'Warning: Bin is approaching weight capacity (90%+).',
            ];
        }

        return ['status' => 'ok', 'message' => 'Capacity OK'];
    }
}
