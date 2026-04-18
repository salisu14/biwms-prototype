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
                        $item->update([
                            'unit_price' => $line->new_unit_price,
                        ]);

                        $line->update([
                            'applied_at' => now(),
                        ]);

                        $updatedCount++;
                    }
                } elseif ($line->category_id) {
                    // Apply to all items in category if specific item not set
                    $items = Item::where('item_category_id', $line->category_id)->get();
                    foreach ($items as $item) {
                        $item->update([
                            'unit_price' => $line->new_unit_price,
                        ]);
                        $updatedCount++;
                    }

                    $line->update([
                        'applied_at' => now(),
                    ]);
                }
            });

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
