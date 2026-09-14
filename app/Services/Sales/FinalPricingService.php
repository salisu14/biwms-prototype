<?php

namespace App\Services\Sales;

use App\Services\PricingService;

class FinalPricingService
{
    public function getFinalPrice($item, $customer)
    {
        // Legacy LCY-only helper: it has no document currency context, so it
        // states NGN explicitly instead of relying on a resolver default.
        $price = app(PricingService::class)
            ->getSalesPrice($item, $customer, 1, documentCurrency: 'NGN')['unit_price'];

        $price = app(CampaignService::class)
            ->apply($item, $price);

        return $price;
    }
}
