<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CurrencyExchangeRateType;
use App\Models\Currency;
use App\Models\CurrencyExchangeRate;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class CurrencyService
{
    private const CACHE_TTL = 3600; // 1 hour

    private const DEFAULT_LCY = 'USD';

    /**
     * Get Local Currency (LCY)
     */
    public function getLCY(): Currency
    {
        return Cache::remember('currency.lcy', self::CACHE_TTL, function () {
            return Currency::where('is_lcy', true)->first()
                ?? Currency::firstOrCreate(
                    ['code' => self::DEFAULT_LCY],
                    [
                        'description' => 'US Dollar',
                        'symbol' => '$',
                        'is_lcy' => true,
                        'decimal_places' => 2,
                    ]
                );
        });
    }

    /**
     * Get currency by code
     */
    public function getByCode(string $code): ?Currency
    {
        return Cache::remember("currency.{$code}", self::CACHE_TTL, function () use ($code) {
            return Currency::with('currentExchangeRate')->where('code', $code)->first();
        });
    }

    /**
     * Create or update exchange rate (BC: Change Exch. Rate)
     */
    public function setExchangeRate(
        string $currencyCode,
        float $rate,
        ?\DateTime $date = null,
        ?CurrencyExchangeRateType $type = null,
        ?string $source = 'manual'
    ): CurrencyExchangeRate {
        $currency = $this->getByCode($currencyCode);

        if (! $currency) {
            throw new \InvalidArgumentException("Currency {$currencyCode} not found");
        }

        return DB::transaction(function () use ($currency, $rate, $date, $type, $source) {
            $date = $date ?? now();
            $type = $type ?? CurrencyExchangeRateType::SPOT;

            // Close current rate
            CurrencyExchangeRate::where('currency_id', $currency->id)
                ->where('is_current', true)
                ->update(['is_current' => false, 'ending_date' => $date]);

            // Create new rate
            $exchangeRate = CurrencyExchangeRate::create([
                'currency_id' => $currency->id,
                'starting_date' => $date,
                'exchange_rate_amount' => $rate,
                'relational_exch_rate_amount' => 1,
                'rate_type' => $type,
                'source' => $source,
                'is_current' => true,
            ]);

            // Update currency cache
            $currency->update([
                'exchange_rate' => $rate,
                'exchange_rate_date' => $date,
            ]);

            Cache::forget("currency.{$currency->code}");

            return $exchangeRate;
        });
    }

    /**
     * Fetch exchange rates from external API (ECB, etc.)
     */
    public function fetchExternalRates(string $provider = 'ecb'): Collection
    {
        $rates = collect();

        switch ($provider) {
            case 'ecb':
                $rates = $this->fetchECBRates();
                break;
            case 'fixer':
                $rates = $this->fetchFixerRates();
                break;
        }

        foreach ($rates as $code => $rate) {
            $currency = Currency::where('code', $code)->first();

            if ($currency && ! $currency->is_lcy) {
                $this->setExchangeRate($code, $rate, now(), CurrencyExchangeRateType::SPOT, $provider);
            }
        }

        return $rates;
    }

    /**
     * Convert amount between currencies
     */
    public function convert(
        float $amount,
        string $fromCurrency,
        string $toCurrency,
        ?\DateTime $date = null
    ): float {
        $from = $this->getByCode($fromCurrency);
        $to = $this->getByCode($toCurrency);

        if (! $from || ! $to) {
            throw new \InvalidArgumentException('Invalid currency code');
        }

        // Same currency
        if ($from->code === $to->code) {
            return $amount;
        }

        // Convert via LCY
        $amountLCY = $from->toLCY($amount, null, $date);

        return $to->fromLCY($amountLCY, null, $date);
    }

    /**
     * Calculate exchange rate gain/loss between two dates
     */
    public function calculateExchangeVariation(
        float $amountFCY,
        string $currencyCode,
        \DateTime $originalDate,
        \DateTime $newDate
    ): float {
        $currency = $this->getByCode($currencyCode);

        $originalRate = $currency->getExchangeRate($originalDate);
        $newRate = $currency->getExchangeRate($newDate);

        $originalLCY = $amountFCY * $originalRate;
        $newLCY = $amountFCY * $newRate;

        return $newLCY - $originalLCY;
    }

    /**
     * Get all active currencies with rates
     */
    public function getActiveCurrencies(): Collection
    {
        return Currency::active()
            ->with('currentExchangeRate')
            ->get()
            ->map(function ($currency) {
                return [
                    'code' => $currency->code,
                    'description' => $currency->description,
                    'symbol' => $currency->symbol,
                    'exchange_rate' => $currency->is_lcy ? 1 : $currency->getExchangeRate(),
                    'is_lcy' => $currency->is_lcy,
                ];
            });
    }

    /**
     * Validate currency for transaction
     */
    public function validateForTransaction(string $currencyCode, ?\DateTime $date = null): void
    {
        $currency = $this->getByCode($currencyCode);

        if (! $currency) {
            throw new \InvalidArgumentException("Currency {$currencyCode} not found");
        }

        if (! $currency->is_active) {
            throw new \InvalidArgumentException("Currency {$currencyCode} is inactive");
        }

        if (! $currency->is_lcy) {
            $rate = $currency->getExchangeRate($date);

            if ($rate <= 0) {
                throw new \InvalidArgumentException("No valid exchange rate for {$currencyCode} on {$date?->format('Y-m-d')}");
            }
        }
    }

    /**
     * Round amount according to currency rules
     */
    public function roundAmount(float $amount, string $currencyCode): float
    {
        $currency = $this->getByCode($currencyCode);

        return $currency?->roundAmount($amount) ?? round($amount, 2);
    }

    /**
     * Format amount for display
     */
    public function format(float $amount, string $currencyCode): string
    {
        $currency = $this->getByCode($currencyCode);

        return $currency?->formatAmount($amount) ?? number_format($amount, 2);
    }

    // Private methods
    private function fetchECBRates(): Collection
    {
        try {
            $response = Http::get('https://api.exchangerate.host/latest?base=USD');

            if ($response->successful()) {
                return collect($response->json('rates'));
            }
        } catch (\Exception $e) {
            report($e);
        }

        return collect();
    }

    private function fetchFixerRates(): Collection
    {
        // Implementation for Fixer.io or other paid services
        return collect();
    }
}
