<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Item;
use App\Models\PostedPurchaseInvoiceLine;
use App\Models\PurchasePrice;
use App\Models\Vendor;

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
        ?\DateTime $date = null,
        ?int $businessId = null
    ): array {
        $date = $date ?? now();
        $unitOfMeasure = $unitOfMeasure ?? $item->base_unit_of_measure;

        // 1. Check vendor-specific purchase price table
        $specificPrice = $this->getSpecificPrice($vendor, $item, $quantity, $unitOfMeasure, $date);

        // 2. Get last direct cost from item ledger
        $lastDirectCost = $this->getLastDirectCost($vendor, $item, $date, $businessId);

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
     * Get the most recent posted purchase invoice line cost for this vendor/item.
     */
    private function getLastDirectCost(Vendor $vendor, Item $item, \DateTime $date, ?int $businessId = null): ?float
    {
        $query = PostedPurchaseInvoiceLine::query()
            ->select('posted_purchase_invoice_lines.*')
            ->join('posted_purchase_invoices', 'posted_purchase_invoices.id', '=', 'posted_purchase_invoice_lines.posted_purchase_invoice_id')
            ->where('posted_purchase_invoices.vendor_id', $vendor->id)
            ->where('posted_purchase_invoices.cancelled', false)
            ->where('posted_purchase_invoice_lines.item_id', $item->id)
            ->whereDate('posted_purchase_invoices.posting_date', '<=', $date->format('Y-m-d'))
            ->orderByDesc('posted_purchase_invoices.posting_date')
            ->orderByDesc('posted_purchase_invoices.id')
            ->orderByDesc('posted_purchase_invoice_lines.line_number')
            ->orderByDesc('posted_purchase_invoice_lines.id');

        if ($businessId !== null) {
            $query->where('posted_purchase_invoices.business_id', $businessId);
        }

        $lastEntry = $query->first();

        if (! $lastEntry) {
            return null;
        }

        return (float) ($lastEntry->unit_cost_lcy ?? $lastEntry->unit_cost ?? 0);
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
