<?php

namespace App\Services\Sales;

use App\Models\DiscountRule;

class DiscountService
{
    public function apply($item, $customer, $price)
    {
        $discount = DiscountRule::query()
            ->where('item_id', $item->id)
            ->where('customer_group_id', $customer->customer_group_id)
            ->active()
            ->first();

        if (! $discount) {
            return $price;
        }

        return $price * (1 - $discount->discount_percent / 100);
    }
}
