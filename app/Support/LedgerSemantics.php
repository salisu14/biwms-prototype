<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\SubledgerOpeningBalance;

/**
 * Shared prospective subledger semantics contract (Phase 3D-1).
 *
 * A ledger row may carry one of two base-column meanings:
 *
 *  - legacy / unclassified (version NULL or 1): the base monetary columns hold
 *    the document-currency amount, exactly as they always have;
 *  - version 2 ({@see self::VERSION_LCY_BASE}): the base monetary columns hold
 *    the LCY carrying amount and `original_*` hold the document (FCY) amount,
 *    with `LCY = FCY x currency_factor`.
 *
 * This helper is the single place that decides how a row is interpreted so the
 * Customer and Vendor implementations cannot drift apart. It never classifies
 * silently: a row is version 2 only when it says so explicitly.
 *
 * Amount conversions here are normalized to {@see DecimalPrecision::AMOUNT_SCALE}
 * (4 dp) because the ledger monetary columns are numeric(15,4); the factor is
 * normalized through the certified 6 dp contract in {@see DocumentCurrency}.
 */
final class LedgerSemantics
{
    /** Reserved for a future explicit historical classification pass. */
    public const VERSION_LEGACY = 1;

    /** Base columns are LCY carrying values; `original_*` are document (FCY). */
    public const VERSION_LCY_BASE = 2;

    /** The document type used by subledger opening balances. */
    public const OPENING_BALANCE_DOCUMENT_TYPE = 'OPENING_BALANCE';

    public static function isVersionTwo(mixed $version): bool
    {
        return $version !== null && (int) $version === self::VERSION_LCY_BASE;
    }

    /**
     * Normalize a factor through the certified shared contract (6 dp, finite,
     * strictly positive; NGN resolves to 1; a missing foreign factor fails).
     */
    public static function normalizeFactor(?string $currencyCode, mixed $factor): string
    {
        return DocumentCurrency::factorFor($currencyCode, $factor);
    }

    /**
     * FCY -> LCY at the ledger amount scale (4 dp).
     */
    public static function lcyFromDocument(mixed $documentAmount, mixed $factor): string
    {
        return DecimalMath::toScale(
            DecimalMath::mul($documentAmount, DecimalMath::of($factor), DecimalPrecision::AMOUNT_SCALE),
            DecimalPrecision::AMOUNT_SCALE,
        );
    }

    /**
     * True when the base monetary columns of a row are known to be LCY.
     *
     * Version 2 and opening balances qualify. A legacy NGN row does too (its
     * LCY context is well defined and the factor is 1). Everything else stays
     * in document currency and must be converted through a factor.
     */
    public static function hasLcyBase(
        mixed $version,
        ?string $documentType,
        mixed $factor,
        ?string $currencyCode,
    ): bool {
        if (self::isVersionTwo($version)) {
            return true;
        }

        if (strtoupper(trim((string) $documentType)) === self::OPENING_BALANCE_DOCUMENT_TYPE) {
            return true;
        }

        if (strtoupper(trim((string) $currencyCode)) === DocumentCurrency::LCY_CODE) {
            return true;
        }

        return $factor !== null && $factor !== '' && DecimalMath::compare($factor, '1') === 0;
    }

    /**
     * True when {@see self::toLcy()} can produce a trusted LCY value.
     *
     * Version-2, opening-balance, NGN and factor-1 rows are trusted as LCY;
     * any other row needs an explicit, non-zero factor. A row with no usable
     * factor is ambiguous: callers must exclude it (or mark any total derived
     * from it as non-authoritative) rather than fabricate an LCY value. A zero
     * factor is treated as unusable here, matching the SQL expressions.
     *
     * This is the single predicate behind the version-2 running-balance
     * contract and the LCY aggregation expressions, so a row can never be
     * treated as trusted by one consumer and ambiguous by another.
     */
    public static function hasTrustedLcyInterpretation(
        mixed $version,
        ?string $documentType,
        mixed $factor,
        ?string $currencyCode,
    ): bool {
        if (self::hasLcyBase($version, $documentType, $factor, $currencyCode)) {
            return true;
        }

        return $factor !== null && $factor !== '' && DecimalMath::compare($factor, '0') !== 0;
    }

    /**
     * Interpret a base monetary value as LCY.
     *
     * Returns null when the row cannot be converted under a trusted rule; the
     * caller must report/exclude it rather than fabricate an LCY value.
     */
    public static function toLcy(
        mixed $amount,
        mixed $version,
        ?string $documentType,
        mixed $factor,
        ?string $currencyCode,
    ): ?string {
        if ($amount === null) {
            return null;
        }

        if (self::hasLcyBase($version, $documentType, $factor, $currencyCode)) {
            return DecimalMath::toScale($amount, DecimalPrecision::AMOUNT_SCALE);
        }

        if (! self::hasTrustedLcyInterpretation($version, $documentType, $factor, $currencyCode)) {
            return null;
        }

        return self::lcyFromDocument($amount, $factor);
    }

    /**
     * Signed-direction rule shared by the ledger models.
     *
     * Debit entries are negative exposure, credit entries positive; when a row
     * carries neither column the sign of the signed `amount` decides.
     */
    public static function signedRemaining(
        bool $isDebitEntry,
        bool $isCreditEntry,
        mixed $signedAmount,
        float $remaining,
    ): float {
        if ($remaining === 0.0) {
            return 0.0;
        }

        if ($isCreditEntry) {
            return abs($remaining);
        }

        if ($isDebitEntry) {
            return -abs($remaining);
        }

        return (float) $signedAmount >= 0 ? abs($remaining) : -abs($remaining);
    }

    /**
     * SQL: the LCY carrying value of `remaining_amount` (unsigned).
     *
     * Legacy rows keep their existing trusted interpretation (opening balances
     * are already LCY; NGN and factor-1 rows are identity; other legacy rows
     * convert through their factor). Rows that cannot be converted yield NULL so
     * SUM() ignores them instead of mixing units.
     */
    public static function lcyRemainingSql(string $ref): string
    {
        $lcy = "'".DocumentCurrency::LCY_CODE."'";
        $opening = "'".SubledgerOpeningBalance::class."'";

        return 'CASE'
            ." WHEN {$ref}.ledger_semantics_version = ".self::VERSION_LCY_BASE." THEN ABS({$ref}.remaining_amount)"
            ." WHEN {$ref}.source_type = {$opening} THEN ABS({$ref}.remaining_amount)"
            ." WHEN UPPER(COALESCE({$ref}.currency_code, '')) = {$lcy} THEN ABS({$ref}.remaining_amount)"
            ." WHEN {$ref}.currency_factor IS NOT NULL AND {$ref}.currency_factor <> 0 THEN ABS({$ref}.remaining_amount * {$ref}.currency_factor)"
            .' ELSE NULL END';
    }

    /**
     * SQL: the LCY carrying value of the signed `amount` column.
     */
    public static function lcyAmountSql(string $ref): string
    {
        $lcy = "'".DocumentCurrency::LCY_CODE."'";
        $opening = "'".SubledgerOpeningBalance::class."'";

        return 'CASE'
            ." WHEN {$ref}.ledger_semantics_version = ".self::VERSION_LCY_BASE." THEN {$ref}.amount"
            ." WHEN {$ref}.source_type = {$opening} THEN {$ref}.amount"
            ." WHEN UPPER(COALESCE({$ref}.currency_code, '')) = {$lcy} THEN {$ref}.amount"
            ." WHEN {$ref}.currency_factor IS NOT NULL AND {$ref}.currency_factor <> 0 THEN {$ref}.amount * {$ref}.currency_factor"
            .' ELSE NULL END';
    }

    /**
     * SQL: the LCY carrying value of the net posting (`credit - debit`).
     */
    public static function lcyNetSql(string $ref): string
    {
        $lcy = "'".DocumentCurrency::LCY_CODE."'";
        $opening = "'".SubledgerOpeningBalance::class."'";

        return 'CASE'
            ." WHEN {$ref}.ledger_semantics_version = ".self::VERSION_LCY_BASE." THEN {$ref}.credit_amount - {$ref}.debit_amount"
            ." WHEN {$ref}.source_type = {$opening} THEN {$ref}.credit_amount - {$ref}.debit_amount"
            ." WHEN UPPER(COALESCE({$ref}.currency_code, '')) = {$lcy} THEN {$ref}.credit_amount - {$ref}.debit_amount"
            ." WHEN {$ref}.currency_factor IS NOT NULL AND {$ref}.currency_factor <> 0 THEN ({$ref}.credit_amount - {$ref}.debit_amount) * {$ref}.currency_factor"
            .' ELSE NULL END';
    }

    /**
     * SQL: signed LCY open exposure for a ledger row.
     *
     * Debit entries contribute negative exposure, credit entries positive. This
     * is the open-item control measure; it deliberately does not use the
     * immutable original debit/credit postings.
     */
    public static function signedLcyRemainingSql(string $ref): string
    {
        $lcy = self::lcyRemainingSql($ref);

        return "CASE WHEN {$ref}.credit_amount > 0 THEN {$lcy} ELSE -({$lcy}) END";
    }
}
