<?php

namespace Database\Factories;

use App\Enums\AccountCategory;
use App\Enums\AccountStructuralType;
use App\Enums\IncomeBalanceType;
use App\Models\ChartOfAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

class ChartOfAccountFactory extends Factory
{
    protected $model = ChartOfAccount::class;

    public function definition(): array
    {
        return [
            'account_number' => $this->faker->unique()->numerify('#####'),
            'name' => $this->faker->words(3, true),
            'structural_type' => AccountStructuralType::POSTING,
            'account_category' => AccountCategory::ASSET,
            'balance' => 0,
            'direct_posting' => true,
            'blocked' => false,
            'income_balance' => IncomeBalanceType::BALANCE_SHEET,
        ];
    }
}
