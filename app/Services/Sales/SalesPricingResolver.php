<?php

namespace App\Services\Sales;

use App\Models\Customer;
use App\Models\CustomerPriceOverride;
use App\Models\DiscountRule;
use App\Models\Item;
use App\Models\Location;
use App\Models\PricingMaster;
use DateTimeInterface;

class SalesPricingResolver
{
    /**
     * @return array{
     *     unit_price: float,
     *     list_price: float,
     *     discount_amount: float,
     *     discount_percent: float,
     *     price_source: string,
     *     pricing_master_id: int|null,
     *     currency: string
     * }
     */
    public function resolve(
        Item $item,
        ?Customer $customer,
        float $quantity,
        ?string $variantCode = null,
        ?string $uom = null,
        ?Location $location = null,
        ?DateTimeInterface $date = null
    ): array {
        $date ??= now();
        $currency = config('app.default_currency', 'USD');
        $conversionFactor = 1.0;

        if ($uom) {
            $conversionFactor = (float) ($item->getConversionFactorForUom($uom) ?: 1.0);
            $conversionFactor = $conversionFactor > 0 ? $conversionFactor : 1.0;
        }

        $pricingMaster = PricingMaster::getBestPrice(
            item: $item,
            customer: $customer,
            variantCode: $variantCode,
            uom: $uom,
            quantity: $quantity,
            location: $location,
            date: $date
        );

        $listPrice = (float) ($item->unit_price ?? 0);
        $unitPrice = $listPrice;
        $priceSource = 'ITEM_CARD';
        $pricingMasterId = null;

        if ($pricingMaster) {
            $calculation = $pricingMaster->calculatePrice(
                quantity: $quantity,
                baseCost: $item->unit_cost,
                listPrice: $item->unit_price
            );

            $listPrice = (float) $calculation['base_price'];
            $unitPrice = (float) $calculation['final_price'];
            $priceSource = $pricingMaster->price_list_code;
            $pricingMasterId = $pricingMaster->id;
            $currency = $pricingMaster->currency_code ?: $currency;
        } elseif ($uom) {
            $listPrice *= $conversionFactor;
            $unitPrice *= $conversionFactor;
        }

        if ($customer) {
            $overridePrice = CustomerPriceOverride::getPriceFor((int) $customer->id, (int) $item->id);

            if ($overridePrice !== null) {
                $unitPrice = $overridePrice * $conversionFactor;
                $priceSource = 'CUSTOMER_OVERRIDE';
                $pricingMasterId = null;
            }
        }

        $discountPercent = 0.0;

        if ($customer?->customer_group_id) {
            $discountRule = DiscountRule::query()
                ->active()
                ->where('item_id', $item->id)
                ->where('customer_group_id', $customer->customer_group_id)
                ->first();

            if ($discountRule) {
                $discountPercent = (float) $discountRule->discount_percent;
            }
        }

        $discountAmount = $discountPercent > 0
            ? round($unitPrice * $quantity * ($discountPercent / 100), 4)
            : 0.0;

        return [
            'unit_price' => round($unitPrice, 4),
            'list_price' => round($listPrice, 4),
            'discount_amount' => $discountAmount,
            'discount_percent' => $discountPercent,
            'price_source' => $priceSource,
            'pricing_master_id' => $pricingMasterId,
            'currency' => $currency,
        ];
    }
}
