<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Presentation-only currency labelling.
 *
 * This helper never performs conversion, rounding, or persistence: it only
 * maps currency codes to display labels. Two rules apply everywhere:
 *
 *  - document/commercial amounts display their own document currency;
 *  - LCY/accounting amounts display the company local currency (NGN).
 *
 * An unknown (null/blank) currency is never relabelled as another currency.
 */
final class CurrencyPresentation
{
    /**
     * Company local currency (LCY) code.
     */
    public static function lcy(): string
    {
        return DocumentCurrency::LCY_CODE;
    }

    /**
     * Configured application default currency.
     *
     * Used only where no document currency exists; never to relabel a document
     * amount whose currency is known.
     */
    public static function default(): string
    {
        return strtoupper(trim((string) config('app.default_currency', self::lcy())));
    }

    /**
     * Normalize a document currency code, or return null when it is unknown.
     *
     * Fail-closed: a missing code stays unknown rather than defaulting to LCY.
     */
    public static function document(?string $code): ?string
    {
        $normalized = strtoupper(trim((string) $code));

        return $normalized !== '' ? $normalized : null;
    }

    /**
     * Known document currency code, or the application default currency when the
     * document carries none.
     *
     * Never returns Laravel's built-in USD default, so an unlabelled document
     * can never be silently presented as US Dollars.
     */
    public static function documentOrDefault(?string $code): string
    {
        return self::document($code) ?? self::default();
    }

    /**
     * Currency symbol for form affixes.
     *
     * Unknown codes render the code itself; a missing code renders a neutral
     * placeholder so it can never be mistaken for USD or NGN.
     */
    public static function symbol(?string $code): string
    {
        $normalized = self::document($code);

        return match ($normalized) {
            null => '—',
            'NGN' => '₦',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            default => $normalized,
        };
    }
}
