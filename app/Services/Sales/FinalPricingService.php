<?php

namespace App\Services\Sales;

use App\Services\PricingService;

class FinalPricingService
{
    public function getFinalPrice($item, $customer)
    {
        $price = app(PricingService::class)
            ->getSalesPrice($item, $customer, 1)['unit_price'];

        $price = app(CampaignService::class)
            ->apply($item, $price);

        return $price;
    }
}
