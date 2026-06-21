<?php

namespace App\Actions\Pricing;

use App\Models\PriceList;
use App\Services\Pricing\PriceValidationService;

class CreatePriceListAction
{
    /**
     * @throws \Exception
     */
    public function execute(array $data): PriceList
    {
        // ✅ Validate BEFORE insert
        app(PriceValidationService::class)->validateUniquePrice($data);

        return PriceList::create($data);
    }
}
