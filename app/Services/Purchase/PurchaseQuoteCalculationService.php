<?php

declare(strict_types=1);

namespace App\Services\Purchase;

use App\Models\PurchaseQuote;
use App\Models\PurchaseQuoteLine;

class PurchaseQuoteCalculationService
{
    public function recalculateQuote(PurchaseQuote $quote): void
    {
        foreach ($quote->lines as $line) {
            $this->recalculateLine($line);
        }

        $quote->calculateTotals();
    }

    public function recalculateLine(PurchaseQuoteLine $line): void
    {
        $line->calculateAmounts();
        $line->save();
    }

    public function applyCurrencyConversion(PurchaseQuote $quote, float $newCurrencyFactor): void
    {
        $oldFactor = $quote->currency_factor;

        foreach ($quote->lines as $line) {
            $line->direct_unit_cost = ($line->direct_unit_cost / $oldFactor) * $newCurrencyFactor;
            $line->calculateAmounts();
            $line->save();
        }

        $quote->currency_factor = $newCurrencyFactor;
        $quote->calculateTotals();
    }
}
