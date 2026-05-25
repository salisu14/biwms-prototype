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

            $template->lines()->whereNull('applied_at')->each(function (PriceChangeTemplateLine $line) use (&$updatedCount) {
                if ($line->item_id) {
                    $item = Item::find($line->item_id);
                    if ($item) {
                        $oldPrice = (float) $item->unit_price;
                        $newPrice = (float) ($line->new_unit_price ?: $oldPrice);

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
                    // Apply to all items in category if specific item not set
                    $items = Item::where('item_category_id', $line->category_id)->get();
                    foreach ($items as $item) {
                        $oldPrice = (float) $item->unit_price;
                        $newPrice = (float) ($line->new_unit_price ?: $oldPrice);

                        $item->update([
                            'unit_price' => $newPrice,
                        ]);
                        $updatedCount++;
                    }

                    $line->update([
                        'adjustment_amount' => 0,
                        'adjustment_percent' => 0,
                        'applied_at' => now(),
                    ]);
                }
            });

            $template->update(['status' => 'applied']);

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
}
