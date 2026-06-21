<?php

declare(strict_types=1);

namespace App\Services\Warehouse;

use App\Enums\BinType;
use App\Enums\ZoneType;
use App\Models\Bin;
use App\Models\Item;
use App\Models\Location;
use Illuminate\Support\Collection;

class BinSuggestionService
{
    /**
     * Suggest bins for put-away based on item characteristics and warehouse rules
     */
    public function suggestPutAwayBins(
        Item $item,
        Location $location,
        float $quantity,
        ?BinType $preferredBinType = null,
        ?ZoneType $preferredZoneType = null,
        ?string $preferredZoneCode = null
    ): Collection {
        $query = Bin::with(['contents', 'zone'])
            ->where('location_id', $location->id)
            ->where('is_active', true)
            ->where('block_movement_in', false)
            ->where(function ($q) use ($item) {
                $q->where('dedicated', false)
                    ->orWhere('dedicated_item_id', $item->id);
            });

        if ($preferredBinType) {
            $query->where('bin_type', $preferredBinType);
        }

        if ($preferredZoneType) {
            $query->whereHas('zone', function ($q) use ($preferredZoneType) {
                $q->where('zone_type', $preferredZoneType);
            });
        }

        if ($preferredZoneCode) {
            $query->whereHas('zone', function ($q) use ($preferredZoneCode) {
                $q->where('zone_code', $preferredZoneCode);
            });
        }

        if ($item->warehouse_class) {
            $query->where('warehouse_class', $item->warehouse_class);
        }

        $bins = $query->get();

        return $bins->filter(fn ($bin) => $this->canAccommodate($bin, $item, $quantity))
            ->sortByDesc(fn ($bin) => $this->scoreBinForPutAway($bin, $item))
            ->map(fn ($bin) => (object) [
                'bin_id' => $bin->id,
                'zone_id' => $bin->zone_id,
                'bin_code' => $bin->bin_code,
                'available_capacity' => $this->calculateAvailableCapacity($bin, $item),
            ]);
    }

    /**
     * Suggest bin for picking (minimize travel distance)
     */
    public function suggestPickBin(
        Item $item,
        Location $location,
        float $quantityNeeded,
        ?string $excludeBinCode = null
    ): ?Bin {
        // Prioritize: Same lot consolidation, proximity to shipping, FEFO
        return null; // Implementation depends on warehouse layout data
    }

    private function canAccommodate(Bin $bin, Item $item, float $quantity): bool
    {
        if (! $bin->acceptsItem($item)) {
            return false;
        }

        $currentQty = $bin->contents->sum('quantity_base');
        $incomingQtyBase = $quantity * ($item->base_unit_of_measure_qty ?? 1);

        if ($bin->maximum_items && $bin->contents->count() >= $bin->maximum_items) {
            // Check if same item exists (can consolidate)
            $hasItem = $bin->contents->contains('item_id', $item->id);
            if (! $hasItem && $bin->contents->count() >= $bin->maximum_items) {
                return false;
            }
        }

        return true;
    }

    private function scoreBinForPutAway(Bin $bin, int|Item $item): int
    {
        $itemModel = $item instanceof Item ? $item : Item::find($item);
        if (!$itemModel) return 0;

        $score = 0;

        // Dedicated bin for this item = highest priority
        if ($bin->dedicated && $bin->dedicated_item_id === $itemModel->id) {
            $score += 100;
        }

        // Existing inventory of same item (consolidation)
        if ($bin->contents->contains('item_id', $itemModel->id)) {
            $score += 50;
        }

        // Empty bin (clean slate)
        if ($bin->contents->isEmpty()) {
            $score += 20;
        }

        // Proximity to receiving (for fast-moving items)
        if ($itemModel->is_fast_moving && $bin->zone?->zone_type === ZoneType::RECEIVING) {
            $score += 10;
        }

        return $score;
    }

    private function calculateAvailableCapacity(Bin $bin, Item $item): float
    {
        // Calculate how much more can fit
        $currentQty = $bin->contents->sum('quantity_base');
        $maxQty = $bin->maximum_items ?? PHP_INT_MAX;

        return max(0, (float)$maxQty - $currentQty);
    }
}
