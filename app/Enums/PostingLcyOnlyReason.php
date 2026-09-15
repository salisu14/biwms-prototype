<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Allowlisted reason a CURRENCY_AWARE posting line carries LCY economics only.
 *
 * A currency-aware line that is not DOCUMENT_MONETARY must explicitly declare
 * one of these reasons. This exists so an unresolvable document-currency line
 * cannot be smuggled past document-trace validation by an unconvincing
 * classification: payable, receivable, revenue and other document-monetary
 * lines must be DOCUMENT_MONETARY.
 *
 * The set is intentionally minimal. Future FX concepts (realized/unrealized FX)
 * must not be added until they have a defined accounting contract and their own
 * implementation phase.
 */
enum PostingLcyOnlyReason: string
{
    case ROUNDING = 'ROUNDING';
    case VALUATION_ONLY = 'VALUATION_ONLY';
}
