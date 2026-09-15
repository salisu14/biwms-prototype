<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Classification of a currency-aware posting line.
 *
 * The classification exists so the kernel can distinguish an intentional
 * absence of document-currency economics from a caller that forgot to supply
 * them. In CURRENCY_AWARE mode every line must declare exactly one type.
 *
 * DOCUMENT_MONETARY carries document-currency debit/credit plus the matching
 * LCY economics (the relationship is validated against the intent factor).
 *
 * LCY_ONLY carries LCY economics only and intentionally has no document amount.
 * Inside a CURRENCY_AWARE intent it must additionally declare an allowlisted
 * PostingLcyOnlyReason (e.g. ROUNDING, VALUATION_ONLY), so it cannot be used as
 * a blanket escape hatch for document-monetary lines. In LCY_ONLY mode neither
 * a line type nor a reason is required.
 */
enum PostingIntentLineType: string
{
    case DOCUMENT_MONETARY = 'DOCUMENT_MONETARY';
    case LCY_ONLY = 'LCY_ONLY';
}
