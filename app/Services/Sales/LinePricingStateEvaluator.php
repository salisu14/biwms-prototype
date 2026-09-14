<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Enums\SalesLinePricingStatus;
use App\Support\DocumentCurrency;

/**
 * Single source of truth for the durable line-pricing compatibility rule.
 *
 * Applied consistently to Sales Order, direct Sales Invoice, and direct/unlinked
 * Sales Credit Memo lines:
 *
 *   - UNRESOLVED            -> unresolved (block);
 *   - RESOLVED              -> resolved (allow);
 *   - MANUAL / NULL legacy  -> unresolved only when the document is explicitly
 *                              foreign AND the line price is not positive.
 *
 * A foreign-currency zero is never treated as an implicit free-of-charge sale,
 * because BIWMS has no controlled free-of-charge workflow in this phase. LCY and
 * unknown/legacy contexts are left to existing behaviour so stored historical
 * data is never retroactively blocked.
 */
final class LinePricingStateEvaluator
{
    public static function isUnresolved(
        ?SalesLinePricingStatus $status,
        bool $isForeignDocument,
        float $unitPrice
    ): bool {
        if ($status === SalesLinePricingStatus::UNRESOLVED) {
            return true;
        }

        if ($status === SalesLinePricingStatus::RESOLVED) {
            return false;
        }

        return $isForeignDocument && $unitPrice <= 0;
    }

    /**
     * An order/document currency is "explicitly foreign" only when it is present
     * and not the local currency. NULL/blank (legacy/unknown) is not foreign.
     */
    public static function isExplicitlyForeignCurrency(?string $currencyCode): bool
    {
        if ($currencyCode === null || trim($currencyCode) === '') {
            return false;
        }

        return ! DocumentCurrency::isLocalCurrency($currencyCode);
    }
}
