<?php

namespace Database\Factories;

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
            'account_type' => 'REVENUE',
            'account_category' => 'RECEIVABLE',
            'balance' => 0,
            'direct_posting' => true,
            'blocked' => false,
        ];
    }
}
