<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Canonical purchasing currency conversion helpers.
 *
 * Convention: the exchange-rate factor is LCY per 1 FCY.
 *
 *     LCY = FCY x factor
 *     FCY = LCY / factor
 *
 * For LCY documents the factor is 1, so FCY and LCY amounts are identical.
 * These helpers only convert amounts; they never decide which column a value
 * belongs in. Document/FCY prices remain distinct from item reference costs.
 */
final class PurchasingCurrency
{
    public const LCY_CODE = 'NGN';

    private const FACTOR_SCALE = 6;

    public static function factorFor(?string $currencyCode, mixed $factor): string
    {
        $currencyCode = strtoupper(trim((string) ($currencyCode ?: self::LCY_CODE)));

        if ($factor === null || $factor === '') {
            if ($currencyCode === self::LCY_CODE) {
                return DecimalMath::toScale('1', self::FACTOR_SCALE);
            }

            throw new InvalidArgumentException('An explicit positive exchange-rate factor is required for foreign-currency purchasing documents.');
        }

        return self::normalizeFactor($factor);
    }

    public static function normalizeFactor(mixed $factor): string
    {
        if ($factor === null || $factor === '') {
            throw new InvalidArgumentException('An explicit positive exchange-rate factor is required.');
        }

        $normalized = DecimalMath::toScale($factor, self::FACTOR_SCALE);

        if (! DecimalMath::isPositive($normalized)) {
            throw new InvalidArgumentException('A positive exchange-rate factor is required.');
        }

        return $normalized;
    }

    public static function isLcyFactor(mixed $factor, ?string $currencyCode = null): bool
    {
        return DecimalMath::compare(self::factorFor($currencyCode, $factor), '1') === 0;
    }

    public static function lcyFromFcy(mixed $fcy, mixed $factor): string
    {
        return DecimalMath::currency(
            DecimalMath::mul($fcy, self::normalizeFactor($factor), DecimalPrecision::AMOUNT_SCALE)
        );
    }

    /**
     * LCY amount for accounting (G/L postings and inventory valuation).
     *
     * This is the single deterministic rule the currency-aware posting boundary
     * validates against: LCY = round(FCY x factor, 2, HALF_UP). It is
     * deliberately distinct from {@see self::lcyFromFcy()}, which rounds at the
     * wider amount scale before reducing to the currency scale and can differ by
     * one minor unit through double rounding. Any value that will be posted to
     * the G/L or validated by the posting kernel must use this rule so the
     * caller and the kernel can never disagree.
     */
    public static function accountingLcy(mixed $fcy, mixed $factor): string
    {
        return DecimalMath::currency(
            DecimalMath::mul($fcy, self::normalizeFactor($factor), DecimalPrecision::CURRENCY_SCALE)
        );
    }

    public static function fcyFromLcy(mixed $lcy, mixed $factor): string
    {
        return DecimalMath::currency(
            DecimalMath::div($lcy, self::normalizeFactor($factor), DecimalPrecision::AMOUNT_SCALE)
        );
    }
}
