<?php

namespace App\Actions\Pricing;

use App\Models\PriceList;
use App\Services\Pricing\PriceValidationService;

class UpdatePriceListAction
{
    public function execute(PriceList $priceList, array $data): PriceList
    {
        app(PriceValidationService::class)->validateUniquePrice($data);

        $priceList->update($data);

        return $priceList;
    }
}
