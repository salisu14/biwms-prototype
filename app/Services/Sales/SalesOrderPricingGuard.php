<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Exceptions\BusinessException;
use App\Models\SalesOrder;
use App\Models\SalesOrderLine;

/**
 * Central invariant protecting Sales financial/fulfilment transitions from an
 * unresolved line price.
 *
 * A Sales line is unresolved when it is explicitly marked UNRESOLVED, or — for
 * an explicitly foreign-currency document — when a legacy/NULL line carries no
 * positive document price. Zero is never accepted as an implicit substitute for
 * a missing foreign price: an intentional zero must be an explicit MANUAL price.
 *
 * This guard is deliberately one reusable check rather than duplicate UI
 * validation, and is invoked from the model transitions that move an order into
 * an approved/shipped/invoiced state.
 */
final class SalesOrderPricingGuard
{
    /**
     * @throws BusinessException when any line on the order has unresolved pricing.
     */
    public function assertCanProgress(SalesOrder $order): void
    {
        $order->loadMissing('lines');

        $unresolved = $order->lines
            ->filter(fn (SalesOrderLine $line): bool => $this->isUnresolved($line, $order))
            ->map(fn (SalesOrderLine $line): string => $line->item_code ?? ('line '.$line->line_number))
            ->values()
            ->all();

        if ($unresolved === []) {
            return;
        }

        throw new BusinessException(
            'Sales order '.$order->order_number.' has unresolved pricing on: '.implode(', ', $unresolved)
                .'. Enter an explicit document-currency price before proceeding.',
            title: 'Sales price unresolved',
            field: 'lines',
            codeIdentifier: 'sales_line_price_unresolved',
        );
    }

    public function isUnresolved(SalesOrderLine $line, SalesOrder $order): bool
    {
        return LinePricingStateEvaluator::isUnresolved(
            $line->pricing_status,
            LinePricingStateEvaluator::isExplicitlyForeignCurrency($order->currency_code),
            (float) ($line->unit_price ?? 0),
        );
    }
}
