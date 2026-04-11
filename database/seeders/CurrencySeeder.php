<?php

namespace Database\Seeders;

use App\Models\Currency;
use App\Models\ChartOfAccount;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        // Get Gain/Loss accounts for linking
        $unrealizedGain = ChartOfAccount::where('account_number', '72100')->first()?->id;
        $unrealizedLoss = ChartOfAccount::where('account_number', '72200')->first()?->id;
        $realizedGain = ChartOfAccount::where('account_number', '72300')->first()?->id;
        $realizedLoss = ChartOfAccount::where('account_number', '72400')->first()?->id;

        $currencies = [
            [
                'code' => 'NGN',
                'description' => 'Nigerian Naira',
                'symbol' => '₦',
                'decimal_places' => 2,
                'is_lcy' => true, // Local Currency
                'is_active' => true,
                'exchange_rate' => 1.0000,
                'iso_numeric_code' => '566',
                'amount_rounding_precision' => 0.01,
                'unit_amount_rounding_precision' => 0.00001,
            ],
            [
                'code' => 'USD',
                'description' => 'United States Dollar',
                'symbol' => '$',
                'decimal_places' => 2,
                'is_lcy' => false,
                'is_active' => true,
                'exchange_rate' => 1500.0000, // 1 USD = 1500 NGN
                'iso_numeric_code' => '840',
                'amount_rounding_precision' => 0.01,
                'unit_amount_rounding_precision' => 0.00001,
            ],
            [
                'code' => 'GBP',
                'description' => 'British Pound Sterling',
                'symbol' => '£',
                'decimal_places' => 2,
                'is_lcy' => false,
                'is_active' => true,
                'exchange_rate' => 1900.0000, // 1 GBP = 1900 NGN
                'iso_numeric_code' => '826',
                'amount_rounding_precision' => 0.01,
                'unit_amount_rounding_precision' => 0.00001,
            ],
            [
                'code' => 'EUR',
                'description' => 'Euro',
                'symbol' => '€',
                'decimal_places' => 2,
                'is_lcy' => false,
                'is_active' => true,
                'exchange_rate' => 1630.0000, // 1 EUR = 1630 NGN
                'iso_numeric_code' => '978',
                'amount_rounding_precision' => 0.01,
                'unit_amount_rounding_precision' => 0.00001,
            ],
            [
                'code' => 'CNY',
                'description' => 'Chinese Yuan Renminbi',
                'symbol' => '¥',
                'decimal_places' => 2,
                'is_lcy' => false,
                'is_active' => true,
                'exchange_rate' => 210.0000, // 1 CNY = 210 NGN
                'iso_numeric_code' => '156',
                'amount_rounding_precision' => 0.01,
                'unit_amount_rounding_precision' => 0.00001,
            ],
            [
                'code' => 'JPY',
                'description' => 'Japanese Yen',
                'symbol' => '¥',
                'decimal_places' => 0,
                'is_lcy' => false,
                'is_active' => true,
                'exchange_rate' => 10.0000, // 1 JPY = 10 NGN
                'iso_numeric_code' => '392',
                'amount_rounding_precision' => 1.00,
                'unit_amount_rounding_precision' => 0.001,
            ],
            [
                'code' => 'CAD',
                'description' => 'Canadian Dollar',
                'symbol' => 'C$',
                'decimal_places' => 2,
                'is_lcy' => false,
                'is_active' => true,
                'exchange_rate' => 1100.0000, // 1 CAD = 1100 NGN
                'iso_numeric_code' => '124',
                'amount_rounding_precision' => 0.01,
                'unit_amount_rounding_precision' => 0.00001,
            ],
        ];

        foreach ($currencies as $currencyData) {
            $currencyData['unrealized_gains_account_id'] = $unrealizedGain;
            $currencyData['unrealized_losses_account_id'] = $unrealizedLoss;
            $currencyData['realized_gains_account_id'] = $realizedGain;
            $currencyData['realized_losses_account_id'] = $realizedLoss;
            
            Currency::updateOrCreate(
                ['code' => $currencyData['code']],
                $currencyData
            );
        }
    }
}
