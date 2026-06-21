<?php

namespace App\Services\Pricing;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Location;
use App\Services\Sales\SalesPricingResolver;

class PricingService
{
    public function __construct(protected SalesPricingResolver $resolver) {}

    public function resolvePrice(
        Item $item,
        ?Customer $customer,
        float $quantity = 1,
        ?string $variantCode = null,
        ?string $uom = null,
        ?Location $location = null,
        ?\DateTimeInterface $date = null
    ): float {
        return $this->resolver->resolve(
            item: $item,
            customer: $customer,
            quantity: $quantity,
            variantCode: $variantCode,
            uom: $uom,
            location: $location,
            date: $date
        )['unit_price'];
    }
}
