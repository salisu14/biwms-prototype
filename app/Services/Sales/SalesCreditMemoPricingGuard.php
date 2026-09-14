<?php

declare(strict_types=1);

namespace App\Services\Sales;

use App\Exceptions\BusinessException;
use App\Models\SalesCreditMemo;
use App\Models\SalesCreditMemoLine;

/**
 * Domain guard protecting direct/unlinked Sales Credit Memo posting from
 * unresolved pricing.
 *
 * Lines inherited from a posted Sales Invoice (`posted_sales_invoice_line_id`)
 * preserve the original invoice economics and are never re-resolved or blocked
 * by this guard. Only direct/unlinked lines are evaluated.
 */
final class SalesCreditMemoPricingGuard
{
    /**
     * @throws BusinessException when a direct/unlinked line has unresolved pricing.
     */
    public function assertCanPost(SalesCreditMemo $memo): void
    {
        $memo->loadMissing('items');

        $isForeignDocument = LinePricingStateEvaluator::isExplicitlyForeignCurrency($memo->currency_code);

        $unresolved = $memo->items
            ->reject(fn (SalesCreditMemoLine $line): bool => $line->posted_sales_invoice_line_id !== null)
            ->filter(fn (SalesCreditMemoLine $line): bool => LinePricingStateEvaluator::isUnresolved(
                $line->pricing_status,
                $isForeignDocument,
                (float) ($line->unit_price ?? 0),
            ))
            ->map(fn (SalesCreditMemoLine $line): string => $line->item?->item_code ?? ('line '.$line->line_no))
            ->values()
            ->all();

        if ($unresolved === []) {
            return;
        }

        throw new BusinessException(
            'Sales credit memo '.$memo->memo_number.' has unresolved pricing on: '.implode(', ', $unresolved)
                .'. Enter an explicit document-currency price before posting.',
            title: 'Sales credit memo price unresolved',
            field: 'items',
            codeIdentifier: 'sales_credit_memo_price_unresolved',
        );
    }
}
