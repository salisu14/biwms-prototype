<?php

namespace Database\Factories;

use App\Enums\AccountCategory;
use App\Enums\BankAccountType;
use App\Enums\IncomeBalanceType;
use App\Models\BankAccount;
use App\Models\ChartOfAccount;
use App\Models\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BankAccount>
 */
class BankAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_code' => strtoupper($this->faker->unique()->lexify('BANK-???')),
            'account_name' => $this->faker->company().' Operating Account',
            'bank_name' => $this->faker->company().' Bank',
            'bank_branch' => $this->faker->city().' Branch',
            'account_number' => $this->faker->bankAccountNumber(),
            'routing_number' => $this->faker->numerify('#########'),
            'gl_account_id' => ChartOfAccount::factory()->state([
                'account_category' => AccountCategory::ASSET,
                'income_balance' => IncomeBalanceType::BALANCE_SHEET,
            ]),
            'currency_id' => $this->localCurrencyId(),
            'account_type' => BankAccountType::CHECKING,
            'current_balance' => 0,
            'available_balance' => 0,
            'next_check_number' => '1000',
            'active' => true,
            'allow_payments' => true,
            'allow_receipts' => true,
        ];
    }

    public function receiptOnly(): static
    {
        return $this->state([
            'allow_payments' => false,
            'allow_receipts' => true,
        ]);
    }

    public function paymentOnly(): static
    {
        return $this->state([
            'allow_payments' => true,
            'allow_receipts' => false,
        ]);
    }

    private function localCurrencyId(): int
    {
        return Currency::query()->firstOrCreate(
            ['code' => 'NGN'],
            [
                'description' => 'Nigerian Naira',
                'symbol' => '₦',
                'decimal_places' => 2,
                'is_active' => true,
                'is_lcy' => true,
                'exchange_rate' => 1.0,
            ],
        )->id;
    }
}
