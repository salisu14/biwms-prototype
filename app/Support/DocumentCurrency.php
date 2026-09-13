<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Shared low-level multi-currency mathematics and validation.
 *
 * Convention: the exchange-rate factor is LCY per 1 FCY.
 *
 *     LCY = FCY x factor
 *     FCY = LCY / factor
 *
 * For the local currency (NGN) the factor resolves to 1, so document and LCY
 * amounts are identical. Foreign-currency documents require an explicit,
 * finite, strictly positive factor; a missing foreign factor is unresolved and
 * fails closed rather than silently becoming 1.
 *
 * This helper is deterministic only: it performs no exchange-rate lookup and
 * no database access. Rate selection and authorization remain domain concerns.
 *
 * For every valid input it mirrors the certified `PurchasingCurrency` semantics
 * so the two are proven equivalent by characterization tests; `PurchasingCurrency`
 * remains the Purchasing wrapper and is not required to migrate in this phase.
 *
 * The one intentional difference is a missing currency code: PurchasingCurrency
 * legacy-infers LCY, whereas this helper fails closed rather than guessing that
 * an unresolved currency is local (see factorFor/isLcyFactor).
 */
final class DocumentCurrency
{
    public const LCY_CODE = 'NGN';

    private const FACTOR_SCALE = 6;

    /**
     * Normalize and validate an ISO currency code.
     *
     * @throws InvalidArgumentException when the code is empty or not a
     *                                  three-letter alphabetic code.
     */
    public static function normalizeCurrencyCode(?string $code): string
    {
        $normalized = strtoupper(trim((string) $code));

        if (preg_match('/^[A-Z]{3}$/', $normalized) !== 1) {
            throw new InvalidArgumentException('A valid three-letter ISO currency code is required.');
        }

        return $normalized;
    }

    /**
     * True only for the local currency (NGN). Never throws.
     */
    public static function isLocalCurrency(?string $code): bool
    {
        return strtoupper(trim((string) $code)) === self::LCY_CODE;
    }

    /**
     * Normalize a required exchange-rate factor.
     *
     * The factor must be finite and strictly greater than zero. A null/blank
     * factor is rejected: this method is context-free and must never silently
     * produce 1.
     */
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

    /**
     * Resolve the factor for a document currency.
     *
     * NGN always resolves to 1 (LCY context is well defined). Any other
     * currency requires an explicit valid factor. A missing/blank currency code
     * is rejected rather than inferred as LCY.
     */
    public static function factorFor(?string $currencyCode, mixed $factor): string
    {
        $code = self::normalizeCurrencyCode($currencyCode);

        if ($code === self::LCY_CODE) {
            return DecimalMath::toScale('1', self::FACTOR_SCALE);
        }

        return self::normalizeFactor($factor);
    }

    /**
     * True when the resolved factor is exactly 1 for the given currency.
     *
     * A null/blank currency code is NOT treated as LCY. When the code is
     * missing, the supplied factor is normalized and compared to 1; if the
     * factor is also missing this fails closed instead of inferring LCY.
     *
     * This is intentionally stricter than PurchasingCurrency::isLcyFactor,
     * which legacy-infers LCY from a missing currency code.
     */
    public static function isLcyFactor(mixed $factor, ?string $currencyCode = null): bool
    {
        if (strtoupper(trim((string) $currencyCode)) === self::LCY_CODE) {
            return true;
        }

        return DecimalMath::compare(self::normalizeFactor($factor), '1') === 0;
    }

    /**
     * Convert a document (FCY) amount to its LCY equivalent.
     *
     * NGN documents are returned unchanged (normalized to currency scale);
     * foreign documents are multiplied by the explicit factor.
     */
    public static function toLcy(mixed $documentAmount, ?string $currencyCode, mixed $factor): string
    {
        $code = self::normalizeCurrencyCode($currencyCode);

        if ($code === self::LCY_CODE) {
            return DecimalMath::currency($documentAmount);
        }

        return self::lcyFromFcy($documentAmount, self::normalizeFactor($factor));
    }

    /**
     * Convert an LCY amount to its document (FCY) equivalent.
     *
     * NGN documents are returned unchanged (normalized to currency scale);
     * foreign documents are divided by the explicit factor.
     */
    public static function fromLcy(mixed $lcyAmount, ?string $currencyCode, mixed $factor): string
    {
        $code = self::normalizeCurrencyCode($currencyCode);

        if ($code === self::LCY_CODE) {
            return DecimalMath::currency($lcyAmount);
        }

        return self::fcyFromLcy($lcyAmount, self::normalizeFactor($factor));
    }

    /**
     * Pure FCY -> LCY multiplication. Ordered identically to
     * PurchasingCurrency::lcyFromFcy so the two are directly comparable.
     */
    public static function lcyFromFcy(mixed $fcy, mixed $factor): string
    {
        return DecimalMath::currency(
            DecimalMath::mul($fcy, self::normalizeFactor($factor), DecimalPrecision::AMOUNT_SCALE)
        );
    }

    /**
     * Pure LCY -> FCY division. Ordered identically to
     * PurchasingCurrency::fcyFromLcy so the two are directly comparable.
     */
    public static function fcyFromLcy(mixed $lcy, mixed $factor): string
    {
        return DecimalMath::currency(
            DecimalMath::div($lcy, self::normalizeFactor($factor), DecimalPrecision::AMOUNT_SCALE)
        );
    }
}
