<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Exceptions\BusinessException;
use App\Support\DocumentCurrency;
use InvalidArgumentException;

/**
 * Centralized currency-context normalization and validation for Sales documents.
 *
 * This service establishes trustworthy currency context only. It deliberately
 * performs no exchange-rate lookup, no pricing, no line/total calculation, no
 * G/L posting, and no subledger or payment work.
 *
 * Conventions (shared with the certified DocumentCurrency helper):
 * - LCY is NGN and always resolves to factor 1;
 * - a foreign currency requires an explicit, finite, strictly positive factor
 *   expressed as LCY per 1 FCY;
 * - an explicitly supplied factor of exactly 1 for a foreign currency is VALID
 *   (real currency pairs can be at parity). The defect this service prevents is
 *   a FABRICATED factor 1, i.e. a missing factor silently becoming 1;
 * - an unresolved foreign factor fails closed instead of silently becoming 1.
 *
 * Two distinct semantics exist and must not be conflated:
 *
 * - {@see resolveForNewDocument()} is prospective: a brand-new Sales document
 *   with no currency may safely default to the LCY (NGN) context.
 * - {@see resolveForExistingDocument()} is historical/testimonial: an existing
 *   document with a missing or ambiguous currency must NEVER be silently
 *   reinterpreted as LCY. It fails closed instead.
 */
final class SalesDocumentCurrencyService
{
    public const LCY_CODE = DocumentCurrency::LCY_CODE;

    /**
     * Resolve the currency context for a NEW Sales document.
     *
     * A missing/blank currency resolves to the local currency (NGN). The local
     * currency always resolves to factor 1. A foreign currency requires an
     * explicit, finite, strictly positive factor and fails closed otherwise.
     *
     * @return array{currency_code: string, currency_factor: string}
     *
     * @throws BusinessException when the code is invalid or a foreign factor is missing/invalid.
     */
    public function resolveForNewDocument(?string $currencyCode, mixed $factor = null): array
    {
        $code = $this->normalizeCurrencyCode($currencyCode) ?? DocumentCurrency::LCY_CODE;

        return $this->resolveFactor($code, $factor);
    }

    /**
     * Resolve the currency context of an EXISTING Sales document.
     *
     * A missing/blank currency is NOT defaulted: reinterpreting unknown
     * historical context as NGN would rewrite the meaning of stored data. It
     * fails closed instead.
     *
     * @return array{currency_code: string, currency_factor: string}
     *
     * @throws BusinessException when the currency is missing, invalid, or a foreign factor is missing/invalid.
     */
    public function resolveForExistingDocument(?string $currencyCode, mixed $factor = null): array
    {
        $code = $this->normalizeCurrencyCode($currencyCode);

        if ($code === null) {
            throw new BusinessException(
                'This sales document has no authoritative currency code and cannot be reinterpreted as local currency.',
                title: 'Currency context missing',
                field: 'currency_code',
                codeIdentifier: 'sales_currency_context_missing',
            );
        }

        return $this->resolveFactor($code, $factor);
    }

    public function isForeign(string $currencyCode): bool
    {
        return ! DocumentCurrency::isLocalCurrency($currencyCode);
    }

    public function isLocal(?string $currencyCode): bool
    {
        return $currencyCode !== null
            && trim($currencyCode) !== ''
            && DocumentCurrency::isLocalCurrency($currencyCode);
    }

    /**
     * @return array{currency_code: string, currency_factor: string}
     */
    private function resolveFactor(string $currencyCode, mixed $factor): array
    {
        try {
            $resolvedFactor = DocumentCurrency::factorFor($currencyCode, $factor);
        } catch (InvalidArgumentException $exception) {
            throw new BusinessException(
                "A valid positive exchange-rate factor is required for {$currencyCode} sales documents.",
                title: 'Exchange-rate factor required',
                field: 'currency_factor',
                codeIdentifier: 'sales_currency_factor_required',
                previous: $exception,
            );
        }

        return [
            'currency_code' => $currencyCode,
            'currency_factor' => $resolvedFactor,
        ];
    }

    /**
     * Normalize and validate an ISO currency code. A missing/blank code returns
     * null so the caller decides whether defaulting (new) or failing closed
     * (existing) is appropriate.
     *
     * @throws BusinessException when a non-empty code is not a valid ISO code.
     */
    private function normalizeCurrencyCode(?string $currencyCode): ?string
    {
        if ($currencyCode === null || trim($currencyCode) === '') {
            return null;
        }

        try {
            return DocumentCurrency::normalizeCurrencyCode($currencyCode);
        } catch (InvalidArgumentException $exception) {
            throw new BusinessException(
                'A valid three-letter ISO currency code is required for sales documents.',
                title: 'Invalid currency code',
                field: 'currency_code',
                codeIdentifier: 'sales_currency_code_invalid',
                previous: $exception,
            );
        }
    }
}
