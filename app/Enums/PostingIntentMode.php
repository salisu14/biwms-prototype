<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Posting intent currency mode.
 *
 * LCY_ONLY is the backward-compatible default: line debit/credit amounts are
 * authoritative local-currency (NGN) economics and the kernel performs no
 * conversion, even when informational currency metadata is present.
 *
 * CURRENCY_AWARE is an explicit opt-in: the caller must supply an explicit
 * document currency, an explicit recognition factor, and explicit
 * document-currency trace per document-monetary line. The kernel validates the
 * caller-supplied relationship and never derives LCY from FCY itself.
 */
enum PostingIntentMode: string
{
    case LCY_ONLY = 'LCY_ONLY';
    case CURRENCY_AWARE = 'CURRENCY_AWARE';

    public function isCurrencyAware(): bool
    {
        return $this === self::CURRENCY_AWARE;
    }
}
