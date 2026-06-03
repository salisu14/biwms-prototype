<?php

namespace App\Services\Inventory;

use App\Models\Item;
use App\Models\PriceChangeTemplate;
use App\Models\PriceChangeTemplateLine;
use Illuminate\Support\Facades\DB;

class ItemService
{
    /**
     * Apply a price change template to items
     */
    public function applyPriceTemplate(PriceChangeTemplate $template): int
    {
        return DB::transaction(function () use ($template) {
            $updatedCount = 0;

            $template->lines()->whereNull('applied_at')->each(function (PriceChangeTemplateLine $line) use (&$updatedCount, $template) {
                if ($line->item_id) {
                    $item = Item::find($line->item_id);
                    if ($item) {
                        $oldPrice = (float) $item->unit_price;
                        $newPrice = $this->resolveNewUnitPrice($template, $line, $item);

                        $item->update([
                            'unit_price' => $newPrice,
                        ]);

                        $line->update([
                            'current_unit_price' => $oldPrice,
                            'new_unit_price' => $newPrice,
                            'adjustment_amount' => $newPrice - $oldPrice,
                            'adjustment_percent' => $oldPrice > 0 ? (($newPrice - $oldPrice) / $oldPrice) * 100 : 0,
                            'applied_at' => now(),
                        ]);

                        $updatedCount++;
                    }
                } elseif ($line->category_id) {
                    $items = Item::query()
                        ->where('item_category_id', $line->category_id)
                        ->where('item_type', 'FINISHED_GOOD')
                        ->get();

                    $categoryCurrentTotal = 0.0;
                    $categoryNewTotal = 0.0;

                    foreach ($items as $item) {
                        $oldPrice = (float) $item->unit_price;
                        $newPrice = $this->resolveNewUnitPrice($template, $line, $item);

                        $item->update([
                            'unit_price' => $newPrice,
                        ]);

                        $categoryCurrentTotal += $oldPrice;
                        $categoryNewTotal += $newPrice;
                        $updatedCount++;
                    }

                    $line->update([
                        'current_unit_price' => count($items) > 0 ? $categoryCurrentTotal / count($items) : 0,
                        'new_unit_price' => count($items) > 0 ? $categoryNewTotal / count($items) : 0,
                        'adjustment_amount' => count($items) > 0 ? ($categoryNewTotal / count($items)) - ($categoryCurrentTotal / count($items)) : 0,
                        'adjustment_percent' => $categoryCurrentTotal > 0 ? ((($categoryNewTotal / max(count($items), 1)) - ($categoryCurrentTotal / max(count($items), 1))) / ($categoryCurrentTotal / max(count($items), 1))) * 100 : 0,
                        'applied_at' => now(),
                    ]);
                }
            });

            if ($updatedCount > 0) {
                $template->update(['status' => 'applied']);
            }

            return $updatedCount;
        });
    }

    /**
     * Synchronize master item properties to location-specific SKUs
     */
    public function syncItemToSkus(Item $item, ?array $fields = null): int
    {
        return $item->syncToSkus($fields);
    }

    public function previewNewUnitPrice(PriceChangeTemplate $template, PriceChangeTemplateLine $line): ?float
    {
        if ($line->item_id) {
            $item = $line->item ?? Item::find($line->item_id);

            return $item ? $this->resolveNewUnitPrice($template, $line, $item) : null;
        }

        return null;
    }

    protected function resolveNewUnitPrice(PriceChangeTemplate $template, PriceChangeTemplateLine $line, Item $item): float
    {
        $lineOverridePrice = (float) ($line->new_unit_price ?? 0);
        if ($template->adjustment_type === 'fixed' && $lineOverridePrice > 0) {
            return $this->roundPrice($lineOverridePrice, $template);
        }

        $basePrice = $template->base === 'cost'
            ? (float) ($item->unit_cost ?? 0)
            : (float) ($item->unit_price ?? 0);

        $adjustmentValue = (float) ($template->value ?? 0);

        $newPrice = match ($template->adjustment_type) {
            'increase' => $basePrice * (1 + ($adjustmentValue / 100)),
            'decrease' => $basePrice * (1 - ($adjustmentValue / 100)),
            'fixed' => $adjustmentValue,
            default => (float) ($item->unit_price ?? 0),
        };

        return $this->roundPrice($newPrice, $template);
    }

    protected function roundPrice(float $price, PriceChangeTemplate $template): float
    {
        $precision = max(0, (int) round((float) ($template->rounding ?? 2)));

        return round($price, $precision);
    }
}
