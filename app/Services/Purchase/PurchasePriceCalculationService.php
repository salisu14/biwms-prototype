<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Item;
use App\Models\PurchasePrice;
use App\Models\Vendor;
use App\Models\VendorLedgerEntry;

class PurchasePriceCalculationService
{
    /**
     * Get best unit cost for item from vendor (BC: Get Best Price)
     */
    public function getUnitCost(
        Vendor $vendor,
        Item $item,
        float $quantity = 1,
        ?string $unitOfMeasure = null,
        ?\DateTime $date = null
    ): array {
        $date = $date ?? now();
        $unitOfMeasure = $unitOfMeasure ?? $item->base_unit_of_measure;

        // 1. Check vendor-specific purchase price table
        $specificPrice = $this->getSpecificPrice($vendor, $item, $quantity, $unitOfMeasure, $date);

        // 2. Get last direct cost from item ledger
        $lastDirectCost = $this->getLastDirectCost($vendor, $item);

        // 3. Get standard cost from item card
        $standardCost = $item->standard_cost;

        // 4. Determine best price (lowest valid price)
        $bestPrice = $this->determineBestPrice([
            'purchase_price' => $specificPrice,
            'last_direct_cost' => $lastDirectCost,
            'standard_cost' => $standardCost,
        ]);

        return [
            'direct_unit_cost' => $bestPrice['cost'],
            'line_discount_percent' => $specificPrice['discount'] ?? 0,
            'price_source' => $bestPrice['source'],
            'vendor_item_no' => $specificPrice['vendor_item_no'] ?? null,
        ];
    }

    /**
     * Get vendor-specific purchase price
     */
    private function getSpecificPrice(
        Vendor $vendor,
        Item $item,
        float $quantity,
        string $unitOfMeasure,
        \DateTime $date
    ): ?array {
        $price = PurchasePrice::where([
            'vendor_id' => $vendor->id,
            'item_id' => $item->id,
        ])
            ->where(function ($q) use ($date) {
                $q->whereNull('starting_date')
                    ->orWhere('starting_date', '<=', $date);
            })
            ->where(function ($q) use ($date) {
                $q->whereNull('ending_date')
                    ->orWhere('ending_date', '>=', $date);
            })
            ->where('minimum_quantity', '<=', $quantity)
            ->orderBy('minimum_quantity', 'desc')
            ->orderBy('starting_date', 'desc')
            ->first();

        if (! $price) {
            return null;
        }

        return [
            'cost' => $this->convertUnitCost($price->direct_unit_cost, $price->unit_of_measure_code, $unitOfMeasure, $item),
            'discount' => $price->line_discount_percent,
            'vendor_item_no' => $price->vendor_item_no,
        ];
    }

    /**
     * Get last direct cost from vendor/item ledger entries
     */
    private function getLastDirectCost(Vendor $vendor, Item $item): ?float
    {
        $lastEntry = VendorLedgerEntry::where('vendor_id', $vendor->id)
            ->whereHas('itemLedgerEntries', function ($q) use ($item) {
                $q->where('item_id', $item->id);
            })
            ->where('document_type', 'invoice')
            ->latest('posting_date')
            ->first();

        return $lastEntry?->itemLedgerEntries()
            ->where('item_id', $item->id)
            ->latest()
            ->first()?->unit_cost;
    }

    /**
     * Determine best price from available sources
     */
    private function determineBestPrice(array $sources): array
    {
        $validPrices = [];

        if ($sources['purchase_price']) {
            $validPrices[] = ['cost' => $sources['purchase_price']['cost'], 'source' => 'purchase_price'];
        }
        if ($sources['last_direct_cost']) {
            $validPrices[] = ['cost' => $sources['last_direct_cost'], 'source' => 'last_direct_cost'];
        }
        if ($sources['standard_cost']) {
            $validPrices[] = ['cost' => $sources['standard_cost'], 'source' => 'standard_cost'];
        }

        if (empty($validPrices)) {
            return ['cost' => 0, 'source' => 'none'];
        }

        // Return lowest cost
        return collect($validPrices)->sortBy('cost')->first();
    }

    /**
     * Convert unit cost between units of measure
     */
    private function convertUnitCost(
        float $cost,
        ?string $fromUom,
        string $toUom,
        Item $item
    ): float {
        if (! $fromUom || $fromUom === $toUom) {
            return $cost;
        }

        $fromQty = $item->getConversionFactorForUom($fromUom);
        $toQty = $item->getConversionFactorForUom($toUom);

        return ($cost / $fromQty) * $toQty;
    }

    /**
     * Calculate line amounts with discounts and VAT
     */
    public function calculateLineAmounts(array $lineData): array
    {
        $quantity = $lineData['quantity'] ?? 0;
        $unitCost = $lineData['direct_unit_cost'] ?? 0;
        $discountPercent = $lineData['line_discount_percent'] ?? 0;
        $vatPercent = $lineData['vat_percent'] ?? 0;

        $amount = $quantity * $unitCost;
        $discountAmount = $amount * ($discountPercent / 100);
        $lineAmount = $amount - $discountAmount;
        $vatAmount = $lineAmount * ($vatPercent / 100);
        $amountInclVat = $lineAmount + $vatAmount;

        return [
            'line_discount_amount' => round($discountAmount, 4),
            'line_amount' => round($lineAmount, 4),
            'vat_amount' => round($vatAmount, 4),
            'amount_including_vat' => round($amountInclVat, 4),
        ];
    }
}
