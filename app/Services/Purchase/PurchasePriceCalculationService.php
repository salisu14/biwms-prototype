<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\Item;
use App\Models\PostedPurchaseInvoiceLine;
use App\Models\PurchasePrice;
use App\Models\Vendor;
use App\Support\PurchasingCurrency;

class PurchasePriceCalculationService
{
    /**
     * Get best unit cost for item from vendor (BC: Get Best Price).
     *
     * The returned {@see direct_unit_cost} is expressed in the document currency
     * of the target purchase document. Sources are normalised to that currency
     * before comparison so an LCY (NGN) reference cost is never compared against
     * a foreign-currency vendor price as if they were the same unit.
     */
    public function getUnitCost(
        Vendor $vendor,
        Item $item,
        float $quantity = 1,
        ?string $unitOfMeasure = null,
        ?\DateTime $date = null,
        ?int $businessId = null,
        ?string $currencyCode = null,
        mixed $currencyFactor = null
    ): array {
        $date = $date ?? now();
        $unitOfMeasure = $unitOfMeasure ?? $item->base_unit_of_measure;

        $documentCurrency = strtoupper((string) ($currencyCode ?: PurchasingCurrency::LCY_CODE));
        $factor = PurchasingCurrency::factorFor($documentCurrency, $currencyFactor);
        $isLcyDocument = PurchasingCurrency::isLcyFactor($factor);

        // 1. Vendor negotiated price / vendor price list. Only usable when its
        //    source currency is known and matches the document currency.
        $specificPrice = $this->getSpecificPrice($vendor, $item, $quantity, $unitOfMeasure, $date);
        [$negotiatedCost, $priceProvenance] = $this->resolveNegotiatedPrice($specificPrice, $documentCurrency);

        // 2. Last document/vendor price from posted purchase history, accepted
        //    only when the historical FCY/LCY relationship is internally valid.
        $lastDirectCost = $this->getLastDirectCost($vendor, $item, $date, $businessId, $documentCurrency, $factor, $isLcyDocument);

        // 3. Item standard/reference cost (LCY), converted to document currency
        //    before it can be offered as a fallback suggestion.
        $referenceCost = $this->normalizeReferenceCost($item->standard_cost, $factor, $isLcyDocument);

        // 4. Determine best price from unit-consistent, provenance-checked sources
        $bestPrice = $this->determineBestPrice([
            'purchase_price' => $negotiatedCost !== null ? ['cost' => $negotiatedCost] : null,
            'last_direct_cost' => $lastDirectCost,
            'standard_cost' => $referenceCost,
        ]);

        return [
            'direct_unit_cost' => $bestPrice['cost'],
            'line_discount_percent' => $negotiatedCost !== null ? ($specificPrice['discount'] ?? 0) : 0,
            'price_source' => $bestPrice['source'],
            // Distinguishes a reference-derived suggestion from a negotiated
            // vendor price; reference cost is planning data, not a vendor price.
            'reference_derived' => $bestPrice['source'] === 'standard_cost',
            'currency_code' => $documentCurrency,
            'currency_factor' => $factor,
            'vendor_item_no' => $negotiatedCost !== null ? ($specificPrice['vendor_item_no'] ?? null) : null,
            // Provenance of the negotiated price row: known_same_currency,
            // unknown_currency_skipped, foreign_currency_skipped, or none.
            'price_provenance' => $priceProvenance,
            'negotiated_price_usable' => $negotiatedCost !== null,
        ];
    }

    /**
     * Decide whether a negotiated vendor price may be used as an automatic
     * document price candidate.
     *
     * A price with no recorded currency is ambiguous and is never assumed to be
     * the document currency. A price in a different (known) currency is skipped
     * because BIWMS has no safe, authoritative cross-currency price path here.
     *
     * @param  array{cost: float|string, currency_code: ?string, discount: mixed, vendor_item_no: mixed}|null  $price
     * @return array{0: float|string|null, 1: string}
     */
    private function resolveNegotiatedPrice(?array $price, string $documentCurrency): array
    {
        if ($price === null) {
            return [null, 'none'];
        }

        $sourceCurrency = $price['currency_code'] ?? null;

        if ($sourceCurrency === null || $sourceCurrency === '') {
            return [null, 'unknown_currency_skipped'];
        }

        if (strtoupper((string) $sourceCurrency) === $documentCurrency) {
            return [$price['cost'], 'known_same_currency'];
        }

        return [null, 'foreign_currency_skipped'];
    }

    /**
     * Convert an LCY reference/standard cost into the document currency.
     * LCY documents keep the value untouched (existing NGN behaviour).
     */
    private function normalizeReferenceCost(mixed $referenceCost, string $factor, bool $isLcyDocument): float|string|null
    {
        if ($referenceCost === null || $referenceCost === '') {
            return null;
        }

        if ($isLcyDocument) {
            return $referenceCost;
        }

        return PurchasingCurrency::fcyFromLcy($referenceCost, $factor);
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
            'cost' => $this->convertUnitCost((float) $price->direct_unit_cost, $price->unit_of_measure_code, $unitOfMeasure, $item),
            'currency_code' => $price->normalizedCurrencyCode(),
            'discount' => $price->line_discount_percent,
            'vendor_item_no' => $price->vendor_item_no,
        ];
    }

    /**
     * Get the most recent posted purchase invoice line cost for this vendor/item.
     */
    private function getLastDirectCost(
        Vendor $vendor,
        Item $item,
        \DateTime $date,
        ?int $businessId,
        string $documentCurrency,
        string $factor,
        bool $isLcyDocument
    ): ?float {
        $query = PostedPurchaseInvoiceLine::query()
            ->select('posted_purchase_invoice_lines.*')
            ->addSelect([
                'posted_purchase_invoices.currency_code as invoice_currency_code',
                'posted_purchase_invoices.currency_factor as invoice_currency_factor',
            ])
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

        $documentUnitCost = (float) ($lastEntry->unit_cost ?? 0);
        $lcyUnitCost = $lastEntry->unit_cost_lcy !== null ? (float) $lastEntry->unit_cost_lcy : null;
        $historicalCurrency = strtoupper((string) ($lastEntry->invoice_currency_code ?? ''));

        // Only reuse a historical document price when its FCY/LCY relationship
        // is internally valid. A historical foreign document stamped factor 1
        // with mirrored LCY values (the known NGN-as-USD defect) is NOT trusted.
        if (! $this->historicalUnitCostIsTrustworthy($historicalCurrency, $lastEntry->invoice_currency_factor, $documentUnitCost, $lcyUnitCost)) {
            return null;
        }

        // Same document currency: the posted document price is directly comparable.
        if ($historicalCurrency === $documentCurrency) {
            return $documentUnitCost;
        }

        // Historical LCY price requested as an LCY document: use the LCY value.
        if ($isLcyDocument) {
            return $lcyUnitCost;
        }

        // Historical LCY price requested in a foreign document: express the LCY
        // value in the document currency at the document rate.
        if ($historicalCurrency === PurchasingCurrency::LCY_CODE && $lcyUnitCost !== null) {
            return (float) PurchasingCurrency::fcyFromLcy($lcyUnitCost, $factor);
        }

        return null;
    }

    /**
     * Decide whether a historical posted unit cost is a trustworthy price source.
     *
     * LCY documents legitimately use factor 1 and LCY is the value. A foreign
     * document is trustworthy only when it carries a finite positive rate that
     * is not the semantically-LCY rate of exactly 1, and its LCY value equals
     * FCY x rate. Rates below 1 are valid for a foreign currency worth less than
     * one LCY unit and must not be rejected merely for being below 1, while the
     * known malformed signature (foreign currency stamped rate 1 with mirrored
     * LCY values) stays untrusted until historical repair.
     */
    private function historicalUnitCostIsTrustworthy(
        string $currency,
        mixed $factor,
        float $documentUnitCost,
        ?float $lcyUnitCost
    ): bool {
        if ($currency === '') {
            return false;
        }

        if ($currency === PurchasingCurrency::LCY_CODE) {
            return $lcyUnitCost !== null || $documentUnitCost > 0;
        }

        if ($factor === null || $factor === '' || ! is_numeric($factor)) {
            return false;
        }

        $factorValue = (float) $factor;

        if (! is_finite($factorValue) || $factorValue <= 0.0) {
            return false;
        }

        // A foreign document stamped with rate 1 carries no evidence of a real
        // conversion; the known NGN-labelled-as-USD defect shares this shape.
        if (abs($factorValue - 1.0) < 1e-9) {
            return false;
        }

        if ($lcyUnitCost === null) {
            return false;
        }

        $expectedLcy = $documentUnitCost * $factorValue;
        $tolerance = max(0.01, abs($expectedLcy) * 0.0001);

        return abs($lcyUnitCost - $expectedLcy) <= $tolerance;
    }

    /**
     * Determine the best price from available sources.
     *
     * Priority is deliberate rather than "lowest number wins": a negotiated
     * vendor price is authoritative, then the last posted document price, and
     * only then the item reference/standard cost (a converted planning value).
     * Comparing these as raw numbers would rank a converted NGN reference cost
     * against an FCY vendor price as if they were the same unit.
     */
    private function determineBestPrice(array $sources): array
    {
        if ($sources['purchase_price']) {
            return ['cost' => $sources['purchase_price']['cost'], 'source' => 'purchase_price'];
        }

        if ($sources['last_direct_cost'] !== null && $sources['last_direct_cost']) {
            return ['cost' => $sources['last_direct_cost'], 'source' => 'last_direct_cost'];
        }

        if ($sources['standard_cost'] !== null && $sources['standard_cost']) {
            return ['cost' => $sources['standard_cost'], 'source' => 'standard_cost'];
        }

        return ['cost' => 0, 'source' => 'none'];
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
