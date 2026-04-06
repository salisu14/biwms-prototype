<?php

namespace App\Services\Sales;

use App\Services\Pricing\PricingService;

class FinalPricingService
{
    public function getFinalPrice($item, $customer)
    {
        $price = app(PricingService::class)
            ->resolvePrice($item, $customer);

        $price = app(DiscountService::class)
            ->apply($item, $customer, $price);

        $price = app(CampaignService::class)
            ->apply($item, $price);

        return $price;
    }
}
