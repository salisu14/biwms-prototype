<?php

// app/Services/PricingService.php

namespace App\Services;

use App\Models\Customer;
use App\Models\Item;
use App\Models\Location;
use App\Models\PricingMaster;

class PricingService
{
    /**
     * Get price for sales order line
     */
    public function getSalesPrice(
        Item $item,
        ?Customer $customer,
        float $quantity,
        ?string $variantCode = null,
        ?string $uom = null,
        ?Location $location = null,
        ?\DateTime $date = null
    ): array {
        // 1. Try price list hierarchy
        $priceList = PricingMaster::getBestPrice(
            item: $item,
            customer: $customer,
            variantCode: $variantCode,
            uom: $uom,
            quantity: $quantity,
            location: $location,
            date: $date
        );

        if ($priceList) {
            $calculation = $priceList->calculatePrice(
                quantity: $quantity,
                baseCost: $item->unit_cost,
                listPrice: $item->unit_price
            );

            return [
                'unit_price' => $calculation['final_price'],
                'list_price' => $calculation['base_price'],
                'discount_amount' => $calculation['discount_amount'],
                'discount_percent' => $calculation['discount_percent'],
                'price_source' => $priceList->price_list_code,
                'pricing_master_id' => $priceList->id,
                'currency' => $priceList->currency_code,
            ];
        }

        // 2. Fallback to item card
        return [
            'unit_price' => $item->unit_price,
            'list_price' => $item->unit_price,
            'discount_amount' => 0,
            'discount_percent' => 0,
            'price_source' => 'ITEM_CARD',
            'pricing_master_id' => null,
            'currency' => config('app.default_currency', 'USD'),
        ];
    }

    /**
     * Validate price against customer limits
     */
    public function validatePrice(
        Customer $customer,
        float $proposedPrice,
        float $listPrice,
        float $quantity
    ): array {
        $errors = [];
        $warnings = [];

        // Check maximum discount
        if ($customer->maximum_discount_percent) {
            $discountPercent = (($listPrice - $proposedPrice) / $listPrice) * 100;

            if ($discountPercent > $customer->maximum_discount_percent) {
                $errors[] = "Discount exceeds customer maximum of {$customer->maximum_discount_percent}%";
            }
        }

        // Check if discounts allowed
        if (! $customer->allow_discounts && $proposedPrice < $listPrice) {
            $errors[] = 'Customer does not allow discounts';
        }

        // Check minimum margin if item has cost
        // This would require item cost lookup

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Get transfer price (inter-company)
     */
    public function getTransferPrice(
        Item $item,
        Location $fromLocation,
        Location $toLocation,
        float $quantity
    ): float {
        // Look for transfer-specific price list
        $transferPrice = PricingMaster::where('price_list_type', 'TRANSFER')
            ->where('item_id', $item->id)
            ->where(function ($q) use ($fromLocation, $toLocation) {
                $q->where('location_id', $fromLocation->id)
                    ->orWhere('location_id', $toLocation->id);
            })
            ->where('status', 'ACTIVE')
            ->first();

        if ($transferPrice) {
            $calc = $transferPrice->calculatePrice($quantity, $item->unit_cost);

            return $calc['final_price'];
        }

        // Default to cost plus standard markup
        return $item->unit_cost * 1.10; // 10% markup
    }
}
