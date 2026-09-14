<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Exceptions\BusinessException;
use App\Models\SalesInvoice;
use App\Models\SalesInvoiceLine;

/**
 * Domain guard protecting direct Sales Invoice posting from unresolved pricing.
 *
 * A direct invoice line can be auto-priced by `SalesPricingResolver`; when no
 * trusted explicit-currency price exists the resolver returns an UNRESOLVED
 * zero. That state must never reach posting as a free sale.
 *
 * Linked Sales Order invoices are not special-cased here: their lines carry the
 * Sales Order line's own pricing status/price, so the same rule applies.
 */
final class SalesInvoicePricingGuard
{
    /**
     * @throws BusinessException when any line has unresolved pricing.
     */
    public function assertCanPost(SalesInvoice $invoice): void
    {
        $invoice->loadMissing('lines');

        $isForeignDocument = LinePricingStateEvaluator::isExplicitlyForeignCurrency($invoice->currency_code);

        $unresolved = $invoice->lines
            ->filter(fn (SalesInvoiceLine $line): bool => LinePricingStateEvaluator::isUnresolved(
                $line->pricing_status,
                $isForeignDocument,
                (float) ($line->unit_price ?? 0),
            ))
            ->map(fn (SalesInvoiceLine $line): string => $line->item?->item_code ?? ('line '.$line->id))
            ->values()
            ->all();

        if ($unresolved === []) {
            return;
        }

        throw new BusinessException(
            'Sales invoice '.$invoice->invoice_number.' has unresolved pricing on: '.implode(', ', $unresolved)
                .'. Enter an explicit document-currency price before posting.',
            title: 'Sales invoice price unresolved',
            field: 'lines',
            codeIdentifier: 'sales_invoice_price_unresolved',
        );
    }
}
