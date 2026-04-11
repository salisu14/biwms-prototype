<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Models\Bin;
use App\Models\BinContent;
use App\Models\Item;
use App\Models\Location;
use Illuminate\Support\Collection;

class BinAvailabilityService
{
    /**
     * Find available inventory for picking (FEFO/FIFO)
     */
    public function findPickSources(
        Item $item,
        Location $location,
        float $quantityNeeded,
        ?string $lotNo = null,
        ?string $zoneCode = null,
        array $excludeBins = [],
        string $pickingMethod = 'FEFO'
    ): Collection {
        $query = BinContent::with(['bin', 'zone'])
            ->where('item_id', $item->id)
            ->whereHas('bin', function ($q) use ($location, $excludeBins) {
                $q->where('location_id', $location->id)
                    ->where('is_active', true)
                    ->where('block_movement_out', false)
                    ->whereNotIn('id', $excludeBins);
            })
            ->where('quantity', '>', 0);

        if ($lotNo) {
            $query->where('lot_no', $lotNo);
        }

        if ($zoneCode) {
            $query->whereHas('zone', fn ($q) => $q->where('zone_code', $zoneCode));
        }

        // Apply picking strategy
        $query->orderBy('expiration_date', 'asc') // FEFO default
            ->orderBy('created_at', 'asc');     // FIFO fallback

        $contents = $query->get();

        // Filter bins that can actually provide inventory
        return $contents->filter(fn ($c) => $c->availableQuantity() > 0)
            ->sortByDesc('available_quantity');
    }

    /**
     * Check if bin can accept item
     */
    public function canPlaceInBin(
        Item $item,
        Bin $bin,
        float $quantity,
        ?string $lotNo = null
    ): bool {
        if (! $bin->acceptsItem($item)) {
            return false;
        }

        // Check capacity constraints
        $existingContent = BinContent::where('bin_id', $bin->id)
            ->where('item_id', '!=', $item->id)
            ->sum('quantity_base');

        $newQtyBase = $this->calculateBaseQty($item, $quantity, $item->base_unit_of_measure);

        if ($bin->maximum_items && BinContent::where('bin_id', $bin->id)->count() >= $bin->maximum_items) {
            return false;
        }

        // Check weight/volume constraints
        if ($bin->maximum_weight) {
            $itemWeight = $item->unit_weight * $quantity;
            $existingWeight = BinContent::where('bin_id', $bin->id)->sum(DB::raw('quantity * unit_weight'));
            if (($existingWeight + $itemWeight) > $bin->maximum_weight) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get available quantity across all bins
     */
    public function getAvailableQuantity(
        Item $item,
        Location $location,
        ?string $lotNo = null
    ): float {
        $query = BinContent::where('item_id', $item->id)
            ->whereHas('bin', fn ($q) => $q->where('location_id', $location->id));

        if ($lotNo) {
            $query->where('lot_no', $lotNo);
        }

        return $query->get()->sum(fn ($c) => $c->availableQuantity());
    }

    private function calculateBaseQty(Item $item, float $qty, string $uom): float
    {
        // Conversion logic
        return $qty; // Simplified
    }
}
