<?php

namespace App\Services\Pricing;

use App\Models\Item;

class ApplyPriceChangeTemplate
{
    public function execute($template)
    {
        $items = Item::query();

        // Apply filters
        if ($template->category_id) {
            $items->where('category_id', $template->category_id);
        }

        foreach ($items->get() as $item) {
            $basePrice = $template->base === 'cost'
                ? $item->cost_price
                : $item->selling_price;

            $newPrice = match ($template->adjustment_type) {
                'increase' => $basePrice * (1 + $template->value / 100),
                'decrease' => $basePrice * (1 - $template->value / 100),
                'fixed' => $template->value,
            };

            if ($template->rounding) {
                $newPrice = round($newPrice / $template->rounding) * $template->rounding;
            }

            $item->update([
                'selling_price' => $newPrice,
            ]);
        }
    }
}
