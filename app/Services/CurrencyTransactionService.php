<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Currency;
use App\Models\PurchaseQuote;
use App\Models\PurchaseQuoteLine;

class CurrencyTransactionService
{
    public function __construct(
        private readonly CurrencyService $currencyService
    ) {}

    /**
     * Initialize currency for purchase quote
     */
    public function initializeForQuote(PurchaseQuote $quote, ?string $currencyCode = null): void
    {
        $vendor = $quote->vendor;

        // Use vendor's currency if none specified
        $currencyCode = $currencyCode ?? $vendor->currency ?? $this->currencyService->getLCY()->code;

        $currency = $this->currencyService->getByCode($currencyCode);

        if (! $currency) {
            throw new \InvalidArgumentException("Currency {$currencyCode} not found");
        }

        $this->currencyService->validateForTransaction($currencyCode, $quote->document_date);

        $quote->update([
            'currency_code' => $currency->code,
            'currency_factor' => $currency->getExchangeRate($quote->document_date),
        ]);

        // Recalculate all lines
        foreach ($quote->lines as $line) {
            $this->recalculateLineCurrency($line, $currency);
        }

        $quote->calculateTotals();
    }

    /**
     * Recalculate line amounts with currency conversion
     */
    public function recalculateLineCurrency(PurchaseQuoteLine $line, Currency $currency): void
    {
        // If line was entered in different currency, convert it
        if ($line->currency_code && $line->currency_code !== $currency->code) {
            $oldCurrency = $this->currencyService->getByCode($line->currency_code);

            // Convert direct unit cost to new currency
            $costInLCY = $oldCurrency->toLCY($line->direct_unit_cost);
            $line->direct_unit_cost = $currency->fromLCY($costInLCY);

            $line->currency_code = $currency->code;
        }

        // Recalculate amounts
        $line->calculateAmounts();
        $line->save();
    }

    /**
     * Convert quote totals to LCY for reporting
     */
    public function convertQuoteToLCY(PurchaseQuote $quote): array
    {
        $currency = $this->currencyService->getByCode($quote->currency_code);

        return [
            'amount_lcy' => $currency->toLCY($quote->amount, $quote->currency_factor),
            'vat_amount_lcy' => $currency->toLCY($quote->vat_amount, $quote->currency_factor),
            'amount_including_vat_lcy' => $currency->toLCY($quote->amount_including_vat, $quote->currency_factor),
            'exchange_rate_used' => $quote->currency_factor,
        ];
    }

    /**
     * Apply currency rounding to quote totals
     */
    public function applyRounding(PurchaseQuote $quote): void
    {
        $currency = $this->currencyService->getByCode($quote->currency_code);

        if ($currency->invoice_rounding) {
            $rounded = $currency->roundInvoice($quote->amount_including_vat);

            if ($rounded['difference'] != 0) {
                // Create rounding line or adjust total
                $quote->rounding_difference = $rounded['difference'];
                $quote->amount_including_vat = $rounded['rounded'];
            }
        }
    }

    /**
     * Update exchange rate on quote (if rate changed)
     */
    public function updateExchangeRate(PurchaseQuote $quote): void
    {
        if ($quote->isReleased()) {
            throw new \InvalidArgumentException('Cannot change exchange rate on released quote');
        }

        $currency = $this->currencyService->getByCode($quote->currency_code);
        $currentRate = $currency->getExchangeRate($quote->document_date);

        if (abs($currentRate - $quote->currency_factor) > 0.000001) {
            $quote->update(['currency_factor' => $currentRate]);

            // Recalculate all lines with new rate
            foreach ($quote->lines as $line) {
                $line->calculateAmounts();
                $line->save();
            }

            $quote->calculateTotals();
        }
    }
}
