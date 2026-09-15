<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Support\DecimalMath;
use App\Support\DecimalPrecision;
use App\Support\DocumentCurrency;
use InvalidArgumentException;

/**
 * Deterministic Sales document monetary derivation (document/FCY -> LCY).
 *
 * Contract: `LCY = FCY x currency_factor`, where `currency_factor` is LCY units
 * per 1 document-currency unit. Commercial/document amounts stay authoritative
 * in the document currency; the `*_lcy` columns are derived recognition
 * equivalents only.
 *
 * This helper is deliberately narrow and side-effect free: it performs no
 * database access, no exchange-rate lookup, no pricing, no rounding of the
 * source (FCY) value, and no posting. `DocumentCurrency` remains the
 * authoritative currency/factor validator.
 *
 * Rounding: the LCY result is rounded to the caller-supplied monetary scale
 * (default: the BIWMS amount scale used by Sales Order and posted documents;
 * direct Sales Invoice/Credit Memo columns use the 2-decimal currency scale).
 * A header LCY total must therefore be the sum of the persisted/rounded line
 * LCY values, never a re-derivation from raw floats with a different method.
 *
 * Fail-closed: an unknown document currency, or a foreign currency whose factor
 * is missing/invalid, yields NULL rather than a fabricated LCY value. This
 * keeps legacy/ambiguous rows unresolved instead of inventing money.
 */
final class SalesDocumentMonetaryCalculator
{
    /**
     * LCY equivalent of a single document (FCY) amount, or NULL when the
     * currency/factor context cannot authorise a conversion.
     */
    public function deriveLcy(
        ?string $currencyCode,
        mixed $factor,
        mixed $documentAmount,
        int $scale = DecimalPrecision::AMOUNT_SCALE,
    ): ?string {
        if ($documentAmount === null) {
            return null;
        }

        $code = strtoupper(trim((string) $currencyCode));

        if ($code === '') {
            // Unknown historical currency: never infer LCY.
            return null;
        }

        if (DocumentCurrency::isLocalCurrency($code)) {
            // NGN is the local currency; the LCY equivalent is the same number.
            return DecimalMath::toScale($documentAmount, $scale);
        }

        try {
            $normalizedFactor = DocumentCurrency::normalizeFactor($factor);
        } catch (InvalidArgumentException) {
            // Missing/zero/negative/blank foreign factor: unresolved, leave NULL.
            return null;
        }

        // Exactly one multiplication by the document factor.
        return DecimalMath::mul($documentAmount, $normalizedFactor, $scale);
    }

    /**
     * Derive a map of LCY components from a map of document/FCY components.
     *
     * @param  array<string, mixed>  $components  target LCY column => document amount
     * @return array<string, string|null>
     */
    public function deriveComponents(
        ?string $currencyCode,
        mixed $factor,
        array $components,
        int $scale = DecimalPrecision::AMOUNT_SCALE,
    ): array {
        $derived = [];

        foreach ($components as $target => $documentAmount) {
            $derived[$target] = $this->deriveLcy($currencyCode, $factor, $documentAmount, $scale);
        }

        return $derived;
    }

    /**
     * Aggregate a document-total component from persisted line LCY values.
     *
     * Returns NULL when every contributing line is unresolved, so a document
     * whose lines carry no authorised LCY value does not gain a fabricated 0.
     *
     * @param  iterable<mixed>  $lineValues
     */
    public function total(iterable $lineValues, int $scale = DecimalPrecision::AMOUNT_SCALE): ?string
    {
        $sum = null;

        foreach ($lineValues as $value) {
            if ($value === null) {
                continue;
            }

            $sum = $sum === null
                ? DecimalMath::toScale($value, $scale)
                : DecimalMath::add($sum, $value, $scale);
        }

        return $sum;
    }
}
