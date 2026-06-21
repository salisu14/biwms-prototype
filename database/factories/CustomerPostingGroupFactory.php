<?php

namespace Database\Factories;

use App\Models\ChartOfAccount;
use App\Models\CustomerPostingGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerPostingGroupFactory extends Factory
{
    protected $model = CustomerPostingGroup::class;

    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->lexify('CUST-???'),
            'description' => $this->faker->sentence(),
            'receivables_account_id' => ChartOfAccount::factory(),
            'blocked' => false,
        ];
    }
}
