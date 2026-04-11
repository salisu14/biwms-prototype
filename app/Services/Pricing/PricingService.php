<?php

namespace App\Services\Pricing;

use App\Models\PriceList;

class PricingService
{
    public function resolvePrice($item, $customer)
    {
        return PriceList::query()
            ->where('item_id', $item->id)

            ->where(function ($q) use ($customer) {
                $q->where('customer_id', $customer->id)
                    ->orWhere('customer_group_id', $customer->customer_group_id)
                    ->orWhere(function ($q) {
                        $q->whereNull('customer_id')
                            ->whereNull('customer_group_id');
                    });
            })

            ->whereDate('starting_date', '<=', now())
            ->where(function ($q) {
                $q->whereNull('ending_date')
                    ->orWhere('ending_date', '>=', now());
            })

            // Priority (VERY IMPORTANT)
            ->orderByRaw('
                CASE
                    WHEN customer_id IS NOT NULL THEN 1
                    WHEN customer_group_id IS NOT NULL THEN 2
                    ELSE 3
                END
            ')

            ->value('price');
    }
}
