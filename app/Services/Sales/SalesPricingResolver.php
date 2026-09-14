<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\SalesLinePricingStatus;
use App\Models\Customer;
use App\Models\CustomerPriceOverride;
use App\Models\DiscountRule;
use App\Models\Item;
use App\Models\Location;
use App\Models\PricingMaster;
use App\Models\SalesPrice;
use App\Support\DocumentCurrency;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Authoritative runtime resolver for a NEW sales document line price.
 *
 * A numeric price is only meaningful together with the currency it is quoted
 * in. This resolver therefore never returns a bare number: every result carries
 * the price currency, its provenance, and whether it is a reference-derived
 * suggestion rather than a negotiated commercial price.
 *
 * Resolution priority (deterministic, NOT "lowest number wins"):
 *   1. customer-specific explicit-currency SalesPrice;
 *   2. customer-group explicit-currency SalesPrice;
 *   3. general/list explicit-currency SalesPrice;
 *   4. legacy `pricing_master` — LCY documents only (see trust rule below);
 *   5. legacy `customer_price_overrides` — LCY documents only;
 *   6. item-card reference price (LCY) — LCY documents only.
 *
 * Currency rules:
 *   - An explicit-currency price is eligible only when its currency equals the
 *     document currency. A different known currency is skipped, never converted
 *     or relabelled.
 *   - An ambiguous legacy row is never inferred to be the document currency.
 *   - The document currency must be explicit and valid. A missing, blank or
 *     invalid currency fails closed (`InvalidArgumentException`); the resolver
 *     never guesses NGN. An intentionally LCY-only caller must pass 'NGN'
 *     explicitly at its boundary.
 *   - A foreign-currency document with no eligible explicit-currency price
 *     fails safe (price 0 + `pricing_status = UNRESOLVED`) instead of borrowing
 *     an LCY reference value and mislabelling it as foreign.
 *
 * Legacy trust rule (`PricingMaster`): the table was created with
 * `currency_code` defaulting to USD, so a matching historical USD row is not
 * proof of an explicit USD commercial price. `PricingMaster` is therefore
 * excluded from automatic FCY pricing entirely and remains usable only for
 * LCY/NGN, where a non-NGN row cannot match. New FCY customer pricing belongs
 * in `sales_prices`.
 *
 * `price_lists` is a legacy/superseded pricing table with no runtime consumer
 * for commercial line pricing (only its own CRUD uses it). It is intentionally
 * NOT part of this resolver; `sales_prices` is the prospective explicit-currency
 * source.
 *
 * This resolver performs no FX conversion and does not populate any `*_lcy`
 * monetary column.
 */
class SalesPricingResolver
{
    public const SOURCE_SALES_PRICE_CUSTOMER = 'SALES_PRICE_CUSTOMER';

    public const SOURCE_SALES_PRICE_GROUP = 'SALES_PRICE_GROUP';

    public const SOURCE_SALES_PRICE_GENERAL = 'SALES_PRICE_GENERAL';

    public const SOURCE_CUSTOMER_OVERRIDE = 'CUSTOMER_OVERRIDE';

    public const SOURCE_ITEM_CARD = 'ITEM_CARD';

    public const SOURCE_MANUAL_REQUIRED = 'NONE';

    /**
     * @return array{
     *     unit_price: float,
     *     list_price: float,
     *     discount_amount: float,
     *     discount_percent: float,
     *     price_source: string,
     *     pricing_master_id: int|null,
     *     currency: string,
     *     currency_code: string,
     *     is_reference_derived: bool,
     *     price_record_id: int|null,
     *     requires_manual_price: bool,
     *     pricing_status: string,
     *     reference_price: float|null,
     *     reference_currency: string
     * }
     *
     * @throws InvalidArgumentException when the document currency is missing or invalid.
     */
    public function resolve(
        Item $item,
        ?Customer $customer,
        float $quantity,
        ?string $variantCode = null,
        ?string $uom = null,
        ?Location $location = null,
        ?DateTimeInterface $date = null,
        ?string $documentCurrency = null
    ): array {
        $date ??= now();
        $documentCurrency = $this->normalizeDocumentCurrency($documentCurrency);
        $isLcyDocument = DocumentCurrency::isLocalCurrency($documentCurrency);

        $selectedUom = $uom ?? $item->base_unit_of_measure;
        $conversionFactor = 1.0;

        if ($selectedUom) {
            $conversionFactor = (float) ($item->getConversionFactorForUom($selectedUom) ?: 1.0);
            $conversionFactor = $conversionFactor > 0 ? $conversionFactor : 1.0;
        }

        // The LCY reference price is planning data. It is exposed for context
        // but is only ever usable as a document price for an LCY document.
        $referencePrice = $item->unit_price !== null
            ? round((float) $item->unit_price * $conversionFactor, 4)
            : null;

        $tagged = $this->resolveTaggedSalesPrice(
            $item,
            $customer,
            $selectedUom,
            $date,
            $documentCurrency,
            $conversionFactor
        );

        $pricingMaster = $tagged === null
            ? $this->resolvePricingMaster($item, $customer, $quantity, $variantCode, $selectedUom, $location, $date, $documentCurrency)
            : null;

        // Ambiguous legacy override: no currency column exists, so it is only
        // safe when the document itself is LCY. It must never be relabelled as
        // a foreign price. Preserved for LCY documents as before.
        $customerOverride = null;
        if ($tagged === null && $pricingMaster === null && $isLcyDocument && $customer) {
            $customerOverride = CustomerPriceOverride::getPriceFor((int) $customer->id, (int) $item->id);
        }

        [$unitPrice, $listPrice, $priceSource, $recordId, $isReferenceDerived, $requiresManualPrice] =
            $this->selectPrice(
                item: $item,
                quantity: $quantity,
                tagged: $tagged,
                pricingMaster: $pricingMaster,
                customerOverride: $customerOverride,
                itemReferencePrice: $referencePrice,
                isLcyDocument: $isLcyDocument,
                conversionFactor: $conversionFactor
            );

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
            'pricing_master_id' => $pricingMaster?->id,
            'currency' => $documentCurrency,
            'currency_code' => $documentCurrency,
            'is_reference_derived' => $isReferenceDerived,
            'price_record_id' => $recordId,
            'requires_manual_price' => $requiresManualPrice,
            'pricing_status' => ($requiresManualPrice
                ? SalesLinePricingStatus::UNRESOLVED
                : SalesLinePricingStatus::RESOLVED)->value,
            'reference_price' => $referencePrice,
            'reference_currency' => DocumentCurrency::LCY_CODE,
        ];
    }

    /**
     * UI-safe wrapper around {@see resolve()} for interactive Sales line forms.
     *
     * The resolver itself fails closed by throwing when the document currency
     * is missing or invalid. A Filament line form must stay usable while that
     * misconfiguration exists, so this wrapper converts the fail-closed
     * condition into an explicit UNRESOLVED result (price 0, no provenance)
     * rather than silently defaulting the currency to NGN.
     *
     * Domain/services code should call {@see resolve()} directly so the failure
     * is explicit.
     *
     * @return array<string, mixed>
     */
    public function resolveOrUnresolved(
        Item $item,
        ?Customer $customer,
        float $quantity,
        ?string $variantCode = null,
        ?string $uom = null,
        ?Location $location = null,
        ?DateTimeInterface $date = null,
        ?string $documentCurrency = null
    ): array {
        try {
            return $this->resolve(
                item: $item,
                customer: $customer,
                quantity: $quantity,
                variantCode: $variantCode,
                uom: $uom,
                location: $location,
                date: $date,
                documentCurrency: $documentCurrency,
            );
        } catch (InvalidArgumentException) {
            return $this->unresolvedResult($item, $uom, $documentCurrency);
        }
    }

    /**
     * Fail-closed result shape used when the document currency itself is
     * missing/invalid. Carries no trusted price and no automatic provenance.
     *
     * @return array<string, mixed>
     */
    private function unresolvedResult(Item $item, ?string $uom, ?string $documentCurrency): array
    {
        $selectedUom = $uom ?? $item->base_unit_of_measure;
        $conversionFactor = $selectedUom
            ? (float) ($item->getConversionFactorForUom($selectedUom) ?: 1.0)
            : 1.0;
        $conversionFactor = $conversionFactor > 0 ? $conversionFactor : 1.0;

        $referencePrice = $item->unit_price !== null
            ? round((float) $item->unit_price * $conversionFactor, 4)
            : null;

        $currency = strtoupper(trim((string) $documentCurrency));

        return [
            'unit_price' => 0.0,
            'list_price' => 0.0,
            'discount_amount' => 0.0,
            'discount_percent' => 0.0,
            'price_source' => self::SOURCE_MANUAL_REQUIRED,
            'pricing_master_id' => null,
            'currency' => $currency,
            'currency_code' => $currency,
            'is_reference_derived' => false,
            'price_record_id' => null,
            'requires_manual_price' => true,
            'pricing_status' => SalesLinePricingStatus::UNRESOLVED->value,
            'reference_price' => $referencePrice,
            'reference_currency' => DocumentCurrency::LCY_CODE,
        ];
    }

    /**
     * Walk the SalesPrice scope hierarchy. Within a scope the most recently
     * effective row wins (`effective_from` desc, NULLs last), then the most
     * recently created (`id` desc), so the outcome is deterministic.
     *
     * @return array{amount: float, currency: string, source: string, record_id: int}|null
     */
    private function resolveTaggedSalesPrice(
        Item $item,
        ?Customer $customer,
        ?string $selectedUom,
        DateTimeInterface $date,
        string $documentCurrency,
        float $conversionFactor
    ): ?array {
        $scopes = [];

        if ($customer) {
            $scopes[] = [
                'source' => self::SOURCE_SALES_PRICE_CUSTOMER,
                'filter' => fn ($query) => $query->where('customer_id', $customer->id),
            ];

            if ($customer->customer_group_id) {
                $scopes[] = [
                    'source' => self::SOURCE_SALES_PRICE_GROUP,
                    'filter' => fn ($query) => $query
                        ->whereNull('customer_id')
                        ->where('customer_group_id', $customer->customer_group_id),
                ];
            }
        }

        $scopes[] = [
            'source' => self::SOURCE_SALES_PRICE_GENERAL,
            'filter' => fn ($query) => $query
                ->whereNull('customer_id')
                ->whereNull('customer_group_id'),
        ];

        foreach ($scopes as $scope) {
            $query = SalesPrice::query()
                ->active()
                ->where('item_id', $item->id)
                ->where('currency_code', $documentCurrency)
                ->where(function ($q) use ($date): void {
                    $q->whereNull('effective_from')
                        ->orWhere('effective_from', '<=', $date->format('Y-m-d'));
                })
                ->where(function ($q) use ($date): void {
                    $q->whereNull('effective_to')
                        ->orWhere('effective_to', '>=', $date->format('Y-m-d'));
                })
                ->where(function ($q) use ($selectedUom): void {
                    $q->whereNull('unit_of_measure_code');

                    if ($selectedUom) {
                        $q->orWhere('unit_of_measure_code', $selectedUom);
                    }
                })
                ->orderByRaw('CASE WHEN effective_from IS NULL THEN 1 ELSE 0 END')
                ->orderByDesc('effective_from')
                ->orderByDesc('id');

            $price = $scope['filter']($query)->first();

            if (! $price instanceof SalesPrice) {
                continue;
            }

            $amount = (float) $price->price;

            // A price recorded without a UOM is a base-UOM price; convert it to
            // the selected UOM exactly as the item-card reference path does.
            if ($price->unit_of_measure_code === null) {
                $amount *= $conversionFactor;
            }

            return [
                'amount' => round($amount, 4),
                'currency' => $documentCurrency,
                'source' => $scope['source'],
                'record_id' => (int) $price->id,
            ];
        }

        return null;
    }

    /**
     * Legacy price list. Excluded from automatic FCY pricing: its historical
     * `currency_code` defaulted to USD, so a matching USD row cannot prove an
     * explicit USD commercial price. It remains usable for LCY/NGN only, where
     * `PricingMaster::getBestPrice()` still enforces a strict currency match.
     */
    private function resolvePricingMaster(
        Item $item,
        ?Customer $customer,
        float $quantity,
        ?string $variantCode,
        ?string $selectedUom,
        ?Location $location,
        DateTimeInterface $date,
        string $documentCurrency
    ): ?PricingMaster {
        if (! DocumentCurrency::isLocalCurrency($documentCurrency)) {
            return null;
        }

        $pricingMaster = PricingMaster::getBestPrice(
            item: $item,
            customer: $customer,
            variantCode: $variantCode,
            uom: $selectedUom,
            quantity: $quantity,
            currency: $documentCurrency,
            location: $location,
            date: $date,
        );

        if (! $pricingMaster) {
            return null;
        }

        $masterCurrency = strtoupper(trim((string) $pricingMaster->currency_code));

        if ($masterCurrency !== $documentCurrency) {
            return null;
        }

        return $pricingMaster;
    }

    /**
     * Collapse the candidate sources into a single usable document price.
     *
     * @return array{0: float, 1: float, 2: string, 3: int|null, 4: bool, 5: bool}
     */
    private function selectPrice(
        Item $item,
        float $quantity,
        ?array $tagged,
        ?PricingMaster $pricingMaster,
        ?float $customerOverride,
        ?float $itemReferencePrice,
        bool $isLcyDocument,
        float $conversionFactor
    ): array {
        if ($tagged !== null) {
            return [
                (float) $tagged['amount'],
                (float) $tagged['amount'],
                (string) $tagged['source'],
                (int) $tagged['record_id'],
                false,
                false,
            ];
        }

        if ($pricingMaster !== null) {
            $calculation = $pricingMaster->calculatePrice(
                quantity: $quantity,
                baseCost: $item->unit_cost !== null ? (float) $item->unit_cost : null,
                listPrice: $item->unit_price !== null ? (float) $item->unit_price : null
            );

            return [
                (float) $calculation['final_price'],
                (float) $calculation['base_price'],
                (string) $pricingMaster->price_list_code,
                (int) $pricingMaster->id,
                false,
                false,
            ];
        }

        if ($customerOverride !== null) {
            $amount = round($customerOverride * $conversionFactor, 4);

            return [$amount, $amount, self::SOURCE_CUSTOMER_OVERRIDE, null, false, false];
        }

        // The item-card price is LCY/NGN reference data. It is a safe fallback
        // for an LCY document only. For a foreign document, borrowing this
        // number would silently mislabel NGN as foreign, so fail safe instead.
        if ($isLcyDocument && $itemReferencePrice !== null) {
            return [
                $itemReferencePrice,
                $itemReferencePrice,
                self::SOURCE_ITEM_CARD,
                null,
                true,
                false,
            ];
        }

        return [0.0, 0.0, self::SOURCE_MANUAL_REQUIRED, null, false, true];
    }

    /**
     * The document currency must be explicit and valid. There is deliberately
     * no NGN fallback here: guessing a currency would silently relabel a price.
     * Callers that are intentionally LCY-only must pass 'NGN' themselves.
     *
     * @throws InvalidArgumentException when the currency is missing, blank or not a valid ISO code.
     */
    private function normalizeDocumentCurrency(?string $documentCurrency): string
    {
        if ($documentCurrency === null || trim($documentCurrency) === '') {
            throw new InvalidArgumentException(
                'An explicit document currency is required to resolve a sales price.'
            );
        }

        return DocumentCurrency::normalizeCurrencyCode($documentCurrency);
    }
}
